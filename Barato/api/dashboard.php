<?php
// api/dashboard.php - Main dashboard API

require_once '../config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getConnection();

if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get comprehensive dashboard data
function getDashboardData($pdo) {
    $dashboardData = [];
    
    try {
        // Revenue data (mock calculation based on expenses and inventory)
        $stmt = $pdo->query("SELECT SUM(stock_quantity * price) as inventory_value FROM inventory");
        $inventoryValue = $stmt->fetch(PDO::FETCH_ASSOC)['inventory_value'] ?: 0;
        
        // Mock revenue as percentage of inventory value
        $dashboardData['total_revenue'] = round($inventoryValue * 0.052, 2); // ~5.2% of inventory value
        $dashboardData['revenue_change'] = '+12.5%';
        
        // Active orders (mock data based on inventory items)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE stock_quantity > 0");
        $inStockItems = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $dashboardData['active_orders'] = $inStockItems * 15; // Mock multiplier
        $dashboardData['orders_change'] = '+8.2%';
        
        // Monthly expenses
        $currentMonth = date('Y-m');
        $stmt = $pdo->prepare("SELECT SUM(amount) as monthly_expenses FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? AND status != 'rejected'");
        $stmt->execute([$currentMonth]);
        $monthlyExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_expenses'] ?: 0;
        $dashboardData['monthly_expenses'] = $monthlyExpenses;
        $dashboardData['expenses_change'] = '-3.1%';
        
        // Inventory health
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN stock_quantity > 20 THEN 1 ELSE 0 END) as healthy_stock
            FROM inventory");
        $inventoryStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inventoryStats['total_products'] > 0) {
            $healthPercentage = round(($inventoryStats['healthy_stock'] / $inventoryStats['total_products']) * 100);
        } else {
            $healthPercentage = 0;
        }
        $dashboardData['inventory_health'] = $healthPercentage;
        $dashboardData['inventory_status'] = 'Optimal levels maintained';
        
        // Recent activities (combination of various activities)
        $dashboardData['recent_activities'] = getRecentActivities($pdo);
        
        // Quick stats for widgets
        $dashboardData['quick_stats'] = [
            'total_employees' => getTotalEmployees($pdo),
            'pending_expenses' => getPendingExpenses($pdo),
            'open_tickets' => getOpenTickets($pdo),
            'active_suppliers' => getActiveSuppliers($pdo)
        ];
        
        // Monthly trends (last 6 months)
        $dashboardData['monthly_trends'] = getMonthlyTrends($pdo);
        
        // Alerts and notifications
        $dashboardData['alerts'] = getSystemAlerts($pdo);
        
        return $dashboardData;
        
    } catch (Exception $e) {
        error_log("Dashboard API error: " . $e->getMessage());
        return ['error' => 'Failed to fetch dashboard data'];
    }
}

function getRecentActivities($pdo) {
    $activities = [];
    
    try {
        // Recent expenses
        $stmt = $pdo->query("SELECT 'expense' as type, description as title, amount as value, created_at as timestamp FROM expenses ORDER BY created_at DESC LIMIT 3");
        $expenseActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent inventory updates (mock data)
        $stmt = $pdo->query("SELECT 'inventory' as type, CONCAT('Updated ', product_name) as title, stock_quantity as value, updated_at as timestamp FROM inventory ORDER BY updated_at DESC LIMIT 3");
        $inventoryActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent support tickets
        $stmt = $pdo->query("SELECT 'support' as type, subject as title, priority as value, created_at as timestamp FROM support_tickets ORDER BY created_at DESC LIMIT 2");
        $supportActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine and sort activities
        $activities = array_merge($expenseActivities, $inventoryActivities, $supportActivities);
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, 10); // Return top 10 recent activities
        
    } catch (Exception $e) {
        error_log("Recent activities error: " . $e->getMessage());
        return [];
    }
}

function getTotalEmployees($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (Exception $e) {
        return 0;
    }
}

function getPendingExpenses($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM expenses WHERE status = 'pending'");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (Exception $e) {
        return 0;
    }
}

function getOpenTickets($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (Exception $e) {
        return 0;
    }
}

function getActiveSuppliers($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (Exception $e) {
        return 0;
    }
}

function getMonthlyTrends($pdo) {
    $trends = [];
    
    try {
        // Get last 6 months of expense data
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(expense_date, '%Y-%m') as month,
                SUM(amount) as total_expenses,
                COUNT(*) as expense_count
            FROM expenses 
            WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                AND status != 'rejected'
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m') 
            ORDER BY month ASC
        ");
        
        $expenseData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fill in missing months with zero values
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[$month] = [
                'month' => $month,
                'expenses' => 0,
                'revenue' => 0, // Mock revenue data
                'orders' => 0    // Mock orders data
            ];
        }
        
        // Populate with actual expense data
        foreach ($expenseData as $data) {
            if (isset($months[$data['month']])) {
                $months[$data['month']]['expenses'] = (float)$data['total_expenses'];
                // Mock revenue and orders based on expenses
                $months[$data['month']]['revenue'] = (float)$data['total_expenses'] * 2.5;
                $months[$data['month']]['orders'] = (int)$data['expense_count'] * 8;
            }
        }
        
        return array_values($months);
        
    } catch (Exception $e) {
        error_log("Monthly trends error: " . $e->getMessage());
        return [];
    }
}

function getSystemAlerts($pdo) {
    $alerts = [];
    
    try {
        // Low stock alerts
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE stock_quantity < 20 AND stock_quantity > 0");
        $lowStockCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Stock Alert',
                'message' => "$lowStockCount items are running low on stock",
                'action' => 'View Inventory',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Out of stock alerts
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE stock_quantity = 0");
        $outOfStockCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($outOfStockCount > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Out of Stock',
                'message' => "$outOfStockCount items are out of stock",
                'action' => 'Reorder Now',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Pending expense approvals
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM expenses WHERE status = 'pending'");
        $pendingExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($pendingExpenses > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pending Approvals',
                'message' => "$pendingExpenses expense reports need approval",
                'action' => 'Review Expenses',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Open support tickets
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
        $openTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($openTickets > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Open Support Tickets',
                'message' => "$openTickets support tickets need attention",
                'action' => 'View Tickets',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Payroll processing reminder (mock - check if it's end of month)
        $dayOfMonth = (int)date('d');
        if ($dayOfMonth >= 25) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Payroll Reminder',
                'message' => 'Month-end payroll processing is due soon',
                'action' => 'Process Payroll',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $alerts;
        
    } catch (Exception $e) {
        error_log("System alerts error: " . $e->getMessage());
        return [];
    }
}

// Handle different dashboard endpoints
if (isset($_GET['endpoint'])) {
    $endpoint = $_GET['endpoint'];
    
    switch ($endpoint) {
        case 'stats':
            // Just return key statistics
            $stats = [
                'revenue' => getDashboardData($pdo)['total_revenue'],
                'orders' => getDashboardData($pdo)['active_orders'],
                'expenses' => getDashboardData($pdo)['monthly_expenses'],
                'inventory_health' => getDashboardData($pdo)['inventory_health']
            ];
            jsonResponse($stats);
            break;
            
        case 'activities':
            // Just return recent activities
            $activities = getRecentActivities($pdo);
            jsonResponse($activities);
            break;
            
        case 'alerts':
            // Just return system alerts
            $alerts = getSystemAlerts($pdo);
            jsonResponse($alerts);
            break;
            
        case 'trends':
            // Just return monthly trends
            $trends = getMonthlyTrends($pdo);
            jsonResponse($trends);
            break;
            
        default:
            jsonResponse(['error' => 'Invalid endpoint'], 404);
    }
} else {
    // Return full dashboard data
    $dashboardData = getDashboardData($pdo);
    jsonResponse($dashboardData);
}
?>