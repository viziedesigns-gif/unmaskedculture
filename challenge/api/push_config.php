<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

$status = getPushConfigStatus();

jsonResponse([
    'success' => true,
    'configured' => $status['configured'],
    'public_key' => $status['public_key'],
    'has_vapid_keys' => $status['has_vapid_keys'],
    'has_web_push_library' => $status['has_web_push_library'],
    'has_database_table' => $status['has_database_table'],
]);
