<?php
/**
 * Cron: send scheduled push notifications.
 *
 * Suggested schedule: every 15 minutes (see docs/push-notifications.md).
 *
 * Uses each user's timezone and daily_reminder_time for check-in reminders.
 * Streak-risk alerts fire at 9:00 PM in the user's local timezone.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

define('BATCH_SIZE', 200);
define('STREAK_RISK_LOCAL_HOUR', 21);
define('STREAK_RISK_LOCAL_MINUTE', 0);
define('PUSH_WINDOW_MINUTES', 15);

function lockFile(string $type, string $date, int $userId = 0): string {
    return sys_get_temp_dir() . '/push_cron_' . $type . '_' . $date . ($userId ? ('_' . $userId) : '') . '.lock';
}

/**
 * Short-lived lock to prevent concurrent cron overlap. Always release in finally.
 */
function acquireLock(string $type, string $date, int $userId = 0): bool {
    $path = lockFile($type, $date, $userId);
    if (file_exists($path)) {
        $age = time() - (int) @filemtime($path);
        // Stale lock older than one window can be cleared (failed prior run).
        if ($age < (PUSH_WINDOW_MINUTES * 60)) {
            return false;
        }
        @unlink($path);
    }
    return (bool) file_put_contents($path, date('c'), LOCK_EX);
}

function releaseLock(string $type, string $date, int $userId = 0): void {
    $path = lockFile($type, $date, $userId);
    if (file_exists($path)) {
        @unlink($path);
    }
}

/**
 * True when local time is within [target, target + window).
 */
function isInLocalSendWindow(int $hour, int $minute, int $targetHour, int $targetMinute, int $windowMinutes = PUSH_WINDOW_MINUTES): bool {
    $now = ($hour * 60) + $minute;
    $start = ($targetHour * 60) + $targetMinute;
    $end = $start + $windowMinutes;
    return $now >= $start && $now < $end;
}

/** Allow a delayed cron run to catch up once before the day deadline. */
function isBeforeDeadlineAndAfterTarget(
    DateTimeInterface $localNow,
    string $userDate,
    int $targetHour,
    int $targetMinute,
    int $deadlineHour = 1
): bool {
    $timezone = $localNow->getTimezone();
    $target = new DateTimeImmutable(sprintf('%s %02d:%02d:00', $userDate, $targetHour, $targetMinute), $timezone);
    $deadline = (new DateTimeImmutable(
        sprintf('%s %02d:00:00', $userDate, $deadlineHour),
        $timezone
    ))->modify('+1 day');
    return $localNow >= $target && $localNow < $deadline;
}

require_once __DIR__ . '/../includes/push_service.php';
require_once __DIR__ . '/../includes/retention_service.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/xp_service.php';

if (!isPushConfigured() || !pushTablesReady()) {
    echo "Push not configured. Exiting.\n";
    exit(0);
}

ensureRetentionColumns();
ensureXpTablesAndColumns();

$sent = 0;
$failed = 0;
$lastId = 0;

do {
    $users = dbFetchAll(
        "SELECT u.id, u.timezone, u.daily_reminder_enabled, u.daily_reminder_time, u.streak_risk_enabled, u.challenge_mode
         FROM users u
         WHERE u.onboarding_completed = 1 AND u.id > ?
         ORDER BY u.id
         LIMIT " . BATCH_SIZE,
        [$lastId]
    );

    foreach ($users as $user) {
        $uid = (int) $user['id'];
        $lastId = $uid;
        $timezone = $user['timezone'] ?: DEFAULT_TIMEZONE;

        try {
            $localNow = new DateTime('now', new DateTimeZone($timezone));
        } catch (Exception $e) {
            error_log("Push cron skipped user $uid: invalid timezone '$timezone'");
            continue;
        }

        $mode = normalizeChallengeMode($user['challenge_mode'] ?? 'intermediate');
        $deadlineHour = getChecklistDeadlineHour($mode);
        $deadlineLabel = $mode === 'easy' ? '1:00 AM' : 'midnight';

        // Easy: during 12:00-12:59 AM, reminders still target yesterday.
        // Intermediate: after midnight, the active day is today.
        $inGrace = checklistAllowsGracePeriod($mode) && (int) $localNow->format('G') < 1;
        $userDate = $inGrace
            ? (clone $localNow)->modify('-1 day')->format('Y-m-d')
            : $localNow->format('Y-m-d');

        $subs = dbFetchOne("SELECT COUNT(*) AS c FROM push_subscriptions WHERE user_id = ?", [$uid]);
        if ((int) ($subs['c'] ?? 0) < 1) {
            continue;
        }

        $isComplete = isDayComplete($uid, $userDate);

        if (!$isComplete && (int) ($user['daily_reminder_enabled'] ?? 1) === 1) {
            $reminderTime = substr((string) ($user['daily_reminder_time'] ?? '18:00:00'), 0, 5);
            [$rh, $rm] = array_map('intval', explode(':', $reminderTime . ':0'));
            if (isBeforeDeadlineAndAfterTarget($localNow, $userDate, $rh, $rm, $deadlineHour) && shouldSendPushNotification($uid, 'daily_reminder', $userDate)) {
                if (acquireLock('daily_reminder', $userDate, $uid)) {
                    try {
                        $title = 'Time for your daily check-in';
                        $body = $mode === 'easy'
                            ? 'One check-in counts today — open the 77-Day Challenge and show up.'
                            : 'Keep your streak alive — open the 77-Day Challenge and finish today\'s items.';
                        $r = sendPushToUser($uid, $title, $body, '/challenge/app/dashboard.php');
                        if (($r['sent'] ?? 0) > 0) {
                            recordPushNotificationSent($uid, 'daily_reminder', $userDate);
                        }
                        $sent += (int) ($r['sent'] ?? 0);
                        $failed += (int) ($r['failed'] ?? 0);
                    } finally {
                        releaseLock('daily_reminder', $userDate, $uid);
                    }
                }
            }
        }

        if (
            !$isComplete
            && (int) ($user['streak_risk_enabled'] ?? 1) === 1
            && isBeforeDeadlineAndAfterTarget($localNow, $userDate, STREAK_RISK_LOCAL_HOUR, STREAK_RISK_LOCAL_MINUTE, $deadlineHour)
        ) {
            $streak = dbFetchOne("SELECT current_streak FROM user_streaks WHERE user_id = ?", [$uid]);
            $days = (int) ($streak['current_streak'] ?? 0);
            if ($days > 0 && shouldSendPushNotification($uid, 'streak_risk', $userDate)) {
                if (acquireLock('streak_risk', $userDate, $uid)) {
                    try {
                        if ($mode === 'easy') {
                            $body = $days > 1
                                ? "Your {$days}-day streak ends at {$deadlineLabel} — one item is enough to keep it."
                                : "Check in with at least one item before {$deadlineLabel} to keep going.";
                        } else {
                            $body = $days > 1
                                ? "Your {$days}-day streak ends at {$deadlineLabel} if you miss today."
                                : "Complete today's checklist before {$deadlineLabel} to keep going.";
                        }
                        $r = sendPushToUser($uid, 'Streak at risk!', $body, '/challenge/app/dashboard.php');
                        if (($r['sent'] ?? 0) > 0) {
                            recordPushNotificationSent($uid, 'streak_risk', $userDate);
                        }
                        $sent += (int) ($r['sent'] ?? 0);
                        $failed += (int) ($r['failed'] ?? 0);
                    } finally {
                        releaseLock('streak_risk', $userDate, $uid);
                    }
                }
            }
        }
    }
} while (count($users) === BATCH_SIZE);

echo "Push cron done. sent={$sent} failed={$failed}\n";
exit($failed > 0 && $sent === 0 ? 1 : 0);
