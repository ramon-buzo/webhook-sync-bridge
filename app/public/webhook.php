<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Container;
use App\Services\WebhookProcessor;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');

$container = Container::build();
$processor = $container->make(WebhookProcessor::class);

$result = $processor->process($rawBody);

http_response_code($result['success'] ? 200 : 422);
echo json_encode($result);