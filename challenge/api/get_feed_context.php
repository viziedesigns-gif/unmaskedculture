<?php
/** Authenticated circle header, member standings, and mention context. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/feed_service.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);

$userId = getCurrentUserId();
$circleId = (int) ($_GET['circle'] ?? 0);
$circle = dbFetchOne(
    "SELECT ic.id, ic.name, ic.description, ic.created_by, icm.role
     FROM inner_circles ic
     JOIN inner_circle_members icm ON icm.circle_id = ic.id
     WHERE ic.id = ? AND icm.user_id = ?",
    [$circleId, $userId]
);
if (!$circle) jsonResponse(['success' => false, 'error' => 'Circle not found'], 403);

$_SESSION['active_circle_id'] = $circleId;

$user = getCurrentUser();
$userDate = computeUserDate($user['timezone'] ?? DEFAULT_TIMEZONE);
jsonResponse([
    'success' => true,
    'circle' => $circle,
    'members' => getRankedCircleMembers($circleId, $userDate),
]);
