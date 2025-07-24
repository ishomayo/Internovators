<?php
// payroll.php - Fixed for root directory

// Include the config file (same directory)
require_once('config.php');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getConnection();
    
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Payroll API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}

function handleGet($pdo) {
    if (isset($_GET['stats'])) {
        // Get payroll statistics
        $stats = [];
        
        // Current month payroll
        $currentMonth = date('Y-m');
        $stmt = $pdo->prepare("SELECT SUM(gross_pay) as total_payroll, SUM(deductions) as total_deductions, SUM(net_pay) as net_payroll FROM payroll WHERE DATE_FORMAT(pay_period_end, '%Y-%m') = ?");
        $stmt->execute([$currentMonth]);
        $payrollData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['total_payroll'] = $payrollData['total_payroll'] ?: 0;
        $stats['total_deductions'] = $payrollData['total_deductions'] ?: 0;
        $stats['net_payroll'] = $payrollData['net_payroll'] ?: 0;
        
        // Active employees count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
        $stats['active_employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Payroll by status
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM payroll WHERE DATE_FORMAT(pay_period_end, '%Y-%m') = ? GROUP BY status");
        $stmt->execute([$currentMonth]);
        $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['payroll_status'] = $statusData;
        
        jsonResponse($stats);
        
    } elseif (isset($_GET['employees'])) {
        // Get employees for payroll
        $stmt = $pdo->query("SELECT id, employee_id, full_name, position, department, gross_salary, deductions, status FROM employees WHERE status = 'active' ORDER BY full_name");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($employees);
        
    } elseif (isset($_GET['employee_id'])) {
        // Get payroll history for specific employee
        $stmt = $pdo->prepare("
            SELECT p.*, e.full_name, e.employee_id, e.department 
            FROM payroll p 
            JOIN employees e ON p.employee_id = e.id 
            WHERE e.id = ? 
            ORDER BY p.pay_period_end DESC
        ");
        $stmt->execute([$_GET['employee_id']]);
        $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($payrolls);
        
    } else {
        // Get current payroll records
        $currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        
        $stmt = $pdo->prepare("
            SELECT p.*, e.full_name, e.employee_id, e.department, e.position 
            FROM payroll p 
            JOIN employees e ON p.employee_id = e.id 
            WHERE DATE_FORMAT(p.pay_period_end, '%Y-%m') = ? 
            ORDER BY e.full_name
        ");
        $stmt->execute([$currentMonth]);
        $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse($payrolls);
    }
}

function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    if (isset($input['action']) && $input['action'] === 'run_payroll') {
        // Run payroll for all active employees
        runPayrollForAllEmployees($pdo, $input);
        return;
    }
    
    // Create individual payroll record
    $required = ['employee_id', 'pay_period_start', 'pay_period_end', 'gross_pay'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    try {
        // Check if employee exists
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active'");
        $stmt->execute([$input['employee_id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            jsonResponse(['error' => 'Employee not found or inactive'], 404);
        }
        
        // Check if payroll already exists for this period
        $stmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND pay_period_start = ? AND pay_period_end = ?");
        $stmt->execute([$input['employee_id'], $input['pay_period_start'], $input['pay_period_end']]);
        
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Payroll already exists for this period'], 409);
        }
        
        $deductions = isset($input['deductions']) ? (float)$input['deductions'] : $employee['deductions'];
        $grossPay = (float)$input['gross_pay'];
        $netPay = $grossPay - $deductions;
        
        $stmt = $pdo->prepare("INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, gross_pay, deductions, net_pay, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([
            $input['employee_id'],
            $input['pay_period_start'],
            $input['pay_period_end'],
            $grossPay,
            $deductions,
            $netPay
        ]);
        
        $payrollId = $pdo->lastInsertId();
        
        // Return the created payroll record
        $stmt = $pdo->prepare("
            SELECT p.*, e.full_name, e.employee_id, e.department 
            FROM payroll p 
            JOIN employees e ON p.employee_id = e.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$payrollId]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($payroll, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function runPayrollForAllEmployees($pdo, $input) {
    try {
        $pdo->beginTransaction();
        
        // Get current month period
        $currentMonth = date('Y-m');
        $periodStart = date('Y-m-01');
        $periodEnd = date('Y-m-t');
        
        // Get all active employees
        $stmt = $pdo->query("SELECT * FROM employees WHERE status = 'active'");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processed = 0;
        $skipped = 0;
        
        foreach ($employees as $employee) {
            // Check if payroll already exists for this month
            $stmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND pay_period_start = ? AND pay_period_end = ?");
            $stmt->execute([$employee['id'], $periodStart, $periodEnd]);
            
            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }
            
            $grossPay = $employee['gross_salary'];
            $deductions = $employee['deductions'];
            $netPay = $grossPay - $deductions;
            
            $stmt = $pdo->prepare("INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, gross_pay, deductions, net_pay, status) VALUES (?, ?, ?, ?, ?, ?, 'processing')");
            $stmt->execute([
                $employee['id'],
                $periodStart,
                $periodEnd,
                $grossPay,
                $deductions,
                $netPay
            ]);
            
            $processed++;
        }
        
        $pdo->commit();
        
        jsonResponse([
            'message' => 'Payroll processed successfully',
            'processed' => $processed,
            'skipped' => $skipped,
            'period' => $currentMonth
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Failed to run payroll: ' . $e->getMessage()], 500);
    }
}

function handlePut($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Payroll ID required'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    try {
        // Check if payroll exists
        $stmt = $pdo->prepare("SELECT * FROM payroll WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            jsonResponse(['error' => 'Payroll record not found'], 404);
        }
        
        // Build update query dynamically
        $updates = [];
        $values = [];
        
        $allowedFields = ['gross_pay', 'deductions', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                if (in_array($field, ['gross_pay', 'deductions'])) {
                    $values[] = (float)$input[$field];
                } else {
                    $values[] = sanitizeInput($input[$field]);
                }
            }
        }
        
        // Recalculate net pay if gross pay or deductions changed
        if (isset($input['gross_pay']) || isset($input['deductions'])) {
            $grossPay = isset($input['gross_pay']) ? (float)$input['gross_pay'] : $existing['gross_pay'];
            $deductions = isset($input['deductions']) ? (float)$input['deductions'] : $existing['deductions'];
            $updates[] = "net_pay = ?";
            $values[] = $grossPay - $deductions;
        }
        
        // Update processed timestamp if status changed to paid
        if (isset($input['status']) && $input['status'] === 'paid' && $existing['status'] !== 'paid') {
            $updates[] = "processed_at = NOW()";
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No valid fields to update'], 400);
        }
        
        $values[] = $_GET['id'];
        
        $sql = "UPDATE payroll SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        // Return updated payroll record
        $stmt = $pdo->prepare("
            SELECT p.*, e.full_name, e.employee_id, e.department 
            FROM payroll p 
            JOIN employees e ON p.employee_id = e.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($payroll);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Payroll ID required'], 400);
    }
    
    try {
        // Check if payroll can be deleted (only pending records)
        $stmt = $pdo->prepare("SELECT status FROM payroll WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payroll) {
            jsonResponse(['error' => 'Payroll record not found'], 404);
        }
        
        if ($payroll['status'] === 'paid') {
            jsonResponse(['error' => 'Cannot delete paid payroll records'], 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
        $result = $stmt->execute([$_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['message' => 'Payroll record deleted successfully']);
        } else {
            jsonResponse(['error' => 'Failed to delete payroll record'], 500);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Seed sample employee and payroll data
function seedPayrollData($pdo) {
    try {
        // Check if employees exist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            // Seed employees
            $employees = [
                ['EMP001', 'Juan Carlos', 'Software Developer', 'Engineering', 65000.00, 8450.00, 'active', '2023-01-15'],
                ['EMP002', 'Maria Santos', 'Marketing Manager', 'Marketing', 55000.00, 7150.00, 'active', '2022-03-20'],
                ['EMP003', 'Robert Cruz', 'Sales Representative', 'Sales', 42000.00, 5460.00, 'active', '2023-06-10'],
                ['EMP004', 'Anna Reyes', 'HR Specialist', 'Human Resources', 48000.00, 6240.00, 'active', '2022-11-05'],
                ['EMP005', 'David Lim', 'Accountant', 'Finance', 52000.00, 6760.00, 'active', '2023-02-28']
            ];
            
            foreach ($employees as $emp) {
                $stmt = $pdo->prepare("INSERT INTO employees (employee_id, full_name, position, department, gross_salary, deductions, status, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute($emp);
            }
            
            // Seed some payroll records for current month
            $currentMonth = date('Y-m');
            $periodStart = date('Y-m-01');
            $periodEnd = date('Y-m-t');
            
            $stmt = $pdo->query("SELECT * FROM employees WHERE status = 'active'");
            $activeEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $statuses = ['paid', 'processing', 'pending'];
            
            foreach ($activeEmployees as $employee) {
                $status = $statuses[array_rand($statuses)];
                $grossPay = $employee['gross_salary'];
                $deductions = $employee['deductions'];
                $netPay = $grossPay - $deductions;
                
                $stmt = $pdo->prepare("INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, gross_pay, deductions, net_pay, status, processed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $processedAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;
                $stmt->execute([
                    $employee['id'],
                    $periodStart,
                    $periodEnd,
                    $grossPay,
                    $deductions,
                    $netPay,
                    $status,
                    $processedAt
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Payroll seeding error: " . $e->getMessage());
    }
}

// Seed data if needed
seedPayrollData($pdo);
?>