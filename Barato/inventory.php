<?php
// inventory.php - Fixed for root directory with SQL syntax correction

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
    error_log("Inventory API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
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
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE (product_name LIKE ? OR sku LIKE ? OR category LIKE ?) ORDER BY product_name");
        $stmt->execute([$search, $search, $search]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update stock status for each product
        foreach ($products as &$product) {
            $product['stock_status'] = getStockStatus($product['stock_quantity']);
        }
        
        jsonResponse($products);
    } elseif (isset($_GET['stats'])) {
        // Get inventory statistics
        $stats = [];
        
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory");
        $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total inventory value
        $stmt = $pdo->query("SELECT SUM(stock_quantity * price) as total_value FROM inventory");
        $inventoryValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'];
        $stats['inventory_value'] = $inventoryValue ?: 0;
        
        // Low stock items (less than 20)
        $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM inventory WHERE stock_quantity < 20 AND stock_quantity > 0");
        $stats['low_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'];
        
        // Out of stock items
        $stmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM inventory WHERE stock_quantity = 0");
        $stats['out_of_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock'];
        
        // Categories breakdown
        $stmt = $pdo->query("SELECT category, COUNT(*) as count, SUM(stock_quantity * price) as value FROM inventory GROUP BY category ORDER BY count DESC");
        $stats['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent activity (products added this month)
        $stmt = $pdo->query("SELECT COUNT(*) as recent FROM inventory WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent_additions'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
        
        jsonResponse($stats);
    } elseif (isset($_GET['export']) && $_GET['export'] === 'csv') {
        // Export to CSV
        exportToCSV($pdo);
    } else {
        // Get all products with pagination - FIXED: Use proper integer binding
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;
        $offset = ($page - 1) * $limit;
        
        // Use different approach for LIMIT and OFFSET to avoid binding issues
        $sql = "SELECT * FROM inventory ORDER BY product_name LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update stock status for each product
        foreach ($products as &$product) {
            $product['stock_status'] = getStockStatus($product['stock_quantity']);
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
    
    // Validate numeric fields
    if (!is_numeric($input['stock_quantity']) || $input['stock_quantity'] < 0) {
        jsonResponse(['error' => 'Stock quantity must be a non-negative number'], 400);
    }
    
    if (!is_numeric($input['price']) || $input['price'] <= 0) {
        jsonResponse(['error' => 'Price must be a positive number'], 400);
    }
    
    try {
        // Check if SKU already exists
        $stmt = $pdo->prepare("SELECT id FROM inventory WHERE sku = ?");
        $stmt->execute([$input['sku']]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'SKU already exists'], 409);
        }
        
        $stmt = $pdo->prepare("INSERT INTO inventory (product_name, sku, category, stock_quantity, price, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            sanitizeInput($input['product_name']),
            sanitizeInput($input['sku']),
            sanitizeInput($input['category']),
            (int)$input['stock_quantity'],
            (float)$input['price']
        ]);
        
        $productId = $pdo->lastInsertId();
        
        // Return the created product
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        $product['stock_status'] = getStockStatus($product['stock_quantity']);
        
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
        
        // Check if new SKU conflicts with existing products (except current one)
        if (isset($input['sku']) && $input['sku'] !== $existing['sku']) {
            $stmt = $pdo->prepare("SELECT id FROM inventory WHERE sku = ? AND id != ?");
            $stmt->execute([$input['sku'], $_GET['id']]);
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'SKU already exists'], 409);
            }
        }
        
        // Build update query dynamically
        $updates = [];
        $values = [];
        
        $allowedFields = ['product_name', 'sku', 'category', 'stock_quantity', 'price'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                // Validate numeric fields
                if ($field === 'stock_quantity') {
                    if (!is_numeric($input[$field]) || $input[$field] < 0) {
                        jsonResponse(['error' => 'Stock quantity must be a non-negative number'], 400);
                    }
                    $updates[] = "$field = ?";
                    $values[] = (int)$input[$field];
                } elseif ($field === 'price') {
                    if (!is_numeric($input[$field]) || $input[$field] <= 0) {
                        jsonResponse(['error' => 'Price must be a positive number'], 400);
                    }
                    $updates[] = "$field = ?";
                    $values[] = (float)$input[$field];
                } else {
                    $updates[] = "$field = ?";
                    $values[] = sanitizeInput($input[$field]);
                }
            }
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No valid fields to update'], 400);
        }
        
        $updates[] = "updated_at = NOW()";
        $values[] = $_GET['id'];
        
        $sql = "UPDATE inventory SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        // Return updated product
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        $product['stock_status'] = getStockStatus($product['stock_quantity']);
        
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
        // Soft delete - set status to inactive instead of actually deleting
        $stmt = $pdo->prepare("UPDATE inventory SET status = 'inactive', updated_at = NOW() WHERE id = ?");
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

function getStockStatus($quantity) {
    if ($quantity == 0) {
        return 'out_of_stock';
    } elseif ($quantity < 20) {
        return 'low_stock';
    } else {
        return 'in_stock';
    }
}

function exportToCSV($pdo) {
    try {
        $stmt = $pdo->query("SELECT product_name, sku, category, stock_quantity, price, created_at FROM inventory ORDER BY product_name");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="inventory_export_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create file pointer
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, ['Product Name', 'SKU', 'Category', 'Stock Quantity', 'Price (₱)', 'Created Date']);
        
        // Add data rows
        foreach ($products as $product) {
            fputcsv($output, [
                $product['product_name'],
                $product['sku'],
                $product['category'],
                $product['stock_quantity'],
                number_format($product['price'], 2),
                date('Y-m-d', strtotime($product['created_at']))
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Export failed: ' . $e->getMessage()], 500);
    }
}

// Seed some sample data if inventory is empty
function seedInventoryData($pdo) {
    try {
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
                ['Water Bottle Steel', 'WB-010', 'Accessories', 124, 650.00],
                ['Wireless Mouse', 'WM-011', 'Electronics', 78, 1200.00],
                ['Notebook A4', 'NB-012', 'Stationery', 200, 45.00],
                ['USB Cable Type-C', 'UC-013', 'Electronics', 95, 250.00],
                ['Coffee Mug Ceramic', 'CM-014', 'Accessories', 67, 180.00],
                ['Phone Stand', 'PS-015', 'Accessories', 143, 320.00]
            ];
            
            foreach ($sampleProducts as $product) {
                $stmt = $pdo->prepare("INSERT INTO inventory (product_name, sku, category, stock_quantity, price, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$product[0], $product[1], $product[2], $product[3], $product[4]]);
            }
        }
    } catch (Exception $e) {
        error_log("Inventory seeding error: " . $e->getMessage());
    }
}

// Seed data if needed
seedInventoryData($pdo);
?>