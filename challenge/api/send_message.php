<?php
/**
 * API: Send Message to Circle
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_service.php';
require_once __DIR__ . '/../includes/feed_service.php';
require_once __DIR__ . '/../includes/shop_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$circleId = (int) ($input['circle_id'] ?? 0);
$message = trim($input['message'] ?? '');
$requestedMentionIds = is_array($input['mention_ids'] ?? null) ? $input['mention_ids'] : [];

if ($circleId < 1) {
    jsonResponse(['success' => false, 'error' => 'Invalid circle']);
}

if (empty($message)) {
    jsonResponse(['success' => false, 'error' => 'Message cannot be empty']);
}

if (strlen($message) > 2000) {
    jsonResponse(['success' => false, 'error' => 'Message too long (max 2000 characters)']);
}

$userId = getCurrentUserId();

// Verify user is member of circle
$membership = dbFetchOne(
    "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
    [$circleId, $userId]
);

if (!$membership) {
    jsonResponse(['success' => false, 'error' => 'You are not a member of this circle'], 403);
}

try {
    // Insert message
    dbQuery(
        "INSERT INTO circle_messages (circle_id, user_id, message, created_at_utc) 
         VALUES (?, ?, ?, UTC_TIMESTAMP())",
        [$circleId, $userId, $message]
    );
    
    $messageId = dbLastId();
    
    // Get the created message with user info
    require_once __DIR__ . '/../includes/avatar_service.php';
    $frameSelect = shopFrameSelectSql('u');
    $avatarSelect = avatarSelectSql('u');
    $newMessage = dbFetchOne(
        "SELECT cm.*, u.first_name, u.last_name, u.profile_pic, u.chat_bubble_color $frameSelect $avatarSelect
         FROM circle_messages cm
         JOIN users u ON cm.user_id = u.id
         WHERE cm.id = ?",
        [$messageId]
    );

    try {
        if (pushTablesReady() && isPushConfigured()) {
            $mentionedIds = validateCircleMentionIds($requestedMentionIds, $circleId, $userId);
            if (!$mentionedIds) {
                $mentionedIds = extractCircleMentionIds($message, $circleId, $userId);
            }
            if ($mentionedIds) {
                $placeholders = implode(',', array_fill(0, count($mentionedIds), '?'));
                $recipients = dbFetchAll(
                    "SELECT DISTINCT ps.user_id FROM push_subscriptions ps
                     WHERE ps.user_id IN ($placeholders) AND ps.feed_enabled = 1",
                    $mentionedIds
                );
            } else {
                $recipients = dbFetchAll(
                    "SELECT DISTINCT icm.user_id
                     FROM inner_circle_members icm
                     JOIN push_subscriptions ps ON ps.user_id = icm.user_id
                     WHERE icm.circle_id = ? AND icm.user_id <> ? AND ps.feed_enabled = 1",
                    [$circleId, $userId]
                );
            }
            $recipientIds = array_map(static fn($row) => (int) $row['user_id'], $recipients);
            if (!empty($recipientIds)) {
                $pushMessage = preg_replace('/@\[([^\]]+)\]\(\d+\)/', '@$1', $message) ?: $message;
                sendPushToUsers(
                    $recipientIds,
                    ($newMessage['first_name'] ?? 'Someone') . ($mentionedIds ? ' mentioned you' : ' posted in your Feed'),
                    substr($pushMessage, 0, 140),
                    '/challenge/app/feed.php?circle=' . $circleId
                );
            }
        }
    } catch (Exception $e) {
        error_log('Feed push notification failed: ' . $e->getMessage());
    }
    
    jsonResponse([
        'success' => true,
        'message' => withFeedUserCosmetics($newMessage ?: [])
    ]);
    
} catch (Exception $e) {
    error_log("Send message error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Failed to send message'], 500);
}
