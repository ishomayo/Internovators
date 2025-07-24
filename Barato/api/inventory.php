<?php
// api/inventory.php - Inventory management API

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
    if (isset($_GET['id'])) {
        // Get single product
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            jsonResponse($product);
        } else {
            jsonResponse(['error' => 'Product not found'], 404);
        }
    } elseif (isset($_GET['search'])) {
        // Search products
        $search = '%' . $_GET['search'] . '%';
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE product_name LIKE ? OR sku LIKE ? OR category LIKE ? ORDER BY product_name");
        $stmt->execute([$search, $search, $search]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($products);
    } elseif (isset($_GET['stats'])) {
        // Get inventory statistics
        $stats = [];
        
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory");
        $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total inventory value
        $stmt = $pdo->query("SELECT SUM(stock_quantity * price) as total_value FROM inventory");
        $stats['inventory_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'];
        
        // Low stock items
        $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM inventory WHERE stock_quantity < 20");
        $stats['low_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'];
        
        // Categories
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM inventory GROUP BY category");
        $stats['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse($stats);
    } else {
        // Get all products
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        $stmt = $pdo->prepare("SELECT * FROM inventory ORDER BY product_name LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update stock status based on quantity
        foreach ($products as &$product) {
            if ($product['stock_quantity'] == 0) {
                $product['status'] = 'out_of_stock';
            } elseif ($product['stock_quantity'] < 20) {
                $product['status'] = 'low_stock';
            } else {
                $product['status'] = 'in_stock';
            }
        }
        
        jsonResponse($products);
    }
}

function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    $required = ['product_name', 'sku', 'category', 'stock_quantity', 'price'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    try {
        // Check if SKU already exists
        $stmt = $pdo->prepare("SELECT id FROM inventory WHERE sku = ?");
        $stmt->execute([$input['sku']]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'SKU already exists'], 409);
        }
        
        // Determine status based on quantity
        $status = 'in_stock';
        if ($input['stock_quantity'] == 0) {
            $status = 'out_of_stock';
        } elseif ($input['stock_quantity'] < 20) {
            $status = 'low_stock';
        }
        
        $stmt = $pdo->prepare("INSERT INTO inventory (product_name, sku, category, stock_quantity, price, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitizeInput($input['product_name']),
            sanitizeInput($input['sku']),
            sanitizeInput($input['category']),
            (int)$input['stock_quantity'],
            (float)$input['price'],
            $status
        ]);
        
        $productId = $pdo->lastInsertId();
        
        // Return the created product
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($product, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handlePut($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Product ID required'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    try {
        // Check if product exists
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            jsonResponse(['error' => 'Product not found'], 404);
        }
        
        // Build update query dynamically
        $updates = [];
        $values = [];
        
        $allowedFields = ['product_name', 'sku', 'category', 'stock_quantity', 'price'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                if ($field === 'stock_quantity') {
                    $values[] = (int)$input[$field];
                } elseif ($field === 'price') {
                    $values[] = (float)$input[$field];
                } else {
                    $values[] = sanitizeInput($input[$field]);
                }
            }
        }
        
        // Update status based on quantity if quantity was updated
        if (isset($input['stock_quantity'])) {
            $status = 'in_stock';
            if ($input['stock_quantity'] == 0) {
                $status = 'out_of_stock';
            } elseif ($input['stock_quantity'] < 20) {
                $status = 'low_stock';
            }
            $updates[] = "status = ?";
            $values[] = $status;
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No valid fields to update'], 400);
        }
        
        $values[] = $_GET['id'];
        
        $sql = "UPDATE inventory SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        // Return updated product
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($product);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Product ID required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $result = $stmt->execute([$_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['message' => 'Product deleted successfully']);
        } else {
            jsonResponse(['error' => 'Product not found'], 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Seed some sample data if inventory is empty
function seedInventoryData($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $sampleProducts = [
            ['Wireless Headphones', 'WH-001', 'Electronics', 45, 2500.00],
            ['Coffee Beans Premium', 'CB-002', 'Food & Beverage', 12, 850.00],
            ['Office Chair Ergonomic', 'OC-003', 'Furniture', 0, 8500.00],
            ['Smartphone Case', 'SC-004', 'Accessories', 156, 450.00],
            ['Desk Lamp LED', 'DL-005', 'Lighting', 23, 1200.00],
            ['Bluetooth Speaker', 'BS-006', 'Electronics', 67, 3200.00],
            ['Organic Tea Bags', 'OT-007', 'Food & Beverage', 89, 320.00],
            ['Laptop Stand', 'LS-008', 'Accessories', 34, 1850.00],
            ['Mechanical Keyboard', 'MK-009', 'Electronics', 18, 4500.00],
            ['Water Bottle Steel', 'WB-010', 'Accessories', 124, 650.00]
        ];
        
        foreach ($sampleProducts as $product) {
            $status = 'in_stock';
            if ($product[3] == 0) {
                $status = 'out_of_stock';
            } elseif ($product[3] < 20) {
                $status = 'low_stock';
            }
            
            $stmt = $pdo->prepare("INSERT INTO inventory (product_name, sku, category, stock_quantity, price, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product[0], $product[1], $product[2], $product[3], $product[4], $status]);
        }
    }
}

// Seed data if needed
try {
    seedInventoryData($pdo);
} catch (Exception $e) {
    error_log("Inventory seeding error: " . $e->getMessage());
}
?>