<?php
$apiKey = 'AIzaSyDaaO1qG26BXv7f-W5SFG9YDOvPC5YCcQY';

// First, list available models
$listUrl = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $listUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
curl_close($ch);

echo "<h3>Available Models:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try gemini-2.0-flash (stable, fast model)
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

$requestData = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say hello in one sentence']
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>HTTP Code: $httpCode</h3>";
echo "<h3>Curl Error: " . ($error ?: 'None') . "</h3>";
echo "<h3>Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        echo "<h3 style='color:green'>SUCCESS: " . htmlspecialchars($data['candidates'][0]['content']['parts'][0]['text']) . "</h3>";
    }
}
