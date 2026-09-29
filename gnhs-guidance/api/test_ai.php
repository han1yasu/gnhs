<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// TEMPORARY DEBUG FILE — DELETE AFTER TESTING
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: text/html; charset=utf-8');

echo "<h2>AI Summary Debug Test</h2>";

// Check cURL
echo "<p><strong>cURL available:</strong> " . (function_exists('curl_init') ? '✅ YES' : '❌ NO') . "</p>";

// Test API call
$apiKey = (getenv('ANTHROPIC_API_KEY') ?: '');

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 100,
    'messages'   => [['role'=>'user','content'=>'Say hello in one sentence.']]
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: '.$apiKey,
        'anthropic-version: 2023-06-01',
    ],
]);

$response = curl_exec($ch);
$err      = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>cURL Error:</strong> " . ($err ?: '(none)') . "</p>";
echo "<p><strong>Raw Response:</strong></p>";
echo "<pre style='background:#f4f4f4;padding:16px;border-radius:8px;overflow:auto'>" . htmlspecialchars($response) . "</pre>";

$data = json_decode($response, true);
$text = $data['content'][0]['text'] ?? null;
echo "<p><strong>Parsed Text:</strong> " . ($text ? htmlspecialchars($text) : '❌ Could not parse') . "</p>";
echo "<hr><p style='color:red'><strong>⚠️ DELETE this file after testing!</strong></p>";



