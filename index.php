<?php
// index.php

// Load webhook URL from environment variable
$webhook_url = getenv('DISCORD_WEBHOOK');

// Read and decode incoming JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Validate embed structure
if (!isset($data['embed']) || !is_array($data['embed'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing or invalid 'embed' field"]);
    exit;
}

// Prepare payload
$payload = json_encode(["embeds" => [$data['embed']]]);

// Forward function
function forwardToDiscord($url, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    return [$httpCode, $curlError, $response];
}

// Dispatch
list($httpCode, $curlError, $response) = forwardToDiscord($webhook_url, $payload);

// Log every attempt
file_put_contents('logs.txt', date('c') . " | $httpCode | $payload\n", FILE_APPEND);

// Optional queue fallback
if ($curlError || $httpCode < 200 || $httpCode >= 300) {
    file_put_contents('queue.txt', $payload . "\n", FILE_APPEND);
}

// Respond to sender
if ($curlError) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $curlError]);
} elseif ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(["success" => true]);
} else {
    http_response_code($httpCode ?: 500);
    echo json_encode(["success" => false, "status" => $httpCode, "response" => $response]);
}
?>
