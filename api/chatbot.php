<?php
/**
 * Health Chatbot API Endpoint
 * Proxies requests to Google Gemini API
 */

session_start();
require_once '../config/db.php';
require_once '../config/env.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$message = sanitize($input['message'] ?? '');
$context = $input['context'] ?? '';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Server-side rate limiting
$userId = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
$rateLimitKey = 'chatbot_rate_' . md5($userId);
$rateLimitFile = sys_get_temp_dir() . '/' . $rateLimitKey . '.json';

$requests = [];
$now = time();

if (file_exists($rateLimitFile)) {
    $requests = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    // Filter to last minute
    $requests = array_filter($requests, function($time) use ($now) {
        return ($now - $time) < 60;
    });
}

// Check rate limit (10 requests per minute server-side)
if (count($requests) >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

$requests[] = $now;
file_put_contents($rateLimitFile, json_encode($requests));

// Groq API Configuration
// Get your API key from: https://console.groq.com/keys
$groqApiKey = env('GROQ_API_KEY', ''); // Your Groq API key from .env file

// If no API key is set, return empty to trigger fallback
if (empty($groqApiKey) || strlen($groqApiKey) < 20) {
    logChatQuery($message, '', 'no_api_key');
    echo json_encode(['response' => '']);
    exit;
}

// Prepare the prompt with health context
$fullPrompt = $context . "\n\nUser Question: " . $message . "\n\nPlease provide a helpful, accurate response about health or medication. Keep it concise (under 150 words) and include a reminder to consult healthcare professionals for specific medical advice.";

// Call Groq API
try {
    // Use llama-3.3-70b-versatile model (fast, capable model)
    $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    
    $requestData = [
        'model' => 'llama-3.3-70b-versatile',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful health and medication assistant. Provide accurate, concise health information while always reminding users to consult healthcare professionals for specific medical advice.'
            ],
            [
                'role' => 'user',
                'content' => $fullPrompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 250,
        'top_p' => 0.8
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqApiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Curl error: ' . $curlError);
    }
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        // Extract response from Groq API format (OpenAI-compatible)
        if (isset($data['choices'][0]['message']['content'])) {
            $aiResponse = trim($data['choices'][0]['message']['content']);
            
            // Log successful query
            logChatQuery($message, $aiResponse, 'groq_success');
            
            echo json_encode(['response' => $aiResponse]);
            exit;
        }
    }
    
    // If API fails, return empty to trigger fallback
    throw new Exception('Groq API request failed with code: ' . $httpCode);
    
} catch (Exception $e) {
    // Log failed query
    logChatQuery($message, '', 'groq_failed: ' . $e->getMessage());
    
    // Return empty response to trigger client-side fallback
    echo json_encode(['response' => '']);
    exit;
}

/**
 * Log chat queries for analytics
 */
function logChatQuery($query, $response, $status) {
    $logFile = __DIR__ . '/../logs/chatbot.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'query' => substr($query, 0, 500), // Limit query length
        'response_length' => strlen($response),
        'status' => $status,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}
