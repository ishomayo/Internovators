<?php
// knowledge_base.php - Enhanced chatbot with CSV knowledge base
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Configuration
$apiKey = 'NjdhMGE3OTYtMWYxNi00M2YwLWJlZGYtMTFlZmZkN2EzMzRm';
$baseUrl = 'https://ai-tools.rev21labs.com/api/v1/ai';
$csvFile = 'Barato KB - KB.csv'; // Your CSV file path

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

/**
 * Load and search knowledge base from CSV
 */
function searchKnowledgeBase($csvFile, $query) {
    if (!file_exists($csvFile)) {
        return null;
    }
    
    $knowledgeBase = [];
    $file = fopen($csvFile, 'r');
    
    // Get headers
    $headers = fgetcsv($file);
    if (!$headers) {
        fclose($file);
        return null;
    }
    
    // Read data
    while (($row = fgetcsv($file)) !== FALSE) {
        $knowledgeBase[] = array_combine($headers, $row);
    }
    fclose($file);
    
    // Search for relevant entries
    $query = strtolower($query);
    $matches = [];
    
    foreach ($knowledgeBase as $entry) {
        // Search in question/title field (adjust field names based on your CSV)
        $question = strtolower($entry['question'] ?? $entry['title'] ?? '');
        $keywords = strtolower($entry['keywords'] ?? '');
        $content = strtolower($entry['answer'] ?? $entry['content'] ?? '');
        
        // Simple keyword matching (you can make this more sophisticated)
        if (strpos($question, $query) !== false || 
            strpos($keywords, $query) !== false ||
            strpos($content, $query) !== false) {
            $matches[] = $entry;
        }
    }
    
    return $matches;
}

/**
 * Format knowledge base context for AI
 */
function formatKnowledgeContext($matches) {
    if (empty($matches)) {
        return '';
    }
    
    $context = "\n\nRelevant information from knowledge base:\n";
    foreach ($matches as $match) {
        $question = $match['question'] ?? $match['title'] ?? 'N/A';
        $answer = $match['answer'] ?? $match['content'] ?? 'N/A';
        $context .= "Q: $question\nA: $answer\n\n";
    }
    
    return $context;
}

/**
 * Get AI Session
 */
function getAiSession($apiKey, $baseUrl) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/session',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey
        ],
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('HTTP Error: ' . $httpCode . ' - ' . $response);
    }
    
    return json_decode($response, true);
}

/**
 * Send Chat Message with Knowledge Base Context
 */
function sendChatMessage($apiKey, $baseUrl, $sessionId, $content, $knowledgeContext = '') {
    $ch = curl_init();
    
    // Enhance the message with knowledge base context
    $enhancedMessage = $content;
    if (!empty($knowledgeContext)) {
        $enhancedMessage = "User question: $content" . $knowledgeContext . 
                          "\n\nPlease answer based on the provided knowledge base information if relevant, otherwise provide a general response.";
    }
    
    $postData = json_encode([
        'content' => $enhancedMessage
    ]);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/chat',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'session-id: ' . $sessionId,
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('HTTP Error: ' . $httpCode . ' - ' . $response);
    }
    
    return json_decode($response, true);
}

try {
    // Search knowledge base first
    $knowledgeMatches = searchKnowledgeBase($csvFile, $message);
    $knowledgeContext = formatKnowledgeContext($knowledgeMatches);
    
    // Get AI session
    $sessionResponse = getAiSession($apiKey, $baseUrl);
    $sessionId = $sessionResponse['session_id'] ?? $sessionResponse['id'] ?? null;
    
    if (!$sessionId) {
        throw new Exception('Failed to get session ID from response');
    }
    
    // Send message with knowledge context
    $chatResponse = sendChatMessage($apiKey, $baseUrl, $sessionId, $message, $knowledgeContext);
    
    // Return response
    $botMessage = $chatResponse['response'] ?? $chatResponse['content'] ?? $chatResponse['message'] ?? 'No response received';
    
    echo json_encode([
        'success' => true,
        'response' => $botMessage,
        'knowledge_matches' => count($knowledgeMatches ?? [])
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>