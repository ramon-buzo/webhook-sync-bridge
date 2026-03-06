<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\RedisClient;

header('Content-Type: application/json');

$checks = [];

// Check Redis
try {
    $redis = new RedisClient();
    $checks['redis'] = 'ok';
} catch (\Throwable $e) {
    $checks['redis'] = 'error';
}

// Check partner mock
$partnerUrl = getenv('PARTNER_MOCK_URL') ?: 'http://partner-mock:8082';
$ch = curl_init($partnerUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 3,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$checks['partner'] = $httpCode === 200 ? 'ok' : 'error';

$allOk = !in_array('error', $checks);

http_response_code($allOk ? 200 : 503);
echo json_encode([
    'status' => $allOk ? 'ok' : 'degraded',
    'version' => '1.0.0',
    'timestamp' => date('c'),
    'checks' => $checks,
]);