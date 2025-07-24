<?php
// chatbot_endpoint.php
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

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
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
 * Send Chat Message
 */
function sendChatMessage($apiKey, $baseUrl, $sessionId, $content) {
    $ch = curl_init();
    
    $postData = json_encode([
        'content' => $content
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
    // Get session
    $sessionResponse = getAiSession($apiKey, $baseUrl);
    $sessionId = $sessionResponse['session_id'] ?? $sessionResponse['id'] ?? null;
    
    if (!$sessionId) {
        throw new Exception('Failed to get session ID from response');
    }
    
    // Send message
    $chatResponse = sendChatMessage($apiKey, $baseUrl, $sessionId, $message);
    
    // Return response (adjust based on your API's response structure)
    $botMessage = $chatResponse['response'] ?? $chatResponse['content'] ?? $chatResponse['message'] ?? 'No response received';
    
    echo json_encode([
        'success' => true,
        'response' => $botMessage
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>