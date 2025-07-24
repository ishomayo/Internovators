<?php
// api/support.php - Support tickets and chat API

require_once '../config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getConnection();

// Route different endpoints
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'tickets';

switch ($endpoint) {
    case 'tickets':
        handleTickets($pdo, $method);
        break;
    case 'chat':
        handleChat($pdo, $method);
        break;
    case 'knowledge':
        handleKnowledgeBase($pdo, $method);
        break;
    default:
        jsonResponse(['error' => 'Invalid endpoint'], 404);
}

function handleTickets($pdo, $method) {
    switch ($method) {
        case 'GET':
            getTickets($pdo);
            break;
        case 'POST':
            createTicket($pdo);
            break;
        case 'PUT':
            updateTicket($pdo);
            break;
        case 'DELETE':
            deleteTicket($pdo);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getTickets($pdo) {
    if (isset($_GET['stats'])) {
        // Get support statistics
        $stats = [];
        
        // Open tickets
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
        $stats['open_tickets'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Average response time (mock data)
        $stats['avg_response_time'] = '< 2hrs';
        $stats['satisfaction_rate'] = '95%';
        
        // Tickets by status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM support_tickets GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Tickets by priority
        $stmt = $pdo->query("SELECT priority, COUNT(*) as count FROM support_tickets GROUP BY priority");
        $stats['by_priority'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse($stats);
        
    } elseif (isset($_GET['id'])) {
        // Get single ticket
        $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ticket) {
            jsonResponse($ticket);
        } else {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        
    } else {
        // Get all tickets
        $stmt = $pdo->query("SELECT * FROM support_tickets ORDER BY created_at DESC");
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($tickets);
    }
}

function createTicket($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    $required = ['subject', 'description'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    try {
        // Generate ticket ID
        $ticketId = 'T' . str_pad(rand(1, 9999), 3, '0', STR_PAD_LEFT);
        
        // Check if ticket ID already exists
        $stmt = $pdo->prepare("SELECT id FROM support_tickets WHERE ticket_id = ?");
        $stmt->execute([$ticketId]);
        while ($stmt->fetch()) {
            $ticketId = 'T' . str_pad(rand(1, 9999), 3, '0', STR_PAD_LEFT);
            $stmt->execute([$ticketId]);
        }
        
        $priority = isset($input['priority']) ? $input['priority'] : 'medium';
        $validPriorities = ['low', 'medium', 'high'];
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        
        $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_id, subject, description, priority, status, created_by) VALUES (?, ?, ?, ?, 'open', 1)");
        $stmt->execute([
            $ticketId,
            sanitizeInput($input['subject']),
            sanitizeInput($input['description']),
            $priority
        ]);
        
        $id = $pdo->lastInsertId();
        
        // Return the created ticket
        $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($ticket, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function updateTicket($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Ticket ID required'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    try {
        // Check if ticket exists
        $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        
        // Build update query dynamically
        $updates = [];
        $values = [];
        
        $allowedFields = ['subject', 'description', 'priority', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $values[] = sanitizeInput($input[$field]);
            }
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No valid fields to update'], 400);
        }
        
        // Validate priority if provided
        if (isset($input['priority'])) {
            $validPriorities = ['low', 'medium', 'high'];
            if (!in_array($input['priority'], $validPriorities)) {
                jsonResponse(['error' => 'Invalid priority'], 400);
            }
        }
        
        // Validate status if provided
        if (isset($input['status'])) {
            $validStatuses = ['open', 'pending', 'resolved', 'closed'];
            if (!in_array($input['status'], $validStatuses)) {
                jsonResponse(['error' => 'Invalid status'], 400);
            }
        }
        
        $values[] = $_GET['id'];
        
        $sql = "UPDATE support_tickets SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        // Return updated ticket
        $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($ticket);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function deleteTicket($pdo) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Ticket ID required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE id = ?");
        $result = $stmt->execute([$_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['message' => 'Ticket deleted successfully']);
        } else {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handleChat($pdo, $method) {
    switch ($method) {
        case 'GET':
            getChatHistory($pdo);
            break;
        case 'POST':
            processChatMessage($pdo);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getChatHistory($pdo) {
    $sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : 'default';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$sessionId, $limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reverse to show oldest first
    $messages = array_reverse($messages);
    
    jsonResponse($messages);
}

function processChatMessage($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['message'])) {
        jsonResponse(['error' => 'Message is required'], 400);
    }
    
    $message = sanitizeInput($input['message']);
    $sessionId = isset($input['session_id']) ? $input['session_id'] : 'default';
    $userId = 1; // Default user, should come from session
    
    try {
        // Save user message
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, session_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $message, $sessionId]);
        $messageId = $pdo->lastInsertId();
        
        // Generate bot response
        $response = generateChatResponse($pdo, $message);
        
        // Update message with response
        $stmt = $pdo->prepare("UPDATE chat_messages SET response = ? WHERE id = ?");
        $stmt->execute([$response, $messageId]);
        
        // Return the complete message
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $chatMessage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($chatMessage, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function generateChatResponse($pdo, $message) {
    $lowerMessage = strtolower($message);
    
    // First try to find answer in knowledge base
    $knowledgeResponse = searchKnowledgeBase($pdo, $message);
    if ($knowledgeResponse) {
        return "📚 From Knowledge Base:\n\n" . $knowledgeResponse['answer'] . "\n\n💡 This answer was found in our " . $knowledgeResponse['category'] . " documentation.";
    }
    
    // Predefined responses
    $responses = [
        'payroll' => 'I can help you with payroll processing! Here are the main steps:\n\n1. Go to the Payroll section from your dashboard\n2. Click "Run Payroll" to start processing\n3. Review employee hours and deductions\n4. Approve and process payments\n\nIf you\'re experiencing specific issues, let me know what error messages you\'re seeing.',
        
        'inventory' => 'For inventory management issues, let\'s troubleshoot step by step:\n\n1. First, try refreshing your browser\n2. Check if you\'ve recently imported data\n3. Verify your stock levels in the Inventory section\n\nIf numbers still look incorrect, I can help you run a stock audit. What specific discrepancies are you seeing?',
        
        'expense' => 'To manage your expenses:\n\n1. Navigate to the Expense Tracker\n2. Use the filter options to select your date range\n3. Click "Add Expense" to record new expenses\n4. Use "Export" to download reports\n\nNeed help with a specific expense-related task?',
        
        'support' => 'I\'m here to provide support! You can:\n\n• Create support tickets for technical issues\n• Search our knowledge base for quick answers\n• Contact our team directly\n• Schedule a call with our specialists\n\nWhat type of support do you need today?',
        
        'logistics' => 'Our logistics dashboard helps you:\n\n• Find suppliers for raw materials\n• View supplier locations on the map\n• Compare supplier ratings and pricing\n• Contact suppliers directly\n\nWhat specific logistics information are you looking for?',
        
        'hello' => 'Hello! Welcome to your business dashboard. I can see your business is performing well. How can I help you today?\n\nI can assist with:\n• Dashboard navigation\n• Business metrics analysis\n• Technical support\n• Feature explanations',
        
        'help' => 'I\'m here to provide comprehensive support for all aspects of your business platform:\n\n📊 Dashboard & Analytics\n💰 Payroll Processing\n📦 Inventory Management\n📈 Expense Tracking\n🚚 Logistics & Suppliers\n🔧 Technical Support\n\nJust describe what you need help with, and I\'ll provide detailed guidance!',
        'sourcing_materials' => 'Hello ka-SangkAI! 🚀 Maari ko bang malaman kung saan ang iyong negosyo? [Chatbot should prompt for user\’s location] 😀\n\nKapag nasabi mo na ang iyong lokasyon, narito ang ilang mga suppliers o tindahan kung saan ka makakabili ng [name of raw materials]:\n📍 [Nearby Supplier 1 with link]\n📍 [Nearby Supplier 2 with link]\n📍 [Online Marketplaces like Lazada, Shopee, or Facebook Marketplace]\n\n💡 Tip: Pwede ka ring sumali sa mga FB Groups ng local entrepreneurs para magtanong ng direct suppliers sa inyong lugar.',

'sales_optimization' => 'Hello ka-SangkAI! 🚀 Ito ang mga maaari mong gawin para maimprove ang iyong sales:\n\n✅ Mag-offer ng promos o bundles\n✅ Gumamit ng social media marketing\n✅ Gumamit ng customer feedback para i-refine ang produkto o serbisyo\n✅ I-post ang customer reviews to build trust\n\nGusto mo bang gumawa tayo ng sales calendar together? 😃',

'inventory_management' => 'Hello ka-SangkAI! 🚀 Para mapamahalaan nang mas maayos ang iyong inventory:\n\n📦 Gumamit ng inventory system tulad ng spreadsheet, Loyverse, o SalesBinder\n📊 I-categorize ang stocks: fast-moving, slow-moving, at seasonal\n🔁 Gamitin ang First-In First-Out (FIFO) method\n📉 Iwasan ang overstock at i-audit monthly\n\nPwede kitang bigyan ng free inventory tracker template! 😃',

'payslip_online' => 'Hello Ka-SangkAI! 🚀 Depende ito sa system na gamit mo:\n\n💼 Kung manual pa, kailangang ikaw ang magbigay ng payslip\n💻 Kung automated (Sprout, Salarium, GCash Payroll), may portal ang staff para makita ang payslip nila\n\nGusto mo bang i-check kung may self-service feature ang payroll system mo?',

'business_consultation' => 'Hello ka-SangkAI! 🚀 Maraming libreng consultation services para sa mga MSMEs! Narito ang mga pwede mong lapitan:\n\n🧠 DTI Negosyo Center – business counselors for strategy & compliance\n🌐 Go Negosyo – mentorship programs for entrepreneurs\n💼 PhilDev, StartUp PH – para sa tech or innovative businesses\n📱 FB groups like "Online Negosyo PH" for peer advice\n\nGusto mo bang hanapan kita ng pinakamalapit na DTI Negosyo Center?',

'online_presence' => 'Hello ka-SangkAI! 🚀 Pwede mong gawin ito para lumakas ang online presence ng business mo:\n\n🌐 Gumawa ng FB Page at Google Business Profile\n📸 Mag-post ng high-quality photos ng produkto mo\n📅 Maging consistent sa posting schedule (kahit 3x/week)\n📈 Gumamit ng trending hashtags at sumali sa seller groups\n🎁 Mag-offer ng promos or giveaways for engagement\n\nPwede kitang bigyan ng content calendar template kung gusto mo!',

'customer_complaint' => 'Hello ka-SangkAI! 🚀 Ito ang tamang gawin kapag may reklamo ang customer:\n\n👂 Pakinggan ang concern nang buo at may respeto\n🙏 Magpakita ng empathy, kahit hindi ikaw ang may pagkukulang\n🔧 Magbigay ng mabilis at malinaw na solusyon\n📝 I-record ang complaint para ma-review sa future\n\nGusto mo bang gumawa tayo ng simple complaint tracker?',

'pricing_strategy' => 'Hello ka-SangkAI! 🚀 Narito ang steps para siguraduhing tama ang presyo mo:\n\n🧮 I-compute ang total cost (materials, labor, overhead)\n📈 Magdagdag ng profit margin (20–40%)\n📊 I-compare sa competitors para sa competitive edge\n📦 Magdagdag ng value—hal. free delivery o eco-packaging\n\nGusto mo ba ng pricing calculator worksheet?',

'product_ideas' => 'Hello ka-SangkAI! 🚀 Heto ang paraan para makaisip ng bagong produkto:\n\n📋 Mangolekta ng feedback mula sa customers\n📱 Gamitin ang social media polls para malaman ang demand\n🧠 Obserbahan ang trending products sa ibang stores\n🌱 Mag-test ng small batches bago mag-scale up\n\nPwede kitang tulungan gumawa ng survey form or poll!',



    ];
    
    // Check for keyword matches
    foreach ($responses as $keyword => $response) {
        if (strpos($lowerMessage, $keyword) !== false) {
            return $response;
        }
    }
    
    // Default responses
    $defaultResponses = [
        'I understand you need assistance with that. Could you provide more specific details about what you\'re trying to accomplish? This will help me give you the most accurate guidance.',
        
        'That\'s a great question! Based on your business setup, I can provide targeted advice. What specific area would you like me to focus on - payroll, inventory, expenses, logistics, or something else?',
        
        'I\'m here to help you resolve this quickly. To give you the best solution, could you tell me:\n• What you were trying to do\n• What happened instead\n• Any error messages you saw',
        
        'Absolutely! I can walk you through that step-by-step. Your business data shows everything is running smoothly, so we should be able to get this sorted out quickly. What\'s the first thing you\'d like to tackle?'
    ];
    
    return $defaultResponses[array_rand($defaultResponses)];
}

function handleKnowledgeBase($pdo, $method) {
    switch ($method) {
        case 'GET':
            getKnowledgeBase($pdo);
            break;
        case 'POST':
            addKnowledgeBase($pdo);
            break;
        case 'PUT':
            updateKnowledgeBase($pdo);
            break;
        case 'DELETE':
            deleteKnowledgeBase($pdo);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getKnowledgeBase($pdo) {
    if (isset($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $stmt = $pdo->prepare("SELECT * FROM knowledge_base WHERE question LIKE ? OR answer LIKE ? OR keywords LIKE ? ORDER BY category, question");
        $stmt->execute([$search, $search, $search]);
    } else {
        $stmt = $pdo->query("SELECT * FROM knowledge_base ORDER BY category, question");
    }
    
    $knowledge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse($knowledge);
}

function searchKnowledgeBase($pdo, $query) {
    $queryLower = strtolower($query);
    
    // Search in questions, answers, and keywords
    $stmt = $pdo->prepare("
        SELECT *, 
        (CASE 
            WHEN LOWER(question) LIKE ? THEN 10 
            WHEN LOWER(keywords) LIKE ? THEN 5 
            WHEN LOWER(answer) LIKE ? THEN 2 
            ELSE 0 
        END) as relevance_score
        FROM knowledge_base 
        WHERE LOWER(question) LIKE ? OR LOWER(keywords) LIKE ? OR LOWER(answer) LIKE ?
        HAVING relevance_score > 0
        ORDER BY relevance_score DESC 
        LIMIT 1
    ");
    
    $searchTerm = '%' . $queryLower . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addKnowledgeBase($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    $required = ['question', 'answer', 'category'];
    $errors = validateRequired($required, $input);
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO knowledge_base (question, answer, category, keywords) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            sanitizeInput($input['question']),
            sanitizeInput($input['answer']),
            sanitizeInput($input['category']),
            isset($input['keywords']) ? sanitizeInput($input['keywords']) : ''
        ]);
        
        $id = $pdo->lastInsertId();
        
        // Return the created knowledge entry
        $stmt = $pdo->prepare("SELECT * FROM knowledge_base WHERE id = ?");
        $stmt->execute([$id]);
        $knowledge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($knowledge, 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Seed sample support data
function seedSupportData($pdo) {
    // Seed support tickets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM support_tickets");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $sampleTickets = [
            ['T001', 'Payroll sync issue', 'Having trouble syncing payroll data with the system', 'high', 'open'],
            ['T002', 'Inventory report error', 'The inventory report is showing incorrect stock numbers', 'medium', 'pending'],
            ['T003', 'Account settings question', 'Need help updating my notification preferences', 'low', 'resolved']
        ];
        
        foreach ($sampleTickets as $ticket) {
            $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_id, subject, description, priority, status, created_by) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute($ticket);
        }
    }
    
    // Seed knowledge base
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM knowledge_base");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $sampleKnowledge = [
            ['How do I process payroll?', 'To process payroll: 1. Go to Payroll section 2. Click "Run Payroll" 3. Review employee data 4. Approve payments', 'payroll', 'payroll,process,salary,wages,payment'],
            ['How to export expense reports?', 'Export expense reports by: 1. Go to Expense Tracker 2. Select date range 3. Click Export button 4. Choose format (PDF/CSV/Excel)', 'expenses', 'export,expense,report,download,csv,pdf'],
            ['Why is my inventory showing incorrect numbers?', 'Inventory discrepancies can occur due to: 1. Recent imports not processed 2. Browser cache issues 3. Sync delays. Try refreshing and running a stock audit.', 'inventory', 'inventory,incorrect,wrong,numbers,stock,discrepancy'],
            ['How do I find suppliers?', 'Use the Logistics dashboard to: 1. Search for raw materials 2. View supplier locations on map 3. Compare ratings and prices 4. Contact suppliers directly', 'logistics', 'suppliers,logistics,raw materials,map,contact'],
            ['How to create a support ticket?', 'Create support tickets by: 1. Go to Communication & Support 2. Click "New Ticket" 3. Fill in subject and description 4. Set priority level', 'support', 'ticket,support,help,create,new']
        ];
        
        foreach ($sampleKnowledge as $kb) {
            $stmt = $pdo->prepare("INSERT INTO knowledge_base (question, answer, category, keywords) VALUES (?, ?, ?, ?)");
            $stmt->execute($kb);
        }
    }
}

// Seed data if needed
try {
    seedSupportData($pdo);
} catch (Exception $e) {
    error_log("Support seeding error: " . $e->getMessage());
}
?>