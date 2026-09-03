<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_service.php';
require_once __DIR__ . '/../includes/retention_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['success' => false, 'error' => 'Invalid JSON'], 400);
}

try {
    $userId = getCurrentUserId();
    savePushSubscription(
        $userId,
        $input,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
    );
    ensureRetentionColumns();
    syncPushSubscriptionPrefs($userId);
    jsonResponse([
        'success' => true,
        'subscriptions' => getUserPushSubscriptionCount($userId),
    ]);
} catch (Exception $e) {
    error_log('Push subscribe failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Unable to save push subscription'], 400);
}
