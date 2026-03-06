<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$uri = $_SERVER['REQUEST_URI'];

if ($uri !== '/subscribe') {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$sessionKey = $body['session_key'] ?? '';
$phone = $body['phone'] ?? '';
$plan = $body['plan'] ?? '';

if (empty($sessionKey) || empty($phone) || empty($plan)) {
    http_response_code(422);
    echo json_encode(['ack' => -1, 'error' => 'missing_fields']);
    exit;
}

$shouldFail = (bool)($body['force_error'] ?? false);

if ($shouldFail) {
    echo json_encode(['ack' => 1]);
    exit;
}

$webhookUrl = getenv('WEBHOOK_URL') ?: 'http://app/webhook.php';
$clientSecret = getenv('PARTNER_CLIENT_SECRET') ?: '';

// Simulate partner delay (1-3 seconds)
$delay = rand(1, 3);
$timestamp = time();

$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<notification>
    <timestamp>{$timestamp}</timestamp>
    <session_key>{$sessionKey}</session_key>
    <phone>{$phone}</phone>
    <plan>{$plan}</plan>
    <price>9.99</price>
    <status>active</status>
    <signature>{SIGNATURE}</signature>
</notification>
XML;

// Generate signature: HMAC-SHA256(timestamp + xml_body, client_secret)
// Timestamp is included to make the signature unique per request (prevents replay attacks)
$signatureInput = $timestamp . $xml;
$signature = hash_hmac('sha256', $signatureInput, $clientSecret);

// Replace placeholder with computed signature
$xml = str_replace('{SIGNATURE}', $signature, $xml);

// Send ACK response immediately
echo json_encode(['ack' => 0]);

// Fire webhook asynchronously after delay
$cmd = sprintf(
    'sleep %d && curl -s -X POST %s -H "Content-Type: application/xml" --data-binary %s > /dev/null 2>&1 &',
    $delay,
    escapeshellarg($webhookUrl),
    escapeshellarg($xml)
);

shell_exec($cmd);