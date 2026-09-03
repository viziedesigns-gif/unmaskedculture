<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = (string) ($input['endpoint'] ?? '');
deletePushSubscription(getCurrentUserId(), $endpoint);

jsonResponse([
    'success' => true,
    'subscriptions' => getUserPushSubscriptionCount(getCurrentUserId()),
]);
