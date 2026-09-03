<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/feed_service.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$messageId = (int) ($input['message_id'] ?? 0);
$userId = getCurrentUserId();
$message = dbFetchOne(
    "SELECT cm.id FROM circle_messages cm
     JOIN inner_circle_members icm ON icm.circle_id = cm.circle_id AND icm.user_id = ?
     WHERE cm.id = ? AND cm.message_type = 'message'",
    [$userId, $messageId]
);
if (!$message) jsonResponse(['success' => false, 'error' => 'Message not found'], 404);

ensureFeedReactionTable();
$existing = dbFetchOne(
    "SELECT message_id FROM circle_message_reactions WHERE message_id = ? AND user_id = ? AND reaction = 'heart'",
    [$messageId, $userId]
);
if ($existing) {
    dbQuery("DELETE FROM circle_message_reactions WHERE message_id = ? AND user_id = ? AND reaction = 'heart'", [$messageId, $userId]);
    $hearted = false;
} else {
    dbQuery("INSERT IGNORE INTO circle_message_reactions (message_id, user_id, reaction) VALUES (?, ?, 'heart')", [$messageId, $userId]);
    $hearted = true;
}
$count = dbFetchOne("SELECT COUNT(*) AS c FROM circle_message_reactions WHERE message_id = ? AND reaction = 'heart'", [$messageId]);
jsonResponse(['success' => true, 'message_id' => $messageId, 'hearted' => $hearted, 'heart_count' => (int) ($count['c'] ?? 0)]);

