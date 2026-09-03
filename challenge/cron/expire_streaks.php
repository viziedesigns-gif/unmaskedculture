<?php
/**
 * Cron: expire missed streaks.
 *
 * Suggested schedule: run every minute. The expiry helper is idempotent, so
 * repeated runs are safe and let each user expire after their local deadline
 * (1:00 AM for Easy, midnight for Intermediate).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

require_once __DIR__ . '/../includes/streak_service.php';

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 500;
$lastSeenUserId = 0;
$processed = 0;
$errors = 0;

do {
    $users = dbFetchAll(
        "SELECT user_id
         FROM user_streaks
         WHERE last_completed_date IS NOT NULL
           AND current_streak > 0
           AND user_id > ?
         ORDER BY user_id
         LIMIT $limit",
        [$lastSeenUserId]
    );

    foreach ($users as $row) {
        $userId = (int) $row['user_id'];
        $lastSeenUserId = $userId;
        try {
            expireStreakIfNeeded($userId);
            $processed++;
        } catch (Exception $e) {
            $errors++;
            error_log("Cron streak expiry failed for user $userId: " . $e->getMessage());
        }
    }
} while (count($users) === $limit);

echo "Processed $processed active streaks";
if ($errors > 0) {
    echo " with $errors errors";
}
echo ".\n";

exit($errors > 0 ? 1 : 0);
