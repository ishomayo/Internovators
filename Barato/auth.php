<?php
// auth.php - Authentication system

require_once 'config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getConnection();

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'login':
                    handleLogin($pdo, $input);
                    break;
                case 'register':
                    handleRegister($pdo, $input);
                    break;
                case 'logout':
                    handleLogout();
                    break;
                default:
                    jsonResponse(['error' => 'Invalid action'], 400);
            }
        } else {
            jsonResponse(['error' => 'Action required'], 400);
        }
        break;
        
    case 'GET':
        // Check login status
        startSession();
        if (isLoggedIn()) {
            $user = getCurrentUser();
            jsonResponse([
                'logged_in' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            jsonResponse(['logged_in' => false]);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function handleLogin($pdo, $input) {
    $required = ['username', 'password'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input['username'], $input['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($input['password'], $user['password'])) {
            startSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            jsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            jsonResponse(['error' => 'Invalid username or password'], 401);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleRegister($pdo, $input) {
    $required = ['username', 'email', 'password', 'full_name'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    // Validate password strength
    if (strlen($input['password']) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters long'], 400);
    }
    
    try {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input['username'], $input['email']]);
        
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Username or email already exists'], 409);
        }
        
        // Create new user
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        $role = isset($input['role']) ? $input['role'] : 'employee';
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitizeInput($input['username']),
            sanitizeInput($input['email']),
            $hashedPassword,
            sanitizeInput($input['full_name']),
            $role
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Auto-login after registration
        startSession();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $input['username'];
        $_SESSION['role'] = $role;
        
        jsonResponse([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $userId,
                'username' => $input['username'],
                'email' => $input['email'],
                'full_name' => $input['full_name'],
                'role' => $role
            ]
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleLogout() {
    startSession();
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

// Create default admin user if no users exist
function createDefaultUser($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                'admin',
                'admin@businesshub.com',
                $defaultPassword,
                'System Administrator',
                'admin'
            ]);
            
            error_log("Default admin user created: admin / admin123");
        }
        
    } catch (Exception $e) {
        error_log("Error creating default user: " . $e->getMessage());
    }
}

// Create default user
createDefaultUser($pdo);
?>