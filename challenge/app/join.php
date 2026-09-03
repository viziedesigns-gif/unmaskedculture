<?php
/**
 * Auto-Join Circle via Invite Link
 * Kinto App
 * 
 * Route: /challenge/app/join.php?code=ABC123
 * - If logged in: auto-join circle, redirect to feed
 * - If not logged in: store code in session, redirect to register
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/push_service.php';

$inviteCode = normalizeCircleInviteCode($_GET['code'] ?? '');

// Validate invite code
if (empty($inviteCode) || strlen($inviteCode) > 20) {
    setFlash('error', 'Invalid invite link');
    redirect('/challenge/app/');
}

// Look up and retain the circle through authentication and onboarding.
$circle = rememberCircleInvite($inviteCode);

if (!$circle) {
    setFlash('error', 'This invite link is invalid or has expired');
    redirect('/challenge/app/');
}

// Check if user is logged in
if (!isLoggedIn()) {
    setFlash('info', 'Sign in or create an account to join "' . $circle['name'] . '"');
    redirect('/challenge/app/?invite=' . rawurlencode($inviteCode));
}

$user = getCurrentUser();
if (!$user['onboarding_completed']) {
    setFlash('info', 'Continue onboarding to join "' . $circle['name'] . '"');
    redirect('/challenge/onboarding/step1.php?invite=' . rawurlencode($inviteCode));
}

// User is logged in and onboarded - proceed with joining
$userId = getCurrentUserId();

// Check if already a member
$existing = dbFetchOne(
    "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
    [$circle['id'], $userId]
);

if ($existing) {
    setFlash('info', 'You are already a member of "' . $circle['name'] . '"');
    redirect('/challenge/app/feed.php?circle=' . (int) $circle['id']);
}

// Join the circle
$pdo = getDbConnection();
$pdo->beginTransaction();

try {
    // Add user to circle
    dbQuery(
        "INSERT INTO inner_circle_members (circle_id, user_id, invited_by, role, joined_at) 
         VALUES (?, ?, ?, 'member', NOW())",
        [$circle['id'], $userId, $circle['created_by']]
    );
    
    // Track the invite
    $inviteRewardInsert = dbQuery(
        "INSERT IGNORE INTO invite_tracking
            (inviter_id, invitee_id, circle_id, invite_code_used, reward_granted, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())",
        [$circle['created_by'], $userId, $circle['id'], $inviteCode]
    );
    
    // Award streak repair to circle owner
    if ($inviteRewardInsert->rowCount() > 0) {
        awardStreakRepair($circle['created_by'], 1);
    }
    
    $pdo->commit();
    
    // Post welcome message to the circle (non-critical)
    $joiningUser = getCurrentUser();
    $joiningName = trim((string) (($joiningUser['first_name'] ?? '') ?: 'Someone'));
    try {
        postSystemMessage(
            $circle['id'],
            $userId,
            $joiningName . ' has joined ' . $circle['name'] . '!',
            'system_join'
        );
    } catch (Exception $e) {
        error_log("Welcome message failed: " . $e->getMessage());
    }

    try {
        notifyCircleMembersOfJoin((int) $circle['id'], $userId, $joiningName, $circle['name']);
    } catch (Exception $e) {
        error_log("Circle join push failed: " . $e->getMessage());
    }
    
    setFlash('success', 'Welcome to "' . $circle['name'] . '"!');
    redirect('/challenge/app/feed.php?circle=' . (int) $circle['id']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Join circle error: " . $e->getMessage());
    setFlash('error', 'Failed to join circle. Please try again.');
    redirect('/challenge/app/feed.php');
}
