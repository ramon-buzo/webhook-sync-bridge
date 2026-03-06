<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Container;
use App\Services\SubscriptionService;
use App\ValueObjects\SubscriptionRequest;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true);
$phone = trim($body['phone'] ?? '');
$plan  = trim($body['plan'] ?? '');

if (empty($phone) || empty($plan)) {
    http_response_code(422);
    echo json_encode(['error' => 'phone_and_plan_required']);
    exit;
}

$container = Container::build();

/**
 * @var SubscriptionService $service
 */
$service   = $container->make(SubscriptionService::class);

$request = new SubscriptionRequest(
    phone:      $phone,
    plan:       $plan,
    forceError: (bool) ($body['force_error'] ?? false),
);

$result = $service->subscribe($request);

http_response_code($result['success'] ? 200 : 422);
echo json_encode($result);