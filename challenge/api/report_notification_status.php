<?php
/** Record the signed-in user's latest browser notification capability. */
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) jsonResponse(['success' => false], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false], 405);
ensureAdminInfrastructure();

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$supported = !empty($payload['supported']) ? 1 : 0;
$permission = (string) ($payload['permission'] ?? 'unknown');
if (!in_array($permission, ['granted', 'denied', 'default', 'unsupported', 'unknown'], true)) $permission = 'unknown';

dbQuery(
    "INSERT INTO user_notification_status (user_id, supported, permission_state, last_reported_at_utc, user_agent)
     VALUES (?, ?, ?, UTC_TIMESTAMP(), ?)
     ON DUPLICATE KEY UPDATE supported = VALUES(supported), permission_state = VALUES(permission_state),
        last_reported_at_utc = UTC_TIMESTAMP(), user_agent = VALUES(user_agent)",
    [getCurrentUserId(), $supported, $permission, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
);
jsonResponse(['success' => true]);
