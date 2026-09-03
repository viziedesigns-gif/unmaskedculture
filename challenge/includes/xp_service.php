<?php
/**
 * Calm Points (XP) service for the 77-Day Challenge.
 */

require_once __DIR__ . '/functions.php';

const XP_ITEM_CHECK = 10;
const XP_WATER_GOAL = 15;
const XP_MOOD_LOG = 10;
const XP_DAY_COUNTED = 25;
const XP_FULL_CHECKLIST = 40;
const XP_LEVEL_STEP = 500;

const XP_MILESTONE_BONUS = [
    7 => 50,
    30 => 100,
    77 => 200,
];

function ensureXpTablesAndColumns(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'challenge_mode' => "ALTER TABLE users ADD COLUMN challenge_mode VARCHAR(20) NOT NULL DEFAULT 'intermediate' AFTER streak_repairs",
        'calm_points' => "ALTER TABLE users ADD COLUMN calm_points INT NOT NULL DEFAULT 0 AFTER challenge_mode",
        'total_calm_points' => "ALTER TABLE users ADD COLUMN total_calm_points INT NOT NULL DEFAULT 0 AFTER calm_points",
    ];

    foreach ($columns as $column => $sql) {
        if (function_exists('userColumnExists') && userColumnExists($column)) {
            continue;
        }
        try {
            dbQuery($sql);
        } catch (Exception $e) {
            error_log("ensureXpTablesAndColumns failed for $column: " . $e->getMessage());
        }
    }

    try {
        dbQuery(
            "CREATE TABLE IF NOT EXISTS user_xp_events (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                points INT NOT NULL,
                user_date DATE NOT NULL,
                meta TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_type_day (user_id, event_type, user_date),
                INDEX idx_user_date (user_id, user_date),
                CONSTRAINT fk_xp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Exception $e) {
        error_log('ensureXpTablesAndColumns table failed: ' . $e->getMessage());
    }
}

function normalizeChallengeMode(?string $mode): string {
    return $mode === 'easy' ? 'easy' : 'intermediate';
}

function getUserChallengeMode(int $userId): string {
    ensureXpTablesAndColumns();
    $row = dbFetchOne("SELECT challenge_mode FROM users WHERE id = ?", [$userId]);
    return normalizeChallengeMode($row['challenge_mode'] ?? 'intermediate');
}

function getCompletionRuleVersionForMode(string $mode): int {
    return normalizeChallengeMode($mode) === 'easy' ? 2 : 1;
}

function getCalmLevel(int $totalPoints): int {
    return max(1, (int) floor($totalPoints / XP_LEVEL_STEP) + 1);
}

/**
 * @return array{awarded:int,total:int,level:int,events:array<int,array{type:string,points:int}>}
 */
function getXpSummary(int $userId): array {
    ensureXpTablesAndColumns();
    $row = dbFetchOne(
        "SELECT calm_points, total_calm_points FROM users WHERE id = ?",
        [$userId]
    );
    $total = (int) ($row['total_calm_points'] ?? 0);
    return [
        'awarded' => 0,
        'current' => (int) ($row['calm_points'] ?? 0),
        'total' => $total,
        'level' => getCalmLevel($total),
        'events' => [],
    ];
}

/**
 * Award XP once per (user, event_type, user_date) when $uniquePerDay is true.
 * @return array{awarded:int,total:int,level:int,events:array<int,array{type:string,points:int}>}
 */
function awardCalmPoints(
    int $userId,
    string $eventType,
    int $points,
    string $userDate,
    bool $uniquePerDay = true,
    ?array $meta = null
): array {
    ensureXpTablesAndColumns();
    $summary = getXpSummary($userId);
    $summary['events'] = [];

    if ($points === 0) {
        return $summary;
    }

    if ($uniquePerDay) {
        $existing = dbFetchOne(
            "SELECT id, points FROM user_xp_events WHERE user_id = ? AND event_type = ? AND user_date = ?",
            [$userId, $eventType, $userDate]
        );
        if ($existing) {
            return $summary;
        }
    }

    try {
        if ($uniquePerDay) {
            dbQuery(
                "INSERT INTO user_xp_events (user_id, event_type, points, user_date, meta, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$userId, $eventType, $points, $userDate, $meta ? json_encode($meta) : null]
            );
        } else {
            // Non-unique events still log; use a unique suffix in event_type for item checks.
            dbQuery(
                "INSERT INTO user_xp_events (user_id, event_type, points, user_date, meta, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE points = VALUES(points), meta = VALUES(meta)",
                [$userId, $eventType, $points, $userDate, $meta ? json_encode($meta) : null]
            );
        }
    } catch (Exception $e) {
        // Duplicate unique key = already awarded
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return $summary;
        }
        error_log('awardCalmPoints insert failed: ' . $e->getMessage());
        return $summary;
    }

    applyCalmPointsDelta($userId, $points);
    $summary = getXpSummary($userId);
    $summary['awarded'] = $points;
    $summary['events'] = [['type' => $eventType, 'points' => $points]];
    return $summary;
}

function applyCalmPointsDelta(int $userId, int $delta): void {
    ensureXpTablesAndColumns();
    if ($delta > 0) {
        dbQuery(
            "UPDATE users
             SET calm_points = calm_points + ?,
                 total_calm_points = total_calm_points + ?
             WHERE id = ?",
            [$delta, $delta, $userId]
        );
        return;
    }

    // Never let lifetime go negative; floor current at 0.
    dbQuery(
        "UPDATE users
         SET calm_points = GREATEST(0, calm_points + ?),
             total_calm_points = GREATEST(0, total_calm_points + ?)
         WHERE id = ?",
        [$delta, $delta, $userId]
    );
}

/**
 * Spend from the wallet only. Lifetime total_calm_points (and level) stay unchanged.
 * Atomic: fails if balance is insufficient.
 */
function spendCalmPoints(int $userId, int $amount): bool {
    ensureXpTablesAndColumns();
    if ($amount <= 0) {
        return false;
    }
    $result = dbQuery(
        "UPDATE users
         SET calm_points = calm_points - ?
         WHERE id = ? AND calm_points >= ?",
        [$amount, $userId, $amount]
    );
    return $result->rowCount() > 0;
}

/**
 * Item check/uncheck XP. Uses event_type item_check_{id} unique per day.
 * @return array{awarded:int,total:int,level:int,events:array<int,array{type:string,points:int}>}
 */
function awardItemCheckXp(int $userId, int $itemId, bool $checked, string $userDate): array {
    ensureXpTablesAndColumns();
    $eventType = 'item_check_' . $itemId;
    $existing = dbFetchOne(
        "SELECT id, points FROM user_xp_events WHERE user_id = ? AND event_type = ? AND user_date = ?",
        [$userId, $eventType, $userDate]
    );

    if ($checked) {
        if ($existing) {
            return getXpSummary($userId);
        }
        return awardCalmPoints($userId, $eventType, XP_ITEM_CHECK, $userDate, true, ['item_id' => $itemId]);
    }

    // Uncheck: remove today's award for this item if present
    if (!$existing) {
        return getXpSummary($userId);
    }

    $points = (int) $existing['points'];
    dbQuery(
        "DELETE FROM user_xp_events WHERE id = ?",
        [(int) $existing['id']]
    );
    applyCalmPointsDelta($userId, -$points);
    $summary = getXpSummary($userId);
    $summary['awarded'] = -$points;
    $summary['events'] = [['type' => $eventType, 'points' => -$points]];
    return $summary;
}

/**
 * Award day-level bonuses after a day newly counts.
 * @return array{awarded:int,total:int,level:int,events:array<int,array{type:string,points:int}>}
 */
function awardDayCompletionXp(int $userId, string $userDate, int $completedCount, int $requiredCount, int $newStreak = 0): array {
    $events = [];
    $awarded = 0;

    $day = awardCalmPoints($userId, 'day_counted', XP_DAY_COUNTED, $userDate, true);
    if (($day['awarded'] ?? 0) > 0) {
        $awarded += $day['awarded'];
        $events = array_merge($events, $day['events']);
    }

    if ($completedCount >= $requiredCount) {
        $full = awardCalmPoints($userId, 'full_checklist', XP_FULL_CHECKLIST, $userDate, true);
        if (($full['awarded'] ?? 0) > 0) {
            $awarded += $full['awarded'];
            $events = array_merge($events, $full['events']);
        }
    }

    if ($newStreak > 0 && isset(XP_MILESTONE_BONUS[$newStreak])) {
        $ms = awardCalmPoints(
            $userId,
            'milestone_' . $newStreak,
            XP_MILESTONE_BONUS[$newStreak],
            $userDate,
            true
        );
        if (($ms['awarded'] ?? 0) > 0) {
            $awarded += $ms['awarded'];
            $events = array_merge($events, $ms['events']);
        }
    }

    $summary = getXpSummary($userId);
    $summary['awarded'] = $awarded;
    $summary['events'] = $events;
    return $summary;
}

/**
 * Merge multiple XP result arrays for API responses.
 * @param array<int, array> $parts
 * @return array{awarded:int,total:int,level:int,current:int,events:array}
 */
function mergeXpResults(array $parts): array {
    $merged = [
        'awarded' => 0,
        'current' => 0,
        'total' => 0,
        'level' => 1,
        'events' => [],
    ];
    foreach ($parts as $part) {
        if (!$part) {
            continue;
        }
        $merged['awarded'] += (int) ($part['awarded'] ?? 0);
        $merged['current'] = (int) ($part['current'] ?? $merged['current']);
        $merged['total'] = (int) ($part['total'] ?? $merged['total']);
        $merged['level'] = (int) ($part['level'] ?? $merged['level']);
        if (!empty($part['events'])) {
            $merged['events'] = array_merge($merged['events'], $part['events']);
        }
    }
    return $merged;
}

/**
 * Honesty stats: today's checks + most-skipped required items over recent days.
 * @return array{today_done:array,today_skipped:array,most_skipped:array}
 */
function getHonestyStats(int $userId, string $userDate, int $lookbackDays = 14): array {
    $items = dbFetchAll(
        "SELECT id, name, icon, is_required
         FROM daily_checklist_items
         WHERE active = 1 AND is_required = 1
         ORDER BY sort_order"
    );

    $checkedToday = dbFetchAll(
        "SELECT item_id FROM daily_checklist_entries
         WHERE user_id = ? AND user_date = ? AND value = 1",
        [$userId, $userDate]
    );
    $checkedIds = array_map('intval', array_column($checkedToday, 'item_id'));

    $todayDone = [];
    $todaySkipped = [];
    foreach ($items as $item) {
        $row = ['id' => (int) $item['id'], 'name' => $item['name'], 'icon' => $item['icon']];
        if (in_array((int) $item['id'], $checkedIds, true)) {
            $todayDone[] = $row;
        } else {
            $todaySkipped[] = $row;
        }
    }

    $skipCounts = [];
    foreach ($items as $item) {
        $skipCounts[(int) $item['id']] = [
            'id' => (int) $item['id'],
            'name' => $item['name'],
            'icon' => $item['icon'],
            'skipped' => 0,
            'days' => 0,
        ];
    }

    try {
        $start = (new DateTime($userDate))->modify('-' . max(1, $lookbackDays - 1) . ' days')->format('Y-m-d');
    } catch (Exception $e) {
        $start = $userDate;
    }

    // Count days in range where the user had any activity (completion or checks)
    $activeDates = dbFetchAll(
        "SELECT DISTINCT user_date FROM (
            SELECT user_date FROM daily_checklist_entries WHERE user_id = ? AND user_date BETWEEN ? AND ?
            UNION
            SELECT user_date FROM user_daily_completion WHERE user_id = ? AND user_date BETWEEN ? AND ?
         ) d",
        [$userId, $start, $userDate, $userId, $start, $userDate]
    );

    foreach ($activeDates as $dateRow) {
        $d = $dateRow['user_date'];
        $dayChecks = dbFetchAll(
            "SELECT item_id FROM daily_checklist_entries
             WHERE user_id = ? AND user_date = ? AND value = 1",
            [$userId, $d]
        );
        $dayIds = array_map('intval', array_column($dayChecks, 'item_id'));
        foreach ($skipCounts as $itemId => &$info) {
            $info['days']++;
            if (!in_array($itemId, $dayIds, true)) {
                $info['skipped']++;
            }
        }
        unset($info);
    }

    usort($skipCounts, static function ($a, $b) {
        return $b['skipped'] <=> $a['skipped'];
    });

    $mostSkipped = array_values(array_filter($skipCounts, static function ($row) {
        return $row['skipped'] > 0;
    }));
    $mostSkipped = array_slice($mostSkipped, 0, 3);

    return [
        'today_done' => $todayDone,
        'today_skipped' => $todaySkipped,
        'most_skipped' => $mostSkipped,
    ];
}
