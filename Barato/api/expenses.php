<?php
// api/expenses.php - Expense tracking API

require_once '../config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
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

function handleGet($pdo) {
    if (isset($_GET['stats'])) {
        // Get expense statistics
        $stats = [];
        
        // Total expenses
        $stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses WHERE status != 'rejected'");
        $stats['total_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
        
        // This month expenses
        $currentMonth = date('Y-m');
        $stmt = $pdo->prepare("SELECT SUM(amount) as monthly FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? AND status != 'rejected'");
        $stmt->execute([$currentMonth]);
        $stats['monthly_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['monthly'] ?: 0;
        
        // Pending approvals
        $stmt = $pdo->query("SELECT SUM(amount) as pending FROM expenses WHERE status = 'pending'");
        $stats['pending_approvals'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?: 0;
        
        // Budget remaining (assuming monthly budget of 50000)
        $monthlyBudget = 50000;
        $stats['budget_remaining'] = $monthlyBudget - $stats['monthly_expenses'];
        $stats['budget_percentage'] = ($stats['budget_remaining'] / $monthlyBudget) * 100;
        
        // Expenses by category
        $stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? AND status != 'rejected' GROUP BY category");
        $stmt->execute([$currentMonth]);
        $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Monthly trend (last 6 months)
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(amount) as total 
            FROM expenses 
            WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status != 'rejected'
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m') 
            ORDER BY month ASC
        ");
        $stats['monthly_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse($stats);
        
    } elseif (isset($_GET['search'])) {
        // Search expenses
        $search = '%' . $_GET['search'] . '%';
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE description LIKE ? OR category LIKE ? ORDER BY expense_date DESC");
        $stmt->execute([$search, $search]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($expenses);
        
    } elseif (isset($_GET['category'])) {
        // Filter by category
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE category = ? ORDER BY expense_date DESC");
        $stmt->execute([$_GET['category']]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($expenses);
        
    } elseif (isset($_GET['date_range'])) {
        // Filter by date range
        $dateRange = $_GET['date_range'];
        $endDate = date('Y-m-d');
        
        switch ($dateRange) {
            case 'today':
                $startDate = date('Y-m-d');
                break;
            case 'week':
                $startDate = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $startDate = date('Y-m-01');
                break;
            case 'quarter':
                $startDate = date('Y-m-d', strtotime('-3 months'));
                break;
            case 'year':
                $startDate = date('Y-01-01');
                break;
            default:
                $startDate = date('Y-m-01');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC");
        $stmt->execute([$startDate, $endDate]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($expenses);
        
    } else {
        // Get all expenses with pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        $stmt = $pdo->prepare("SELECT * FROM expenses ORDER BY expense_date DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse($expenses);
    }
}

function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    $required = ['description', 'amount', 'category', 'expense_date'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    // Validate category
    $validCategories = ['office', 'travel', 'marketing', 'utilities', 'meals'];
    if (!in_array($input['category'], $validCategories)) {
        jsonResponse(['error' => 'Invalid category'], 400);
    }
    
    // Validate amount
    if (!is_numeric($input['amount']) || $input['amount'] <= 0) {
        jsonResponse(['error' => 'Amount must be a positive number'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, category, expense_date, receipt_path, status, created_by) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            sanitizeInput($input['description']),
            (float)$input['amount'],
            sanitizeInput($input['category']),
            $input['expense_date'],
            isset($input['receipt_path']) ? sanitizeInput($input['receipt_path']) : null,
            1 // Default user ID, should be from session in real app
        ]);
        
        $expenseId = $pdo->lastInsertId();
        
        // Return the created expense
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$expenseId]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($expense, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handlePut($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Expense ID required'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    try {
        // Check if expense exists
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            jsonResponse(['error' => 'Expense not found'], 404);
        }
        
        // Build update query dynamically
        $updates = [];
        $values = [];
        
        $allowedFields = ['description', 'amount', 'category', 'expense_date', 'receipt_path', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                if ($field === 'amount') {
                    $values[] = (float)$input[$field];
                } else {
                    $values[] = sanitizeInput($input[$field]);
                }
            }
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No valid fields to update'], 400);
        }
        
        // Validate category if provided
        if (isset($input['category'])) {
            $validCategories = ['office', 'travel', 'marketing', 'utilities', 'meals'];
            if (!in_array($input['category'], $validCategories)) {
                jsonResponse(['error' => 'Invalid category'], 400);
            }
        }
        
        // Validate amount if provided
        if (isset($input['amount']) && (!is_numeric($input['amount']) || $input['amount'] <= 0)) {
            jsonResponse(['error' => 'Amount must be a positive number'], 400);
        }
        
        // Validate status if provided
        if (isset($input['status'])) {
            $validStatuses = ['pending', 'approved', 'rejected'];
            if (!in_array($input['status'], $validStatuses)) {
                jsonResponse(['error' => 'Invalid status'], 400);
            }
        }
        
        $values[] = $_GET['id'];
        
        $sql = "UPDATE expenses SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        // Return updated expense
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($expense);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Expense ID required'], 400);
    }
    
    try {
        // Check if expense can be deleted (only pending expenses)
        $stmt = $pdo->prepare("SELECT status FROM expenses WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            jsonResponse(['error' => 'Expense not found'], 404);
        }
        
        if ($expense['status'] === 'approved') {
            jsonResponse(['error' => 'Cannot delete approved expenses'], 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $result = $stmt->execute([$_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['message' => 'Expense deleted successfully']);
        } else {
            jsonResponse(['error' => 'Failed to delete expense'], 500);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Export expenses to CSV
function exportExpenses($pdo, $filters = []) {
    $sql = "SELECT description, amount, category, expense_date, status FROM expenses WHERE 1=1";
    $params = [];
    
    if (isset($filters['category'])) {
        $sql .= " AND category = ?";
        $params[] = $filters['category'];
    }
    
    if (isset($filters['start_date'])) {
        $sql .= " AND expense_date >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (isset($filters['end_date'])) {
        $sql .= " AND expense_date <= ?";
        $params[] = $filters['end_date'];
    }
    
    $sql .= " ORDER BY expense_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV
    $filename = "expenses_export_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['Description', 'Amount', 'Category', 'Date', 'Status']);
    
    // CSV data
    foreach ($expenses as $expense) {
        fputcsv($output, [
            $expense['description'],
            number_format($expense['amount'], 2),
            ucfirst($expense['category']),
            $expense['expense_date'],
            ucfirst($expense['status'])
        ]);
    }
    
    fclose($output);
    exit;
}

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filters = [];
    if (isset($_GET['category'])) $filters['category'] = $_GET['category'];
    if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
    if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
    
    exportExpenses($pdo, $filters);
}

// Seed sample expense data
function seedExpenseData($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM expenses");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $sampleExpenses = [
            ['Office Supplies - Printer Paper', 1250.00, 'office', '2025-06-19'],
            ['Business Trip to Cebu', 8500.00, 'travel', '2025-06-18'],
            ['Google Ads Campaign', 5000.00, 'marketing', '2025-06-17'],
            ['Electricity Bill - June', 3200.00, 'utilities', '2025-06-16'],
            ['Team Lunch Meeting', 2800.00, 'meals', '2025-06-15'],
            ['Software Subscription - Slack', 1500.00, 'office', '2025-06-14'],
            ['Taxi to Client Meeting', 450.00, 'travel', '2025-06-13'],
            ['Marketing Materials', 3500.00, 'marketing', '2025-06-12'],
            ['Water Bill - June', 800.00, 'utilities', '2025-06-11'],
            ['Coffee for Office', 1200.00, 'meals', '2025-06-10'],
            ['Laptop Repair', 4500.00, 'office', '2025-06-09'],
            ['Flight to Manila', 6800.00, 'travel', '2025-06-08'],
            ['Facebook Ads', 2500.00, 'marketing', '2025-06-07'],
            ['Internet Bill - June', 2200.00, 'utilities', '2025-06-06'],
            ['Client Dinner', 3800.00, 'meals', '2025-06-05']
        ];
        
        $statuses = ['pending', 'approved', 'approved', 'approved'];
        
        foreach ($sampleExpenses as $expense) {
            $status = $statuses[array_rand($statuses)];
            $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, category, expense_date, status, created_by) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$expense[0], $expense[1], $expense[2], $expense[3], $status]);
        }
    }
}

// Seed data if needed
try {
    seedExpenseData($pdo);
} catch (Exception $e) {
    error_log("Expense seeding error: " . $e->getMessage());
}
?>