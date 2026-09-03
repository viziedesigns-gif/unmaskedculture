<?php
/**
 * API: SSE Messages Stream
 * Server-Sent Events for real-time chat
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/feed_service.php';
require_once __DIR__ . '/../includes/shop_service.php';
require_once __DIR__ . '/../includes/avatar_service.php';

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Check authentication
if (!isLoggedIn()) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Not authenticated']) . "\n\n";
    exit;
}

$circleId = (int) ($_GET['circle'] ?? 0);
$lastId = (int) ($_GET['last_id'] ?? 0);

if ($circleId < 1) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Invalid circle']) . "\n\n";
    exit;
}

$userId = getCurrentUserId();

// Verify membership
$membership = dbFetchOne(
    "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
    [$circleId, $userId]
);

if (!$membership) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Not a member']) . "\n\n";
    exit;
}
ensureFeedReactionTable();

// This endpoint holds a long-lived request open. Release the PHP session lock
// now so navigating from Feed to Daily (or any other app page) is not blocked
// until the SSE loop times out.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Set script timeout (30 seconds for SSE connection)
set_time_limit(35);
$startTime = time();
$timeout = 30;
$lastMembershipCheck = $startTime;

// Main loop
while (true) {
    if ((time() - $lastMembershipCheck) >= 3) {
        $stillMember = dbFetchOne(
            "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
            [$circleId, $userId]
        );
        if (!$stillMember) {
            echo "data: " . json_encode(['type' => 'access_revoked']) . "\n\n";
            flush();
            break;
        }
        $lastMembershipCheck = time();
    }

    // Check for new messages
    require_once __DIR__ . '/../includes/avatar_service.php';
    $frameSelect = shopFrameSelectSql('u');
    $avatarSelect = avatarSelectSql('u');
    $messages = dbFetchAll(
        "SELECT cm.*, u.first_name, u.last_name, u.profile_pic, u.chat_bubble_color $frameSelect $avatarSelect,
                (SELECT COUNT(*) FROM circle_message_reactions cmr WHERE cmr.message_id = cm.id AND cmr.reaction = 'heart') AS heart_count,
                EXISTS(SELECT 1 FROM circle_message_reactions cmr2 WHERE cmr2.message_id = cm.id AND cmr2.user_id = ? AND cmr2.reaction = 'heart') AS hearted_by_me
         FROM circle_messages cm
         JOIN users u ON cm.user_id = u.id
         WHERE cm.circle_id = ? AND cm.id > ?
         ORDER BY cm.id ASC
         LIMIT 50",
        [$userId, $circleId, $lastId]
    );
    
    if (!empty($messages)) {
        foreach ($messages as $msg) {
            echo "data: " . json_encode([
                'type' => 'message',
                'message' => withFeedUserCosmetics($msg)
            ]) . "\n\n";
            
            $lastId = $msg['id'];
        }
        
        // Flush output
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    // Send ping every 15 seconds to keep connection alive
    if ((time() - $startTime) % 15 === 0) {
        echo "data: " . json_encode(['type' => 'ping']) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    // Check if we've exceeded timeout
    if ((time() - $startTime) >= $timeout) {
        // Send reconnect event
        echo "event: reconnect\n";
        echo "data: " . json_encode(['last_id' => $lastId]) . "\n\n";
        break;
    }
    
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    // Sleep to prevent CPU hogging (100ms for faster message delivery)
    usleep(100000); // 0.1 seconds
}
