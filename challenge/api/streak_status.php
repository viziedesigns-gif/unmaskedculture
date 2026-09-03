<?php
/**
 * API: Get Streak Status
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';

header('Content-Type: application/json');

// Require authentication
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

$userId = getCurrentUserId();

// Check streak continuity and get status
checkStreakContinuity($userId);
$status = getStreakStatus($userId);

jsonResponse([
    'success' => true,
    'streak' => $status
]);
