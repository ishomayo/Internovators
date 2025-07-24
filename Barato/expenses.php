<?php
  $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql = $db->query("SELECT * FROM expenses");
  $month_expenses_sql = $db->query("SELECT SUM(amount) AS total_this_month FROM expenses WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AND created_at < DATE_FORMAT(CURRENT_DATE + INTERVAL 1 MONTH, '%Y-%m-01')");
  $total_expenses_sql = $db->query("SELECT SUM(amount) AS total FROM expenses");
  $categories_sql = $db->query("SELECT DISTINCT category FROM expenses");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker - Business Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">B</div>
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
                <a href="expenses.html" class="nav-item active">
                    <div class="nav-icon">📈</div>
                    Expense Tracker
                </a>
                <a href="support.html" class="nav-item">
                    <div class="nav-icon">💬</div>
                    Communication & Support
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1 class="page-title">📈 Expense Tracker</h1>
                <p class="page-subtitle">Monitor business expenses, track spending patterns, and manage your budget</p>
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
                        foreach($month_expenses_sql as $row) { 
                            ?> 
                            <?= $row["total_this_month"] ?>
                        <?php } ?>
                    </div>
                    <div class="stat-label">This Month</div>
                    <div class="stat-change stat-decrease">-12% from last month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">₱32,000</div>
                    <div class="stat-label">Budget Remaining</div>
                    <div class="stat-change">64% of monthly budget</div>
                </div>
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
                                    <th>Receipt</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach($sql as $row) { ?>
                                    <tr>
                                    <td><?= $row["created_at"] ?></td>
                                    <td><?= $row["description"] ?></td>
                                    <td><span class="category-badge category-office"><?= $row["category"] ?></span></td>
                                    <td><?= $row["amount"] ?></td>
                                    <td>
                                    <button class="btn-secondary" style="padding: 4px 8px; font-size: 12px;">Edit</button>
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
                        <div class="chart-placeholder">
                            <iframe src="https://lookerstudio.google.com/reporting/4c947710-c55f-44a7-9a6d-47cbc637183c" frameborder="0"></iframe>
                            📊 Interactive expense chart would be displayed here
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="expenseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            <h2 style="margin-bottom: 1.5rem;">Add New Expense</h2>
            <form id="expenseForm">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Amount (₱)</label>
                    <input type="number" id="expenseAmount" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem;">Category</label>
                    <select id="expenseCategory" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <?php 
                        foreach($categories_sql as $row) { ?>
                            <option value="<?= $row["category"] ?>"><?= $row["category"] ?></option>
                        <?php } ?>
                    </select>
                    <button type="button" id="newCategoryBtn" style="margin-top: 0.5rem;" class="btn-secondary">+ Add New Category</button>
                </div>

                <div style="display: none" id="newCategoryInput">
                    <label style="display: block; margin-bottom: 0.5rem;">New Category Name</label>
                    <input type="text" id="newCategory" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                    <button type="button" class="btn-secondary" onclick="closeExpenseModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Expense</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

        // New category button functionality
        document.getElementById('newCategoryBtn').addEventListener('click', function() {
            const newCategoryInput = document.getElementById('newCategoryInput');
            newCategoryInput.style.display = newCategoryInput.style.display === 'none' ? 'block' : 'none';
        });

        // Expense form submission
        document.getElementById('expenseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = parseFloat(document.getElementById('expenseAmount').value);
            if (amount <= 0) {
                alert('Please enter a positive amount');
                return;
            }
            
            let category = document.getElementById('expenseCategory').value;
            const newCategory = document.getElementById('newCategory').value.trim();
            
            if (newCategory) {
                // Check if category already exists (case-insensitive)
                const existingCategories = Array.from(document.getElementById('expenseCategory').options)
                    .map(option => option.value.toLowerCase());
                
                if (existingCategories.includes(newCategory.toLowerCase())) {
                    // If category exists, just use the existing category
                    category = document.getElementById('expenseCategory').value;
                } else {
                    // If it's a new category, add it
                    category = newCategory;
                    // Add new category to select options
                    const option = new Option(newCategory, newCategory);
                    document.getElementById('expenseCategory').add(option);
                    // Also add to filter select
                    const filterOption = new Option(newCategory, newCategory);
                    document.querySelector('.filter-select').add(filterOption);
                }
            }

            // Create new table row
            const tbody = document.querySelector('.expense-table tbody');
            const newRow = document.createElement('tr');
            const today = new Date();
            
            newRow.innerHTML = `
                <td>${today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                <td>New Expense Entry</td>
                <td><span class="category-badge category-${category.toLowerCase()}">${category}</span></td>
                <td>₱${amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td>📄</td>
                <td>
                    <button class="btn-secondary" style="padding: 4px 8px; font-size: 12px;">Edit</button>
                </td>
            `;
            
            tbody.insertBefore(newRow, tbody.firstChild);
            closeExpenseModal();
        });

        // Close modal when clicking outside
        document.getElementById('expenseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExpenseModal();
            }
        });
    </script>
</body>
</html>