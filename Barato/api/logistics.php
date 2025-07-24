<?php
// api/logistics.php - Logistics and suppliers API

require_once '../config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getConnection();

// Route different endpoints
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'suppliers';

switch ($endpoint) {
    case 'suppliers':
        handleSuppliers($pdo, $method);
        break;
    case 'products':
        handleProducts($pdo, $method);
        break;
    case 'stats':
        handleStats($pdo, $method);
        break;
    default:
        jsonResponse(['error' => 'Invalid endpoint'], 404);
}

function handleSuppliers($pdo, $method) {
    switch ($method) {
        case 'GET':
            getSuppliers($pdo);
            break;
        case 'POST':
            createSupplier($pdo);
            break;
        case 'PUT':
            updateSupplier($pdo);
            break;
        case 'DELETE':
            deleteSupplier($pdo);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getSuppliers($pdo) {
    if (isset($_GET['id'])) {
        // Get single supplier with products
        $stmt = $pdo->prepare("
            SELECT s.*, GROUP_CONCAT(rm.name) as products 
            FROM suppliers s 
            LEFT JOIN supplier_products sp ON s.id = sp.supplier_id 
            LEFT JOIN raw_materials rm ON sp.product_id = rm.id 
            WHERE s.id = ? 
            GROUP BY s.id
        ");
        $stmt->execute([$_GET['id']]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($supplier) {
            $supplier['products'] = $supplier['products'] ? explode(',', $supplier['products']) : [];
            jsonResponse($supplier);
        } else {
            jsonResponse(['error' => 'Supplier not found'], 404);
        }
        
    } elseif (isset($_GET['product_id'])) {
        // Get suppliers for a specific product
        $stmt = $pdo->prepare("
            SELECT s.*, sp.price, sp.availability 
            FROM suppliers s 
            JOIN supplier_products sp ON s.id = sp.supplier_id 
            WHERE sp.product_id = ? 
            ORDER BY s.rating DESC
        ");
        $stmt->execute([$_GET['product_id']]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($suppliers);
        
    } elseif (isset($_GET['category'])) {
        // Filter suppliers by category
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE category = ? ORDER BY rating DESC");
        $stmt->execute([$_GET['category']]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($suppliers);
        
    } elseif (isset($_GET['search'])) {
        // Search suppliers
        $search = '%' . $_GET['search'] . '%';
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE name LIKE ? OR location LIKE ? ORDER BY rating DESC");
        $stmt->execute([$search, $search]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($suppliers);
        
    } else {
        // Get all suppliers with their products
        $stmt = $pdo->query("
            SELECT s.*, GROUP_CONCAT(rm.name) as products 
            FROM suppliers s 
            LEFT JOIN supplier_products sp ON s.id = sp.supplier_id 
            LEFT JOIN raw_materials rm ON sp.product_id = rm.id 
            GROUP BY s.id 
            ORDER BY s.rating DESC
        ");
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert products string to array for each supplier
        foreach ($suppliers as &$supplier) {
            $supplier['products'] = $supplier['products'] ? explode(',', $supplier['products']) : [];
        }
        
        jsonResponse($suppliers);
    }
}

function createSupplier($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    $required = ['name', 'category', 'location', 'phone', 'email'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    // Validate category
    $validCategories = ['food', 'electronics', 'textile', 'chemicals', 'metals'];
    if (!in_array($input['category'], $validCategories)) {
        jsonResponse(['error' => 'Invalid category'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, category, location, phone, email, rating, lead_time, min_order_amount, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['category']),
            sanitizeInput($input['location']),
            sanitizeInput($input['phone']),
            sanitizeInput($input['email']),
            isset($input['rating']) ? (float)$input['rating'] : 0,
            isset($input['lead_time']) ? sanitizeInput($input['lead_time']) : null,
            isset($input['min_order_amount']) ? (float)$input['min_order_amount'] : null,
            isset($input['latitude']) ? (float)$input['latitude'] : null,
            isset($input['longitude']) ? (float)$input['longitude'] : null
        ]);
        
        $supplierId = $pdo->lastInsertId();
        
        // Return the created supplier
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($supplier, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function updateSupplier($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Supplier ID required'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    try {
        // Check if supplier exists
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            jsonResponse(['error' => 'Supplier not found'], 404);
        }
        
        // Build update query dynamically
        $updates = [];
        $values = [];
        
        $allowedFields = ['name', 'category', 'location', 'phone', 'email', 'rating', 'lead_time', 'min_order_amount', 'latitude', 'longitude'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                if (in_array($field, ['rating', 'min_order_amount', 'latitude', 'longitude'])) {
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
            $validCategories = ['food', 'electronics', 'textile', 'chemicals', 'metals'];
            if (!in_array($input['category'], $validCategories)) {
                jsonResponse(['error' => 'Invalid category'], 400);
            }
        }
        
        $values[] = $_GET['id'];
        
        $sql = "UPDATE suppliers SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        // Return updated supplier
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($supplier);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function deleteSupplier($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Supplier ID required'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete supplier products first
        $stmt = $pdo->prepare("DELETE FROM supplier_products WHERE supplier_id = ?");
        $stmt->execute([$_GET['id']]);
        
        // Delete supplier
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $result = $stmt->execute([$_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            jsonResponse(['message' => 'Supplier deleted successfully']);
        } else {
            $pdo->rollBack();
            jsonResponse(['error' => 'Supplier not found'], 404);
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleProducts($pdo, $method) {
    switch ($method) {
        case 'GET':
            getProducts($pdo);
            break;
        case 'POST':
            createProduct($pdo);
            break;
        case 'PUT':
            updateProduct($pdo);
            break;
        case 'DELETE':
            deleteProduct($pdo);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getProducts($pdo) {
    if (isset($_GET['search'])) {
        // Search products
        $search = '%' . $_GET['search'] . '%';
        $stmt = $pdo->prepare("
            SELECT rm.*, COUNT(sp.supplier_id) as supplier_count 
            FROM raw_materials rm 
            LEFT JOIN supplier_products sp ON rm.id = sp.product_id 
            WHERE rm.name LIKE ? OR rm.category LIKE ? 
            GROUP BY rm.id 
            ORDER BY rm.name
        ");
        $stmt->execute([$search, $search]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($products);
        
    } elseif (isset($_GET['category'])) {
        // Filter by category
        $stmt = $pdo->prepare("
            SELECT rm.*, COUNT(sp.supplier_id) as supplier_count 
            FROM raw_materials rm 
            LEFT JOIN supplier_products sp ON rm.id = sp.product_id 
            WHERE rm.category = ? 
            GROUP BY rm.id 
            ORDER BY rm.name
        ");
        $stmt->execute([$_GET['category']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($products);
        
    } else {
        // Get all products with supplier count
        $stmt = $pdo->query("
            SELECT rm.*, COUNT(sp.supplier_id) as supplier_count 
            FROM raw_materials rm 
            LEFT JOIN supplier_products sp ON rm.id = sp.product_id 
            GROUP BY rm.id 
            ORDER BY rm.name
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($products);
    }
}

function createProduct($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    $required = ['name', 'category'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO raw_materials (name, category, description) VALUES (?, ?, ?)");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['category']),
            isset($input['description']) ? sanitizeInput($input['description']) : null
        ]);
        
        $productId = $pdo->lastInsertId();
        
        // Return the created product
        $stmt = $pdo->prepare("SELECT * FROM raw_materials WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($product, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleStats($pdo, $method) {
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $stats = [];
    
    // Total suppliers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers");
    $stats['active_suppliers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Product categories
    $stmt = $pdo->query("SELECT COUNT(DISTINCT category) as count FROM raw_materials");
    $stats['product_categories'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // On-time delivery (mock data)
    $stats['on_time_delivery'] = 85;
    
    // Monthly orders (mock data)
    $stats['monthly_orders'] = 2400000;
    
    // Suppliers by category
    $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM suppliers GROUP BY category");
    $stats['suppliers_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top rated suppliers
    $stmt = $pdo->query("SELECT name, rating, category FROM suppliers ORDER BY rating DESC LIMIT 5");
    $stats['top_suppliers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Average ratings by category
    $stmt = $pdo->query("SELECT category, AVG(rating) as avg_rating FROM suppliers GROUP BY category");
    $stats['avg_ratings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($stats);
}

// Contact supplier function
function contactSupplier($pdo, $supplierId, $message) {
    try {
        // In a real application, this would send an email or create a communication record
        $stmt = $pdo->prepare("SELECT name, email, phone FROM suppliers WHERE id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            return ['error' => 'Supplier not found'];
        }
        
        // Log the contact attempt
        error_log("Contact attempt - Supplier: {$supplier['name']}, Email: {$supplier['email']}, Message: $message");
        
        return [
            'success' => true,
            'message' => "Contact initiated with {$supplier['name']}",
            'supplier' => $supplier
        ];
        
    } catch (PDOException $e) {
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

// Handle contact requests
if (isset($_GET['action']) && $_GET['action'] === 'contact' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['supplier_id']) || !isset($input['message'])) {
        jsonResponse(['error' => 'Supplier ID and message are required'], 400);
    }
    
    $result = contactSupplier($pdo, $input['supplier_id'], $input['message']);
    
    if (isset($result['error'])) {
        jsonResponse($result, 400);
    } else {
        jsonResponse($result);
    }
    exit;
}

// Seed sample logistics data
function seedLogisticsData($pdo) {
    // Check if raw materials exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM raw_materials");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        // Seed raw materials
        $materials = [
            ['Cotton Fabric', 'textile', 'High-quality cotton fabric for garment manufacturing'],
            ['Steel Sheets', 'metals', 'Industrial grade steel sheets for construction'],
            ['Electronic Components', 'electronics', 'Various electronic components and semiconductors'],
            ['Wheat Flour', 'food', 'Premium grade wheat flour for food processing'],
            ['Plastic Pellets', 'chemicals', 'Raw plastic pellets for injection molding'],
            ['Aluminum Sheets', 'metals', 'Lightweight aluminum sheets for various applications'],
            ['Silicon Chips', 'electronics', 'High-performance silicon chips and processors'],
            ['Organic Coconut Oil', 'food', 'Cold-pressed organic coconut oil'],
            ['Polyester Yarn', 'textile', 'Durable polyester yarn for textile production'],
            ['Chemical Solvents', 'chemicals', 'Industrial grade chemical solvents']
        ];
        
        $materialIds = [];
        foreach ($materials as $material) {
            $stmt = $pdo->prepare("INSERT INTO raw_materials (name, category, description) VALUES (?, ?, ?)");
            $stmt->execute($material);
            $materialIds[] = $pdo->lastInsertId();
        }
        
        // Seed suppliers
        $suppliers = [
            ['Manila Textile Co.', 'textile', 'Manila, Philippines', '+63 2 123 4567', 'contact@manilatextile.com', 4.8, '5-7 days', 50000, 14.5995, 120.9842],
            ['Philippine Steel Corp.', 'metals', 'Batangas, Philippines', '+63 43 456 7890', 'sales@philsteel.com', 4.9, '10-14 days', 100000, 13.7565, 121.0583],
            ['TechSource Manila', 'electronics', 'Makati, Philippines', '+63 2 789 0123', 'info@techsource.ph', 4.7, '3-5 days', 25000, 14.5547, 121.0244],
            ['Golden Wheat Mills', 'food', 'Bulacan, Philippines', '+63 44 234 5678', 'orders@goldenwheat.ph', 4.6, '2-3 days', 30000, 14.7942, 120.8794],
            ['Polymer Industries', 'chemicals', 'Laguna, Philippines', '+63 49 345 6789', 'sales@polymerindustries.com', 4.5, '7-10 days', 75000, 14.2691, 121.1074],
            ['Cebu Cotton Mills', 'textile', 'Cebu, Philippines', '+63 32 456 7890', 'info@cebucotton.com', 4.9, '6-8 days', 40000, 10.3157, 123.8854],
            ['Semiconductor PH', 'electronics', 'Clark, Pampanga', '+63 45 567 8901', 'contact@semiconductorph.com', 4.8, '4-6 days', 80000, 15.1855, 120.5430],
            ['Tropical Oils Inc.', 'food', 'Davao, Philippines', '+63 82 678 9012', 'sales@tropicaloils.ph', 4.7, '5-7 days', 35000, 7.1907, 125.4553],
            ['Metro Steel Industries', 'metals', 'Quezon City, Philippines', '+63 2 789 0123', 'info@metrosteel.com', 4.6, '8-12 days', 90000, 14.6760, 121.0437],
            ['ChemTech Philippines', 'chemicals', 'Cavite, Philippines', '+63 46 890 1234', 'orders@chemtechph.com', 4.4, '6-9 days', 60000, 14.4791, 120.8970]
        ];
        
        $supplierIds = [];
        foreach ($suppliers as $supplier) {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, category, location, phone, email, rating, lead_time, min_order_amount, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($supplier);
            $supplierIds[] = $pdo->lastInsertId();
        }
        
        // Create supplier-product relationships
        $relationships = [
            // Manila Textile Co. - Cotton Fabric, Polyester Yarn
            [$supplierIds[0], $materialIds[0], 45.00, 'available'],
            [$supplierIds[0], $materialIds[8], 38.50, 'available'],
            
            // Philippine Steel Corp. - Steel Sheets, Aluminum Sheets  
            [$supplierIds[1], $materialIds[1], 125.00, 'available'],
            [$supplierIds[1], $materialIds[5], 95.00, 'available'],
            
            // TechSource Manila - Electronic Components, Silicon Chips
            [$supplierIds[2], $materialIds[2], 15.75, 'available'],
            [$supplierIds[2], $materialIds[6], 285.00, 'limited'],
            
            // Golden Wheat Mills - Wheat Flour
            [$supplierIds[3], $materialIds[3], 28.50, 'available'],
            
            // Polymer Industries - Plastic Pellets, Chemical Solvents
            [$supplierIds[4], $materialIds[4], 65.00, 'available'],
            [$supplierIds[4], $materialIds[9], 145.00, 'available'],
            
            // Cebu Cotton Mills - Cotton Fabric
            [$supplierIds[5], $materialIds[0], 42.00, 'available'],
            
            // Semiconductor PH - Silicon Chips
            [$supplierIds[6], $materialIds[6], 295.00, 'available'],
            
            // Tropical Oils Inc. - Organic Coconut Oil
            [$supplierIds[7], $materialIds[7], 85.00, 'available'],
            
            // Metro Steel Industries - Steel Sheets
            [$supplierIds[8], $materialIds[1], 128.00, 'available'],
            
            // ChemTech Philippines - Chemical Solvents
            [$supplierIds[9], $materialIds[9], 150.00, 'available']
        ];
        
        foreach ($relationships as $rel) {
            $stmt = $pdo->prepare("INSERT INTO supplier_products (supplier_id, product_id, price, availability) VALUES (?, ?, ?, ?)");
            $stmt->execute($rel);
        }
    }
}

// Seed data if needed
try {
    seedLogisticsData($pdo);
} catch (Exception $e) {
    error_log("Logistics seeding error: " . $e->getMessage());
}
?>