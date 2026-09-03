<?php
/**
 * Get Circle Messages API
 * Returns messages for a specific circle
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/feed_service.php';
require_once __DIR__ . '/../includes/shop_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = getCurrentUserId();
$circleId = (int) ($_GET['circle'] ?? 0);
$lastId = (int) ($_GET['last_id'] ?? 0);

if ($circleId < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid circle']);
    exit;
}

// Verify membership
$membership = dbFetchOne(
    "SELECT icm.*, ic.name as circle_name
     FROM inner_circle_members icm
     JOIN inner_circles ic ON icm.circle_id = ic.id
     WHERE icm.circle_id = ? AND icm.user_id = ?",
    [$circleId, $userId]
);

if (!$membership) {
    echo json_encode(['success' => false, 'error' => 'Not a member']);
    exit;
}

// Get member count
$memberCount = dbFetchOne(
    "SELECT COUNT(*) as count FROM inner_circle_members WHERE circle_id = ?",
    [$circleId]
)['count'];

// Get messages
ensureFeedReactionTable();
require_once __DIR__ . '/../includes/avatar_service.php';
$frameSelect = shopFrameSelectSql('u');
$avatarSelect = avatarSelectSql('u');
$reactionSelect = ",
    (SELECT COUNT(*) FROM circle_message_reactions cmr WHERE cmr.message_id = cm.id AND cmr.reaction = 'heart') AS heart_count,
    EXISTS(SELECT 1 FROM circle_message_reactions cmr2 WHERE cmr2.message_id = cm.id AND cmr2.user_id = " . (int) $userId . " AND cmr2.reaction = 'heart') AS hearted_by_me";
if ($lastId > 0) {
    // Get only new messages after lastId
    $messages = dbFetchAll(
        "SELECT cm.*, u.first_name, u.last_name, u.profile_pic, u.chat_bubble_color $frameSelect $avatarSelect $reactionSelect
         FROM circle_messages cm
         JOIN users u ON cm.user_id = u.id
         WHERE cm.circle_id = ? AND cm.id > ?
         ORDER BY cm.created_at_utc ASC
         LIMIT 50",
        [$circleId, $lastId]
    );
} else {
    // Get last 50 messages
    $messages = dbFetchAll(
        "SELECT cm.*, u.first_name, u.last_name, u.profile_pic, u.chat_bubble_color $frameSelect $avatarSelect $reactionSelect
         FROM circle_messages cm
         JOIN users u ON cm.user_id = u.id
         WHERE cm.circle_id = ?
         ORDER BY cm.created_at_utc DESC
         LIMIT 50",
        [$circleId]
    );
    $messages = array_reverse($messages);
}

echo json_encode([
    'success' => true,
    'circle' => [
        'id' => $circleId,
        'name' => $membership['circle_name'],
        'member_count' => $memberCount
    ],
    'messages' => withFeedUserCosmeticsList($messages),
    'user_id' => $userId
]);
