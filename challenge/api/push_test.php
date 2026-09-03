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

$result = sendPushToUser(
    getCurrentUserId(),
    'Kinto notifications are on',
    'You will receive reminders and challenge updates here.',
    '/challenge/app/dashboard.php'
);

if (!$result['configured']) {
    jsonResponse([
        'success' => false,
        'error' => 'Push is not configured on the server yet. Install Composer dependencies and add VAPID keys.',
        'result' => $result,
    ], 503);
}

jsonResponse([
    'success' => true,
    'result' => $result,
]);
