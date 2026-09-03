<?php
/**
 * Retention helpers: reminders, milestones, weekly insights.
 */

require_once __DIR__ . '/streak_service.php';
require_once __DIR__ . '/push_service.php';

function ensureRetentionColumns(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'daily_reminder_enabled' => "ALTER TABLE users ADD COLUMN daily_reminder_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER chat_bubble_color",
        'daily_reminder_time' => "ALTER TABLE users ADD COLUMN daily_reminder_time TIME NOT NULL DEFAULT '18:00:00' AFTER daily_reminder_enabled",
        'streak_risk_enabled' => "ALTER TABLE users ADD COLUMN streak_risk_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER daily_reminder_time",
    ];

    foreach ($columns as $column => $sql) {
        if (userColumnExists($column)) {
            continue;
        }
        try {
            dbQuery($sql);
        } catch (Exception $e) {
            error_log("ensureRetentionColumns failed for $column: " . $e->getMessage());
        }
    }

    if (file_exists(__DIR__ . '/xp_service.php')) {
        require_once __DIR__ . '/xp_service.php';
        ensureXpTablesAndColumns();
    }
}

function hasRetentionColumns(): bool {
    ensureRetentionColumns();
    return userColumnExists('daily_reminder_enabled')
        && userColumnExists('daily_reminder_time')
        && userColumnExists('streak_risk_enabled');
}

/**
 * Sync user-level notification prefs onto all push subscriptions.
 */
function syncPushSubscriptionPrefs(int $userId, ?int $dailyReminder = null, ?int $streakRisk = null): void {
    if (!function_exists('pushTablesReady') || !pushTablesReady()) {
        return;
    }

    $user = dbFetchOne(
        "SELECT daily_reminder_enabled, streak_risk_enabled FROM users WHERE id = ?",
        [$userId]
    );
    if (!$user) {
        return;
    }

    $daily = $dailyReminder ?? (int) ($user['daily_reminder_enabled'] ?? 1);
    $risk = $streakRisk ?? (int) ($user['streak_risk_enabled'] ?? 1);

    dbQuery(
        "UPDATE push_subscriptions
         SET daily_reminder_enabled = ?, streak_risk_enabled = ?
         WHERE user_id = ?",
        [$daily, $risk, $userId]
    );
}

/**
 * @return array<string, mixed>|null
 */
function getMilestoneCelebration(int $userId, int $currentStreak, string $userDate): ?array {
    $mode = function_exists('getUserChallengeMode')
        ? getUserChallengeMode($userId)
        : 'intermediate';

    $milestones = [
        7 => ['title' => 'One week strong!', 'message' => 'You hit a 7-day streak. Keep the rhythm going.'],
        30 => ['title' => '30 days of discipline!', 'message' => 'A full month on the challenge. That is real momentum.'],
        77 => ['title' => 'Challenge complete!', 'message' => 'You finished all 77 days. Celebrate this win.'],
    ];

    if ($mode === 'easy') {
        $milestones[7] = ['title' => 'One week of showing up!', 'message' => 'Seven days of progress — honesty and momentum.'];
        $milestones[30] = ['title' => '30 days of showing up!', 'message' => 'A full month of Easy mode. Keep building.'];
        $milestones[77] = [
            'title' => 'Easy mode complete!',
            'message' => 'You finished 77 days on Easy. Ready for Intermediate — the full daily protocol?',
            'prompt_intermediate' => true,
        ];
    }

    if (!isset($milestones[$currentStreak])) {
        return null;
    }

    if (function_exists('pushTablesReady') && pushTablesReady()) {
        $existing = dbFetchOne(
            "SELECT id FROM push_notification_log WHERE user_id = ? AND notification_type = ? AND user_date = ?",
            [$userId, 'milestone_' . $currentStreak, $userDate]
        );
        if ($existing) {
            return null;
        }
    } elseif (!empty($_SESSION['milestone_seen_' . $currentStreak])) {
        return null;
    }

    return $milestones[$currentStreak] + ['day' => $currentStreak, 'challenge_mode' => $mode];
}

function dismissMilestoneCelebration(int $userId, int $day, string $userDate): void {
    if (function_exists('recordPushNotificationSent')) {
        recordPushNotificationSent($userId, 'milestone_' . $day, $userDate);
    }
    $_SESSION['milestone_seen_' . $day] = true;
}

/**
 * Weekly recap for Insights.
 *
 * @return array<string, mixed>
 */
function getWeeklyInsightSummary(int $userId, string $timezone, int $waterGoal): array {
    $endDate = computeUserDate($timezone);
    $startDate = (new DateTime($endDate))->modify('-6 days')->format('Y-m-d');

    $moodRows = dbFetchAll(
        "SELECT mood_level FROM mood_entries
         WHERE user_id = ? AND user_date BETWEEN ? AND ?",
        [$userId, $startDate, $endDate]
    );
    $moodCount = count($moodRows);
    $avgMood = $moodCount > 0
        ? round(array_sum(array_column($moodRows, 'mood_level')) / $moodCount, 1)
        : null;

    $completedDays = dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM user_daily_completion
         WHERE user_id = ? AND user_date BETWEEN ? AND ?",
        [$userId, $startDate, $endDate]
    );

    $waterGoal = max(1, $waterGoal);
    $waterRows = dbFetchAll(
        "SELECT user_date, COALESCE(SUM(oz_amount), 0) AS total_oz
         FROM water_log
         WHERE user_id = ? AND user_date BETWEEN ? AND ?
         GROUP BY user_date",
        [$userId, $startDate, $endDate]
    );
    $waterGoalDays = 0;
    foreach ($waterRows as $row) {
        if ((int) $row['total_oz'] >= $waterGoal) {
            $waterGoalDays++;
        }
    }

    $streakStatus = getStreakStatus($userId, false);

    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'days_logged' => $moodCount,
        'avg_mood' => $avgMood,
        'completed_days' => (int) ($completedDays['c'] ?? 0),
        'water_goal_days' => $waterGoalDays,
        'water_days_logged' => count($waterRows),
        'current_streak' => (int) ($streakStatus['current_streak'] ?? 0),
    ];
}

function formatReminderTimeDisplay(?string $time): string {
    $time = trim((string) $time);
    if ($time === '') {
        $time = '18:00:00';
    }
    try {
        return DateTime::createFromFormat('H:i:s', $time)?->format('g:i A')
            ?? DateTime::createFromFormat('H:i', substr($time, 0, 5))?->format('g:i A')
            ?? '6:00 PM';
    } catch (Exception $e) {
        return '6:00 PM';
    }
}
