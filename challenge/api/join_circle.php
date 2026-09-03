<?php
/**
 * API: Join Inner Circle
 * Kinto App
 */

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

$inviteCode = strtoupper(trim($input['invite_code'] ?? ''));

if (empty($inviteCode)) {
    jsonResponse(['success' => false, 'error' => 'Invite code is required']);
}

$userId = getCurrentUserId();

// Find circle
$circle = dbFetchOne(
    "SELECT * FROM inner_circles WHERE invite_code = ?",
    [$inviteCode]
);

if (!$circle) {
    jsonResponse(['success' => false, 'error' => 'Invalid invite code']);
}

// Check if already member
$existing = dbFetchOne(
    "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
    [$circle['id'], $userId]
);

if ($existing) {
    jsonResponse(['success' => false, 'error' => 'You are already a member of this circle']);
}

$pdo = getDbConnection();
$pdo->beginTransaction();

try {
    // Join circle
    dbQuery(
        "INSERT INTO inner_circle_members (circle_id, user_id, invited_by, role, joined_at) 
         VALUES (?, ?, ?, 'member', NOW())",
        [$circle['id'], $userId, $circle['created_by']]
    );
    
    // Track invite for streak repair
    $inviteRewardInsert = dbQuery(
        "INSERT IGNORE INTO invite_tracking
            (inviter_id, invitee_id, circle_id, invite_code_used, reward_granted, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())",
        [$circle['created_by'], $userId, $circle['id'], $inviteCode]
    );
    
    // Award streak repair to circle creator
    if ($inviteRewardInsert->rowCount() > 0) {
        awardStreakRepair($circle['created_by'], 1);
    }
    
    $pdo->commit();

    try {
        $joiningUser = getCurrentUser();
        $joiningName = trim((string) (($joiningUser['first_name'] ?? '') ?: 'Someone'));
        notifyCircleMembersOfJoin((int) $circle['id'], $userId, $joiningName, $circle['name']);
    } catch (Exception $e) {
        error_log("Circle join push failed: " . $e->getMessage());
    }
    
    jsonResponse([
        'success' => true,
        'circle_id' => $circle['id'],
        'circle_name' => $circle['name']
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Circle join error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Failed to join circle'], 500);
}
