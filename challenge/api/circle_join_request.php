<?php
/**
 * Create an owner-reviewed circle join request.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/community_service.php';

requireOnboarding();
$communityReady = ensureCommunityTables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$returnTo = (string) ($_POST['return_to'] ?? '/challenge/app/feed.php');
if (!str_starts_with($returnTo, '/challenge/app/member_profile.php?')) {
    $returnTo = '/challenge/app/feed.php';
}

if (!validCommunityCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Your session expired. Please try again.');
    redirect($returnTo);
}
if (!$communityReady) {
    setFlash('error', 'Circle requests are temporarily unavailable. Please try again later.');
    redirect($returnTo);
}

$circleId = (int) ($_POST['circle_id'] ?? 0);
$requesterId = (int) getCurrentUserId();
$pdo = getDbConnection();
$pdo->beginTransaction();
try {
    $circle = dbFetchOne(
        "SELECT ic.id, ic.name, ic.created_by
         FROM inner_circles ic
         JOIN users owner ON owner.id = ic.created_by
         WHERE ic.id = ? AND owner.profile_visible = 1 AND owner.public_profile_slug IS NOT NULL
         FOR UPDATE",
        [$circleId]
    );
    if (!$circle || (int) $circle['created_by'] === $requesterId) {
        $pdo->rollBack();
        setFlash('error', 'That circle is not available for requests.');
        redirect($returnTo);
    }

    if (dbFetchOne("SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?", [$circleId, $requesterId])) {
        $pdo->rollBack();
        setFlash('success', 'You are already a member of that circle.');
        redirect('/challenge/app/feed.php?circle=' . $circleId);
    }

    dbQuery(
        "INSERT INTO circle_join_requests
            (circle_id, requester_id, status, pending_key, requested_at)
         VALUES (?, ?, 'pending', ?, UTC_TIMESTAMP())",
        [$circleId, $requesterId, pendingJoinKey($circleId, $requesterId)]
    );
    $pdo->commit();
    setFlash('success', 'Your request to join "' . $circle['name'] . '" was sent.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        setFlash('success', 'Your request is already waiting for owner review.');
    } else {
        error_log('Circle join request failed: ' . $e->getMessage());
        setFlash('error', 'We could not send that request. Please try again.');
    }
}

redirect($returnTo);
