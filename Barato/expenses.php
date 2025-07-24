<?php
    session_start();

  $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
                    <div class="stat-change stat-decrease">-12% from last month</div>
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
                        <div class="chart-placeholder">
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

        // Expense form submission
        // document.getElementById('expenseForm').addEventListener('submit', function(e) {
        //     const amount = parseFloat(document.getElementById('expenseAmount').value);
        //     if (amount <= 0) {
        //         alert('Please enter a positive amount');
        //         return;
        //     }
            
        //     let category = document.getElementById('expenseCategory').value;
        //     const newCategory = document.getElementById('newCategory').value.trim();
            
        //     if (newCategory) {
        //         // Check if category already exists (case-insensitive)
        //         const existingCategories = Array.from(document.getElementById('expenseCategory').options)
        //             .map(option => option.value.toLowerCase());
                
        //         if (existingCategories.includes(newCategory.toLowerCase())) {
        //             // If category exists, just use the existing category
        //             category = document.getElementById('expenseCategory').value;
        //         } else {
        //             // If it's a new category, add it
        //             category = newCategory;
        //             // Add new category to select options
        //             const option = new Option(newCategory, newCategory);
        //             document.getElementById('expenseCategory').add(option);
        //             // Also add to filter select
        //             const filterOption = new Option(newCategory, newCategory);
        //             document.querySelector('.filter-select').add(filterOption);
        //         }
        //     }

        //     // Create new table row
        //     const tbody = document.querySelector('.expense-table tbody');
        //     const newRow = document.createElement('tr');
        //     const today = new Date();
            
        //     newRow.innerHTML = `
        //         <td>${today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
        //         <td>New Expense Entry</td>
        //         <td><span class="category-badge category-${category.toLowerCase()}">${category}</span></td>
        //         <td>₱${amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        //         <td>📄</td>
        //         <td>
        //             <button class="btn-secondary" style="padding: 4px 8px; font-size: 12px;">Edit</button>
        //         </td>
        //     `;
            
        //     tbody.insertBefore(newRow, tbody.firstChild);
        //     closeExpenseModal();
        // });

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
            // e.preventDefault();
            const expenseId = document.getElementById('editExpenseId').value;
            const amount = document.getElementById('editExpenseAmount').value;
            const category = document.getElementById('editExpenseCategory').value;
            const description = document.getElementById('editExpenseDescription').value;

            // fetch('update_expense.php', {
            //     method: 'POST',
            //     headers: {
            //         'Content-Type': 'application/x-www-form-urlencoded',
            //     },
            //     body: `id=${expenseId}&amount=${amount}&category=${category}`
            // })
            // .then(response => response.json())
            // .then(data => {
            //     if (data.success) {
            //         // Update the row in the table
            //         const row = document.querySelector(`tr[data-expense-id="${expenseId}"]`);
            //         row.querySelector('td:nth-child(3) .category-badge').textContent = category;
            //         row.querySelector('td:nth-child(4)').textContent = `₱${parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            //         closeEditModal();
            //     } else {
            //         alert('Error updating expense');
            //     }
            // })
            // .catch(error => {
            //     console.error('Error:', error);
            //     alert('Error updating expense');
            // });
        });

        // Delete expense functionality
        function deleteExpense() {
            if (!confirm('Are you sure you want to delete this expense?')) {
                return;
            }

            const expenseId = document.getElementById('editExpenseId').value;

            // fetch('delete_expense.php', {
            //     method: 'POST',
            //     headers: {
            //         'Content-Type': 'application/x-www-form-urlencoded',
            //     },
            //     body: `id=${expenseId}`
            // })
            // .then(response => response.json())
            // .then(data => {
            //     if (data.success) {
            //         const row = document.querySelector(`tr[data-expense-id="${expenseId}"]`);
            //         row.remove();
            //         closeEditModal();
            //     } else {
            //         alert('Error deleting expense');
            //     }
            // })
            // .catch(error => {
            //     console.error('Error:', error);
            //     alert('Error deleting expense');
            // });
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
    </script>
</body>
</html>