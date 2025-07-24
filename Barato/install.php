<?php
// install.php - Installation and setup script

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Hub - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }
        
        .logo {
            background: #4f46e5;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            margin: 0 auto 2rem;
        }
        
        h1 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }
        
        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 2rem;
        }
        
        .step {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }
        
        .step h3 {
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .step-number {
            background: #4f46e5;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .status {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 1rem;
        }
        
        .status.success {
            background: #dcfce7;
            color: #166534;
        }
        
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .btn {
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }
        
        .btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .info-box h4 {
            color: #1e40af;
            margin-bottom: 0.5rem;
        }
        
        .info-box p {
            color: #1e40af;
            font-size: 14px;
            margin: 0.25rem 0;
        }
        
        .code {
            background: #f1f5f9;
            padding: 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            margin: 0.5rem 0;
        }
        
        .file-list {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .file-list h4 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .file-list ul {
            list-style-type: none;
            margin-left: 1rem;
        }
        
        .file-list li {
            color: #64748b;
            font-size: 14px;
            margin: 0.25rem 0;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">B</div>
        <h1>Business Hub Installation</h1>
        <p class="subtitle">Welcome to Business Hub setup. Let's get your system ready!</p>
        
        <?php
        $installationComplete = false;
        $errors = [];
        $warnings = [];
        
        // Step 1: Check PHP requirements
        echo '<div class="step">';
        echo '<h3><span class="step-number">1</span>System Requirements</h3>';
        
        $phpVersion = PHP_VERSION;
        $requiredVersion = '7.4.0';
        
        if (version_compare($phpVersion, $requiredVersion, '>=')) {
            echo '<div class="status success">✅ PHP ' . $phpVersion . ' (Required: ' . $requiredVersion . '+)</div>';
        } else {
            echo '<div class="status error">❌ PHP ' . $phpVersion . ' (Required: ' . $requiredVersion . '+)</div>';
            $errors[] = 'PHP version ' . $requiredVersion . ' or higher is required';
        }
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                echo '<div class="status success">✅ ' . $ext . ' extension loaded</div>';
            } else {
                echo '<div class="status error">❌ ' . $ext . ' extension missing</div>';
                $errors[] = $ext . ' extension is required';
            }
        }
        
        echo '</div>';
        
        // Step 2: Database Connection
        echo '<div class="step">';
        echo '<h3><span class="step-number">2</span>Database Setup</h3>';
        
        try {
            // Try to include config and connect
            if (file_exists('config.php')) {
                require_once 'config.php';
                $pdo = getConnection();
                echo '<div class="status success">✅ Database connection successful</div>';
                echo '<div class="status success">✅ Database tables initialized</div>';
                
                // Check if sample data exists
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory");
                $inventoryCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($inventoryCount > 0) {
                    echo '<div class="status success">✅ Sample data loaded (' . $inventoryCount . ' inventory items)</div>';
                } else {
                    echo '<div class="status warning">⚠️ No sample data found</div>';
                    $warnings[] = 'Sample data not loaded';
                }
                
            } else {
                echo '<div class="status error">❌ config.php file not found</div>';
                $errors[] = 'Database configuration file missing';
            }
        } catch (Exception $e) {
            echo '<div class="status error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $errors[] = 'Database connection failed';
        }
        
        echo '</div>';
        
        // Step 3: File Permissions
        echo '<div class="step">';
        echo '<h3><span class="step-number">3</span>File Permissions</h3>';
        
        $writableDirs = ['uploads', 'logs', 'cache'];
        foreach ($writableDirs as $dir) {
            if (!file_exists($dir)) {
                if (mkdir($dir, 0755, true)) {
                    echo '<div class="status success">✅ Created directory: ' . $dir . '</div>';
                } else {
                    echo '<div class="status error">❌ Could not create directory: ' . $dir . '</div>';
                    $errors[] = 'Cannot create ' . $dir . ' directory';
                }
            }
            
            if (is_writable($dir)) {
                echo '<div class="status success">✅ ' . $dir . '/ is writable</div>';
            } else {
                echo '<div class="status error">❌ ' . $dir . '/ is not writable</div>';
                $errors[] = $dir . ' directory is not writable';
            }
        }
        
        echo '</div>';
        
        // Step 4: API Endpoints
        echo '<div class="step">';
        echo '<h3><span class="step-number">4</span>API Endpoints</h3>';
        
        $apiEndpoints = [
            'api/dashboard.php' => 'Dashboard API',
            'api/inventory.php' => 'Inventory API',
            'api/payroll.php' => 'Payroll API',
            'api/expenses.php' => 'Expenses API',
            'api/support.php' => 'Support API',
            'api/logistics.php' => 'Logistics API'
        ];
        
        foreach ($apiEndpoints as $file => $name) {
            if (file_exists($file)) {
                echo '<div class="status success">✅ ' . $name . ' available</div>';
            } else {
                echo '<div class="status error">❌ ' . $name . ' missing</div>';
                $errors[] = $name . ' file not found';
            }
        }
        
        echo '</div>';
        
        // Step 5: Default User
        echo '<div class="step">';
        echo '<h3><span class="step-number">5</span>Default Administrator</h3>';
        
        try {
            if (isset($pdo)) {
                $stmt = $pdo->query("SELECT username, email FROM users WHERE role = 'admin' LIMIT 1");
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($admin) {
                    echo '<div class="status success">✅ Admin user exists: ' . htmlspecialchars($admin['username']) . '</div>';
                    echo '<div class="info-box">';
                    echo '<h4>Default Login Credentials:</h4>';
                    echo '<p><strong>Username:</strong> ' . htmlspecialchars($admin['username']) . '</p>';
                    echo '<p><strong>Email:</strong> ' . htmlspecialchars($admin['email']) . '</p>';
                    echo '<p><strong>Password:</strong> admin123 (Please change after first login)</p>';
                    echo '</div>';
                } else {
                    echo '<div class="status error">❌ No admin user found</div>';
                    $errors[] = 'Default admin user not created';
                }
            }
        } catch (Exception $e) {
            echo '<div class="status error">❌ Could not check admin user: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        echo '</div>';
        
        // Installation Summary
        echo '<div class="step">';
        echo '<h3><span class="step-number">6</span>Installation Summary</h3>';
        
        if (empty($errors)) {
            echo '<div class="status success">🎉 Installation completed successfully!</div>';
            echo '<p>Your Business Hub system is ready to use.</p>';
            echo '<a href="landing.html" class="btn">Launch Business Hub</a>';
            $installationComplete = true;
        } else {
            echo '<div class="status error">❌ Installation incomplete</div>';
            echo '<p><strong>Errors that need to be fixed:</strong></p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li style="color: #991b1b; margin: 0.25rem 0;">' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
        }
        
        if (!empty($warnings)) {
            echo '<div class="status warning">⚠️ Warnings (optional fixes):</div>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li style="color: #92400e; margin: 0.25rem 0;">' . htmlspecialchars($warning) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
        ?>
        
        <!-- File Structure Information -->
        <div class="step">
            <h3><span class="step-number">📁</span>Required File Structure</h3>
            <p>Make sure your files are organized as follows:</p>
            
            <div class="file-list">
                <h4>Frontend Files:</h4>
                <ul>
                    <li>📄 landing.html (Main dashboard)</li>
                    <li>📄 inventory.html</li>
                    <li>📄 payroll.html</li>
                    <li>📄 expenses.html</li>
                    <li>📄 support.html</li>
                    <li>📄 logistics.html</li>
                </ul>
            </div>
            
            <div class="file-list">
                <h4>Backend Files:</h4>
                <ul>
                    <li>📄 config.php</li>
                    <li>📄 auth.php</li>
                    <li>📁 api/</li>
                    <li>&nbsp;&nbsp;📄 dashboard.php</li>
                    <li>&nbsp;&nbsp;📄 inventory.php</li>
                    <li>&nbsp;&nbsp;📄 payroll.php</li>
                    <li>&nbsp;&nbsp;📄 expenses.php</li>
                    <li>&nbsp;&nbsp;📄 support.php</li>
                    <li>&nbsp;&nbsp;📄 logistics.php</li>
                </ul>
            </div>
        </div>
        
        <!-- Database Configuration Help -->
        <div class="step">
            <h3><span class="step-number">🔧</span>Database Configuration</h3>
            <p>If you need to configure the database connection, edit <code>config.php</code>:</p>
            
            <div class="code">
define('DB_HOST', 'localhost');<br>
define('DB_NAME', 'business_hub');<br>
define('DB_USER', 'your_username');<br>
define('DB_PASS', 'your_password');
            </div>
            
            <div class="info-box">
                <h4>Database Setup Steps:</h4>
                <p>1. Create a MySQL database named 'business_hub'</p>
                <p>2. Update the database credentials in config.php</p>
                <p>3. The tables will be created automatically</p>
                <p>4. Sample data will be populated on first run</p>
            </div>
        </div>
        
        <?php if ($installationComplete): ?>
        <div class="step">
            <h3><span class="step-number">🚀</span>Next Steps</h3>
            <p>Your Business Hub is now ready! Here's what you can do:</p>
            <ul style="margin: 1rem 0; padding-left: 2rem;">
                <li>Log in with the admin credentials above</li>
                <li>Explore the dashboard and different modules</li>
                <li>Add your own data and customize settings</li>
                <li>Test the chatbot and knowledge base features</li>
                <li>Configure logistics suppliers and products</li>
            </ul>
            
            <div class="info-box">
                <h4>Important Security Note:</h4>
                <p>Remember to change the default admin password after your first login!</p>
                <p>Consider deleting this install.php file after installation is complete.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>