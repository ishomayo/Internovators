<?php
    session_start();

    $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle AJAX request for AI insights
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_ai_insights') {
        header('Content-Type: application/json');
        
        try {
            // Get expense data from database
            $total_expenses_query = $db->query("SELECT SUM(amount) AS total FROM expenses");
            $month_expenses_query = $db->query("SELECT SUM(amount) AS total_this_month FROM expenses WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AND created_at < DATE_FORMAT(CURRENT_DATE + INTERVAL 1 MONTH, '%Y-%m-01')");
            $budget_query = $db->query("SELECT budget FROM monthly_budget");
            $categories_query = $db->query("SELECT category, SUM(amount) as total_amount FROM expenses GROUP BY category ORDER BY total_amount DESC");
            $recent_expenses_query = $db->query("SELECT * FROM expenses ORDER BY created_at DESC LIMIT 10");
            
            $total_expenses = $total_expenses_query->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            $month_expenses = $month_expenses_query->fetch(PDO::FETCH_ASSOC)['total_this_month'] ?? 0;
            $monthly_budget = $budget_query->fetch(PDO::FETCH_ASSOC)['budget'] ?? 50000;
            $remaining_budget = $monthly_budget - $month_expenses;
            
            // Prepare data for AI analysis
            $expense_data = [
                'total_expenses' => $total_expenses,
                'month_expenses' => $month_expenses,
                'monthly_budget' => $monthly_budget,
                'remaining_budget' => $remaining_budget,
                'categories' => [],
                'recent_expenses' => []
            ];
            
            // Get category breakdown
            foreach($categories_query as $row) {
                $expense_data['categories'][$row['category']] = $row['total_amount'];
            }
            
            // Get recent expenses
            foreach($recent_expenses_query as $row) {
                $expense_data['recent_expenses'][] = [
                    'date' => $row['created_at'],
                    'description' => $row['description'],
                    'category' => $row['category'],
                    'amount' => $row['amount']
                ];
            }
            
            // Create prompt for AI
            $prompt = "Analyze the following business expense data and provide actionable insights and recommendations:\n\n";
            $prompt .= "Expense Summary:\n";
            $prompt .= "- Total Expenses: ₱" . number_format($total_expenses, 2) . "\n";
            $prompt .= "- This Month: ₱" . number_format($month_expenses, 2) . "\n";
            $prompt .= "- Monthly Budget: ₱" . number_format($monthly_budget, 2) . "\n";
            $prompt .= "- Budget Remaining: ₱" . number_format($remaining_budget, 2) . "\n\n";
            
            $prompt .= "Category Breakdown:\n";
            foreach($expense_data['categories'] as $category => $amount) {
                $prompt .= "- {$category}: ₱" . number_format($amount, 2) . "\n";
            }
            
            $prompt .= "\nRecent Transactions:\n";
            foreach($expense_data['recent_expenses'] as $expense) {
                $prompt .= "- {$expense['date']}: {$expense['description']} ({$expense['category']}) - ₱" . number_format($expense['amount'], 2) . "\n";
            }
            
            $prompt .= "\nPlease provide 4-6 specific, actionable insights focusing on:\n";
            $prompt .= "1. Cost optimization opportunities\n";
            $prompt .= "2. Spending pattern analysis\n";
            $prompt .= "3. Budget management recommendations\n";
            $prompt .= "4. Policy or process improvements\n";
            $prompt .= "5. Risk identification\n\n";
            $prompt .= "Format each insight as:\n";
            $prompt .= "Priority: [High/Medium/Low]\n";
            $prompt .= "Title: [Brief title]\n";
            $prompt .= "Description: [Detailed explanation and recommended action]\n\n";
            $prompt .= "Keep insights practical and relevant to a Philippine business context.";
            
            // Call Rev2 Labs Chat Service API
            $api_key = "NjdhMGE3OTYtMWYxNi00M2YwLWJlZGYtMTFlZmZkN2EzMzRm"; // Your Rev2 Labs API key
            
            // First, get or create a session
            $session_ch = curl_init();
            curl_setopt($session_ch, CURLOPT_URL, 'https://ai-tools.rev2llabs.com/api/v1/ai/session');
            curl_setopt($session_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($session_ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($session_ch, CURLOPT_HTTPHEADER, [
                'x-api-key: ' . $api_key
            ]);
            
            $session_response = curl_exec($session_ch);
            $session_http_code = curl_getinfo($session_ch, CURLINFO_HTTP_CODE);
            curl_close($session_ch);
            
            if ($session_http_code !== 200) {
                throw new Exception("Rev2 Labs Session Error: HTTP $session_http_code");
            }
            
            $session_data = json_decode($session_response, true);
            if (!$session_data || !isset($session_data['session_id'])) {
                throw new Exception("Failed to create Rev2 Labs session");
            }
            
            $session_id = $session_data['session_id'];
            
            // Now send the chat message
            $chat_data = [
                'content' => "You are a financial analyst specializing in business expense optimization. Analyze the following expense data and provide 4-6 actionable insights:\n\n" . $prompt . "\n\nFormat each insight as:\nPriority: [High/Medium/Low]\nTitle: [Brief title]\nDescription: [Detailed explanation and recommended action]"
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://ai-tools.rev2llabs.com/api/v1/ai/chat');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'session-id: ' . $session_id,
                'Content-Type: application/json',
                'x-api-key: ' . $api_key
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($chat_data));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception("Network error: $curlError");
            }
            
            if ($httpCode === 429) {
                // Rate limit exceeded - provide demo insights
                $insights = generateDemoInsights($expense_data);
                echo json_encode([
                    'success' => true,
                    'insights' => $insights,
                    'demo' => true,
                    'message' => 'Rev2 Labs rate limit reached. Showing demo insights based on your data.'
                ]);
                exit;
            }
            
            if ($httpCode === 401) {
                throw new Exception("Invalid API key. Please check your Rev2 Labs API key configuration.");
            }
            
            if ($httpCode === 403) {
                throw new Exception("API access forbidden. Please check your Rev2 Labs account status.");
            }
            
            if ($httpCode !== 200) {
                // For any other error, fall back to demo insights
                $insights = generateDemoInsights($expense_data);
                echo json_encode([
                    'success' => true,
                    'insights' => $insights,
                    'demo' => true,
                    'message' => "Rev2 Labs API temporarily unavailable (HTTP $httpCode). Showing demo insights."
                ]);
                exit;
            }
            
            $ai_response = json_decode($response, true);
            
            if (!$ai_response || !isset($ai_response['content'])) {
                throw new Exception("Invalid response from Rev2 Labs API");
            }
            
            $ai_content = $ai_response['content'];
            
            // Parse AI response into structured insights
            $insights = [];
            $sections = preg_split('/(?=Priority:|Title:)/', $ai_content);
            
            foreach ($sections as $section) {
                if (preg_match('/Priority:\s*(\w+)/i', $section, $priority_match) &&
                    preg_match('/Title:\s*([^\n]+)/i', $section, $title_match) &&
                    preg_match('/Description:\s*([\s\S]+?)(?=Priority:|$)/i', $section, $desc_match)) {
                    
                    $insights[] = [
                        'priority' => strtolower(trim($priority_match[1])),
                        'title' => trim($title_match[1]),
                        'description' => trim($desc_match[1])
                    ];
                }
            }
            
            // Fallback parsing if structured format fails
            if (empty($insights)) {
                $lines = array_filter(explode("\n", $ai_content), function($line) {
                    return !empty(trim($line));
                });
                
                $current_insight = null;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (stripos($line, 'priority:') !== false || stripos($line, 'title:') !== false || preg_match('/^\d+\./', $line)) {
                        if ($current_insight) {
                            $insights[] = $current_insight;
                        }
                        $current_insight = [
                            'priority' => 'medium',
                            'title' => preg_replace('/Priority:|Title:|\d+\./', '', $line),
                            'description' => ''
                        ];
                    } elseif ($current_insight && !empty($line)) {
                        $current_insight['description'] .= $line . ' ';
                    }
                }
                if ($current_insight) {
                    $insights[] = $current_insight;
                }
            }
            
            echo json_encode([
                'success' => true,
                'insights' => $insights
            ]);
            
        } catch (Exception $e) {
            // Fallback to demo insights on any error
            $demo_insights = generateDemoInsights($expense_data ?? []);
            echo json_encode([
                'success' => true,
                'insights' => $demo_insights,
                'demo' => true,
                'message' => 'Using demo insights due to API limitations. ' . $e->getMessage()
            ]);
        }
        
        exit;
    }

    // Function to generate demo insights based on actual data
    function generateDemoInsights($expense_data) {
        $insights = [];
        
        // Budget Analysis
        if (isset($expense_data['remaining_budget']) && isset($expense_data['monthly_budget'])) {
            $budget_usage = (($expense_data['monthly_budget'] - $expense_data['remaining_budget']) / $expense_data['monthly_budget']) * 100;
            
            if ($budget_usage > 80) {
                $insights[] = [
                    'priority' => 'high',
                    'title' => 'Budget Alert - High Usage Detected',
                    'description' => 'You have used ' . number_format($budget_usage, 1) . '% of your monthly budget. Consider reviewing non-essential expenses and implementing stricter approval processes for the remainder of the month.'
                ];
            } elseif ($budget_usage < 50) {
                $insights[] = [
                    'priority' => 'low',
                    'title' => 'Budget Opportunity - Under-utilization',
                    'description' => 'You have used only ' . number_format($budget_usage, 1) . '% of your monthly budget. Consider investing in growth opportunities or equipment upgrades while staying within budget.'
                ];
            }
        }
        
        // Category Analysis
        if (isset($expense_data['categories']) && !empty($expense_data['categories'])) {
            $total_expenses = array_sum($expense_data['categories']);
            $top_category = array_keys($expense_data['categories'], max($expense_data['categories']))[0];
            $top_amount = max($expense_data['categories']);
            $top_percentage = ($top_amount / $total_expenses) * 100;
            
            if ($top_percentage > 40) {
                $insights[] = [
                    'priority' => 'medium',
                    'title' => 'Category Concentration Risk - ' . $top_category,
                    'description' => $top_category . ' expenses represent ' . number_format($top_percentage, 1) . '% of total spending. Consider diversifying expenses or negotiating better rates for ' . strtolower($top_category) . ' to reduce financial risk.'
                ];
            }
            
            // Travel expense analysis
            if (isset($expense_data['categories']['Travel']) && $expense_data['categories']['Travel'] > 5000) {
                $insights[] = [
                    'priority' => 'medium',
                    'title' => 'Travel Cost Optimization',
                    'description' => 'Travel expenses are ₱' . number_format($expense_data['categories']['Travel'], 2) . '. Consider implementing a travel policy with preferred vendors, advance booking requirements, and video conferencing alternatives for some meetings.'
                ];
            }
            
            // Office supplies analysis
            if (isset($expense_data['categories']['Office'])) {
                $insights[] = [
                    'priority' => 'low',
                    'title' => 'Office Supplies Efficiency',
                    'description' => 'Office supply expenses are ₱' . number_format($expense_data['categories']['Office'], 2) . '. Consider bulk purchasing agreements with suppliers and implementing inventory management to reduce costs by 10-15%.'
                ];
            }
        }
        
        // Monthly trend analysis
        if (isset($expense_data['month_expenses']) && isset($expense_data['total_expenses'])) {
            $monthly_average = $expense_data['total_expenses'] / 12; // Rough estimate
            if ($expense_data['month_expenses'] > $monthly_average * 1.2) {
                $insights[] = [
                    'priority' => 'high',
                    'title' => 'Above-Average Monthly Spending',
                    'description' => 'This month\'s expenses (₱' . number_format($expense_data['month_expenses'], 2) . ') are significantly above your historical average. Review recent transactions for unusual or one-time expenses that may need categorization or policy review.'
                ];
            }
        }
        
        // General recommendations
        $insights[] = [
            'priority' => 'low',
            'title' => 'Process Improvement Opportunity',
            'description' => 'Implement digital receipt management and automated expense categorization to improve tracking accuracy and reduce manual data entry time by up to 70%.'
        ];
        
        $insights[] = [
            'priority' => 'medium',
            'title' => 'Financial Planning Enhancement',
            'description' => 'Consider setting up quarterly expense reviews and category-specific budgets to better control spending and identify cost-saving opportunities before they become significant.'
        ];
        
        return array_slice($insights, 0, 6); // Return max 6 insights
    }

    // Regular page loading continues here...
    $sql = $db->query("SELECT * FROM expenses");
    $month_expenses_sql = $db->query("SELECT SUM(amount) AS total_this_month FROM expenses WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AND created_at < DATE_FORMAT(CURRENT_DATE + INTERVAL 1 MONTH, '%Y-%m-01')");
    $budget_sql = $db->query("SELECT budget FROM monthly_budget");
    $total_expenses_sql = $db->query("SELECT SUM(amount) AS total FROM expenses");
    $categories_sql = $db->query("SELECT DISTINCT category FROM expenses");
    $remaining_budget = 0;
    $budget_percentage = 0;
    $monthly_budget = 0;

    foreach($budget_sql as $row) {
        $monthly_budget = $row["budget"];
        foreach($month_expenses_sql as $month_row) {
            $remaining_budget = $row["budget"] - $month_row["total_this_month"];
        }
    }
    $budget_percentage = ($remaining_budget / $monthly_budget) * 100;

    // Get expense data grouped by category
    $chart_sql = $db->query("
        SELECT 
            category,
            SUM(amount) as total_amount
        FROM expenses 
        GROUP BY category 
        ORDER BY total_amount DESC
    ");

    $categories = [];
    $amounts = [];
    $backgroundColors = [];
    $totalExpenses = 0;

    // Calculate total first
    $total_query = $db->query("SELECT SUM(amount) as total FROM expenses");
    $total_row = $total_query->fetch(PDO::FETCH_ASSOC);
    $totalExpenses = $total_row['total'];

    // Define colors
    $colors = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
    ];

    $colorIndex = 0;
    foreach($chart_sql as $row) {
        $categories[] = $row['category'];
        $amounts[] = floatval($row['total_amount']); // Ensure float conversion
        $backgroundColors[] = $colors[$colorIndex % count($colors)];
        $colorIndex++;
    }

    // Convert to JSON for JavaScript
    $chartData = [
        'categories' => $categories,
        'amounts' => $amounts,
        'colors' => $backgroundColors
    ];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker - Business Hub</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* AI Insights Section */
        .ai-insights {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 1.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .ai-insights::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }

        .ai-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .ai-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .ai-icon {
            width: 24px;
            height: 24px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .ai-refresh-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ai-refresh-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .ai-refresh-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .ai-content {
            position: relative;
            z-index: 1;
        }

        .ai-loading {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            opacity: 0.9;
        }

        .ai-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .ai-insights-list {
            list-style: none;
        }

        .ai-insight-item {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid rgba(255,255,255,0.3);
        }

        .ai-insight-item:last-child {
            margin-bottom: 0;
        }

        .ai-insight-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ai-insight-description {
            font-size: 13px;
            line-height: 1.4;
            opacity: 0.9;
        }

        .insight-priority-high {
            border-left-color: #fbbf24;
        }

        .insight-priority-medium {
            border-left-color: #60a5fa;
        }

        .insight-priority-low {
            border-left-color: #34d399;
        }

        .ai-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 1rem;
            color: #fecaca;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <img class="logo" src="assets/logo.png" alt="barato logo">
        <div class="header-right">
            <div class="time-info">
                <div>Philippine Standard Time</div>
                <div id="current-time">Friday, June 20, 2025, 9:29:45 AM</div>
            </div>
            <button class="notification-btn">🔔</button>
            <button class="user-btn">
                <div class="user-avatar">👤</div>
                <span>User</span>
                <span>▼</span>
            </button>
        </div>
    </div>

    <div class="main-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="nav-section">
                <div class="nav-title">Home</div>
                <a href="landing.html" class="nav-item">
                    <div class="nav-icon">📊</div>
                    Dashboard
                </a>
            </div>
            
            <div class="nav-section">
                <a href="inventory.html" class="nav-item">
                    <div class="nav-icon">📦</div>
                    Inventory Management
                </a>
                <a href="payroll.html" class="nav-item">
                    <div class="nav-icon">💰</div>
                    Payroll
                </a>
                <a href="expenses.php" class="nav-item active">
                    <div class="nav-icon">📈</div>
                    Expense Tracker
                </a>
                <a href="support.html" class="nav-item">
                    <div class="nav-icon">💬</div>
                    Communication & Support
                </a>
                <a href="logistics.html" class="nav-item">
                    <div class="nav-icon">🚚</div>
                    Logistics & Suppliers
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1 class="page-title">📈 Expense Tracker</h1>
                <p class="page-subtitle">Monitor business expenses, track spending patterns, and manage your budget</p>
            </div>

            <!-- AI Insights Section -->
            <div class="ai-insights">
                <div class="ai-header">
                    <div class="ai-title">
                        <div class="ai-icon">🤖</div>
                        AI Analysis & Insights
                    </div>
                    <button class="ai-refresh-btn" id="refresh-insights">
                        <span>🔄</span>
                        Generate Insights
                    </button>
                </div>
                <div class="ai-content" id="ai-content">
                    <div class="ai-loading" id="ai-loading" style="display: none;">
                        <div class="ai-spinner"></div>
                        Analyzing your expense data with AI...
                    </div>
                    <div id="ai-insights-container">
                        <p style="opacity: 0.9; font-size: 14px;">Click "Generate Insights" to get AI-powered analysis of your expense patterns and actionable recommendations.</p>
                    </div>
                </div>
            </div>

            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-value">
                        ₱<?php 
                        foreach($total_expenses_sql as $row) { 
                            ?> 
                            <?= $row["total"] ?>
                        <?php } ?>
                    </div>
                    <div class="stat-label">Total Expenses</div>
                    <div class="stat-change stat-increase"></div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">
                        ₱<?php
                        $month_expenses_sql = $db->query("SELECT SUM(amount) AS total_this_month FROM expenses WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AND created_at < DATE_FORMAT(CURRENT_DATE + INTERVAL 1 MONTH, '%Y-%m-01')");
   
                        foreach($month_expenses_sql as $row) { 
                            ?> 
                            <?= $row["total_this_month"] ?>
                        <?php } ?>
                    </div>
                    <div class="stat-label">This Month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">
                        ₱<?= $remaining_budget ?>
                    </div>
                    <div class="stat-label">Budget Remaining</div>
                    <div class="stat-change"><?= $budget_percentage ?>% of monthly budget</div>
                </div>
                <form action="edit-budget.php" method="POST">
                    
                    <div class="stat-card">
                        <div class="stat-value" style="display: flex; align-items: center; gap: 8px;">
                        <?php
                            $budget_sql = $db->query("SELECT budget FROM monthly_budget");
  
                            foreach($budget_sql as $row) { ?>
                               <input name="budget" type="number" min=0 step=0.01 value="<?= $current_budget = $row["budget"]; ?>" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <?php } ?>                                                    
                        </div>
                        <div class="stat-label">Initial Budget of the Month</div>
                        <div style="display: flex; gap: 8px; margin-top: 10px;">
                            <input type="hidden" name="current_budget" value="<?= $current_budget ?>">
                            <button type="submit" class="btn-primary" style="flex: 1;">Confirm Change</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="expense-grid">
                <div class="expense-panel">
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <button class="btn-primary" id="openExpenseModal">+ Add Expense</button>
                            <select class="filter-select">
                                <option>All Categories</option>
                                <?php 
                                    foreach($categories_sql as $row) { ?>
                                    <option><?= $row["category"] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <input type="text" class="search-bar" placeholder="Search expenses...">
                    </div>
                    
                    <div class="panel-content">
                        <table class="expense-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($sql as $row) { ?>
                                    <tr data-expense-id="<?= $row["id"] ?>">
                                        <td hidden><?= $row["id"] ?></td>
                                        <td><?= $row["created_at"] ?></td>
                                        <td><?= $row["description"] ?></td>
                                        <td><span class="category-badge category-office"><?= $row["category"] ?></span></td>
                                        <td>₱<?= $row["amount"] ?></td>
                                        <td>
                                            <button class="btn-secondary edit-btn" style="padding: 4px 8px; font-size: 12px;" 
                                                    data-expense-id="<?= $row["id"] ?>"
                                                    data-amount="<?= $row["amount"] ?>"
                                                    data-description="<?= $row["description"] ?>"
                                                    data-category="<?= $row["category"] ?>">
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="side-panel">
                    <div class="chart-container">
                        <div class="chart-title">Expense Breakdown by Category</div>
                        <canvas id="expenseChart"></canvas>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="expenseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            <h2 style="margin-bottom: 1.5rem;">Add New Expense</h2>
            <form id="expenseForm" action="add-expense.php" method="POST">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Amount (₱)</label>
                    <input name="amount" type="number" min=0 id="expenseAmount" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Decription</label>
                    <input name="description" id="expenseDescription" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;" required>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Category</label>
                    <select name="category" id="expenseCategory" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option>Select Category</option>
                        <?php
                            $categories_sql = $db->query("SELECT DISTINCT category FROM expenses");

                            foreach($categories_sql as $row) { ?>
                            <option value="<?= $row["category"] ?>"><?= $row["category"] ?></option>
                        <?php } ?>
                    </select>
                    <button type="button" id="newCategoryBtn" style="margin-top: 0.5rem;" class="btn-secondary">+ Add New Category</button>
                </div>
                
                <div style="display: none" id="newCategoryInput">
                    <label style="display: block; margin-bottom: 0.5rem;">New Category Name</label>
                    <input name="newCategory" type="text" id="newCategory" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                    <button type="button" class="btn-secondary" onclick="closeExpenseModal()">Cancel</button>
                    <input type="submit" class="btn-primary" value="Add Expense" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;"></input>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div id="editExpenseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            <h2 style="margin-bottom: 1.5rem;">Edit Expense</h2>
            <form id="editExpenseForm" action="edit-expense.php" method="POST">
                <input type="hidden" name="id" id="editExpenseId">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Amount (₱)</label>
                    <input name="amount" type="number" id="editExpenseAmount" min=0 required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Description</label>
                    <input name="description" id="editExpenseDescription" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Category</label>
                    <select name="category" id="editExpenseCategory" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <?php
                            $categories_sql = $db->query("SELECT DISTINCT category FROM expenses");

                            foreach($categories_sql as $row) { ?>
                            <option value="<?= $row["category"] ?>"><?= $row["category"] ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
                    <button type="submit" name="act" class="btn-danger" value="Delete Expense">Delete Expense</button>
                    <div>
                        <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" name="act" class="btn-primary" value="Save Changes">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // AI Insights Generation
        async function generateAIInsights() {
            const loadingEl = document.getElementById('ai-loading');
            const contentEl = document.getElementById('ai-insights-container');
            const refreshBtn = document.getElementById('refresh-insights');

            // Show loading state
            loadingEl.style.display = 'flex';
            contentEl.innerHTML = '';
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<span>⏳</span> Analyzing...';

            try {
                const response = await fetch('expenses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=generate_ai_insights'
                });

                if (!response.ok) {
                    throw new Error(`Server Error: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    const insights = data.insights || [];
                    const isDemo = data.demo || false;
                    displayInsights(insights, isDemo);
                    
                    if (data.message) {
                        console.log('AI Insights Message:', data.message);
                    }
                } else {
                    throw new Error(data.error || 'Failed to generate insights');
                }

            } catch (error) {
                console.error('Error generating AI insights:', error);
                contentEl.innerHTML = `
                    <div class="ai-error">
                        <strong>Error generating insights:</strong> ${error.message}
                        <br><br>
                        Please make sure your Rev2 Labs API key is configured in expenses.php and try again.
                    </div>
                `;
            } finally {
                loadingEl.style.display = 'none';
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<span>🔄</span> Generate Insights';
            }
        }

        // Display insights in the UI
        function displayInsights(insights, isDemo = false) {
            const contentEl = document.getElementById('ai-insights-container');
            
            if (insights.length === 0) {
                contentEl.innerHTML = `
                    <div class="ai-error">
                        No insights could be generated. Please try again or check your expense data.
                    </div>
                `;
                return;
            }
            
            let demoMessage = '';
            if (isDemo) {
                demoMessage = `
                    <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; margin-bottom: 1rem; font-size: 13px; opacity: 0.9;">
                        💡 <strong>Demo Mode:</strong> These insights are generated from your actual expense data using built-in analysis. 
                    </div>
                `;
            }
            
            const insightsList = insights.map(insight => `
                <div class="ai-insight-item insight-priority-${insight.priority}">
                    <div class="ai-insight-title">
                        ${getPriorityIcon(insight.priority)} ${insight.title}
                    </div>
                    <div class="ai-insight-description">
                        ${insight.description}
                    </div>
                </div>
            `).join('');
            
            contentEl.innerHTML = demoMessage + `<div class="ai-insights-list">${insightsList}</div>`;
        }

        // Get priority icon
        function getPriorityIcon(priority) {
            switch (priority) {
                case 'high': return '🔴';
                case 'medium': return '🟡';
                case 'low': return '🟢';
                default: return '🔵';
            }
        }

        // Event listeners for AI functionality
        document.getElementById('refresh-insights').addEventListener('click', generateAIInsights);

        // Update time every second
        function updateTime() {
            const now = new Date();
            const options = {
                timeZone: 'Asia/Manila',
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const timeString = now.toLocaleString('en-US', options);
            document.getElementById('current-time').textContent = timeString;
        }

        updateTime();
        setInterval(updateTime, 1000);

        // Search functionality
        document.querySelector('.search-bar').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.expense-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Filter functionality
        document.querySelector('.filter-select').addEventListener('change', function(e) {
            const filterValue = e.target.value;
            const rows = document.querySelectorAll('.expense-table tbody tr');
            
            rows.forEach(row => {
                if (filterValue === 'All Categories') {
                    row.style.display = '';
                } else {
                    const categoryCell = row.querySelector('.category-badge');
                    const categoryText = categoryCell ? categoryCell.textContent : '';
                    row.style.display = categoryText.includes(filterValue.split(' ')[0]) ? '' : 'none';
                }
            });
        });

        // Add click handlers for buttons
        document.addEventListener('click', function(e) {
            if (e.target.textContent === '+ Add Expense' || e.target.id === 'openExpenseModal') {
                document.getElementById('expenseModal').style.display = 'block';
            } else if (e.target.textContent === 'Upload Receipt') {
                alert('Receipt upload functionality would be implemented here');
            } else if (e.target.textContent === 'Edit') {
                alert('Edit expense dialog would open here');
            }
        });

        // Receipt click handler
        document.addEventListener('click', function(e) {
            if (e.target.textContent === '📄') {
                alert('Receipt viewer would open here');
            }
        });

        // Open Expense Modal
        function openExpenseModal() {
            document.getElementById('expenseModal').style.display = 'block';
        }

        // Close modal function
        function closeExpenseModal() {
            document.getElementById('expenseModal').style.display = 'none';
            document.getElementById('newCategoryInput').style.display = 'none';
            document.getElementById('expenseForm').reset();
        }

        // Edit expense functionality
        function openEditModal(expenseId, amount, description, category) {
            document.getElementById('editExpenseModal').style.display = 'block';
            document.getElementById('editExpenseId').value = expenseId;
            document.getElementById('editExpenseAmount').value = amount;
            document.getElementById('editExpenseDescription').value = description;
            document.getElementById('editExpenseCategory').value = category;
        }

        function closeEditModal() {
            document.getElementById('editExpenseModal').style.display = 'none';
            document.getElementById('editExpenseForm').reset();
        }

        // New category button functionality
        document.getElementById('newCategoryBtn').addEventListener('click', function() {
            const newCategoryInput = document.getElementById('newCategoryInput');
            newCategoryInput.style.display = newCategoryInput.style.display === 'none' ? 'block' : 'none';
        });

        // Update click handler for edit buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-btn')) {
                const expenseId = e.target.dataset.expenseId;
                const amount = e.target.dataset.amount;
                const description = e.target.dataset.description;
                const category = e.target.dataset.category;
                openEditModal(expenseId, amount, description, category);
            }
        });

        // Handle edit form submission
        document.getElementById('editExpenseForm').addEventListener('submit', function(e) {
            const expenseId = document.getElementById('editExpenseId').value;
            const amount = document.getElementById('editExpenseAmount').value;
            const category = document.getElementById('editExpenseCategory').value;
            const description = document.getElementById('editExpenseDescription').value;
        });

        // Delete expense functionality
        function deleteExpense() {
            if (!confirm('Are you sure you want to delete this expense?')) {
                return;
            }

            const expenseId = document.getElementById('editExpenseId').value;
        }

        // Close modal when clicking outside
        document.getElementById('expenseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExpenseModal();
            }
        });

        document.getElementById('editExpenseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Chart functionality
        const chartData = <?php echo json_encode($chartData); ?>;

        const ctx = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartData.categories,
                datasets: [{
                    data: chartData.amounts.map(Number), // Ensure numbers
                    backgroundColor: chartData.colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                size: 12
                            },
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = Number(context.raw);
                                const dataset = context.dataset.data.map(Number);
                                const sum = dataset.reduce((a, b) => a + b, 0);
                                const percentage = ((value / sum) * 100).toFixed(1);
                                
                                const formattedValue = new Intl.NumberFormat('en-PH', {
                                    style: 'currency',
                                    currency: 'PHP',
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }).format(value);
                                
                                return `${context.label}: ${formattedValue} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
