<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Quick AI test page - DELETE after testing!
$apiKey = (getenv('ANTHROPIC_API_KEY') ?: '');

echo "<h2>GNHS AI Summary Test</h2>";
echo "<p>cURL available: " . (function_exists('curl_init') ? '<b style="color:green">YES ✅</b>' : '<b style="color:red">NO ❌</b>') . "</p>";

if (!function_exists('curl_init')) {
    echo "<p style='color:red'>cURL is not enabled. Enable it in php.ini</p>";
    exit;
}

$payload = json_encode([
    'model'      => 'claude-haiku-4-5',
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

echo "<p>HTTP Status: <b>$httpCode</b></p>";

if ($err) {
    echo "<p style='color:red'>cURL Error: $err</p>";
} elseif ($response) {
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "<p style='color:red'>API Error: " . htmlspecialchars($data['error']['message']) . "</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } elseif (isset($data['content'][0]['text'])) {
        echo "<p style='color:green'><b>✅ AI is working!</b></p>";
        echo "<p>Response: <i>" . htmlspecialchars($data['content'][0]['text']) . "</i></p>";
    } else {
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
echo "<hr><p style='color:red'><b>⚠️ Delete ai-test.php after testing!</b></p>";



