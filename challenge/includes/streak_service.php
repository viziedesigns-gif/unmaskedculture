<?php
/**
 * Streak Service
 * Implements daily streak logic per Skill Daily Streak Logic.md specifications
 * 
 * Kinto App
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/xp_service.php';

// Configuration (legacy global fallback; per-user challenge_mode takes precedence)
define('DAILY_COMPLETION_MODE', 'all_required');
define('REQUIRED_ITEMS_COUNT', 7);

/**
 * Upsert a checklist entry for a user
 * @param int $userId
 * @param int $itemId
 * @param string $userDate Y-m-d format
 * @param bool $checked
 */
function upsertChecklistEntry(int $userId, int $itemId, string $userDate, bool $checked): void {
    if ($checked) {
        // Insert or update to checked
        dbQuery(
            "INSERT INTO daily_checklist_entries (user_id, item_id, user_date, checked_at_utc, value) 
             VALUES (?, ?, ?, UTC_TIMESTAMP(), 1)
             ON DUPLICATE KEY UPDATE checked_at_utc = UTC_TIMESTAMP(), value = 1",
            [$userId, $itemId, $userDate]
        );
    } else {
        // Remove the entry (or set value to 0)
        dbQuery(
            "DELETE FROM daily_checklist_entries WHERE user_id = ? AND item_id = ? AND user_date = ?",
            [$userId, $itemId, $userDate]
        );
    }
}

/**
 * Get all checklist entries for a user on a specific date
 * @param int $userId
 * @param string $userDate
 * @return array
 */
function getChecklistEntries(int $userId, string $userDate): array {
    return dbFetchAll(
        "SELECT dce.*, dci.name, dci.item_type, dci.icon 
         FROM daily_checklist_entries dce
         JOIN daily_checklist_items dci ON dce.item_id = dci.id
         WHERE dce.user_id = ? AND dce.user_date = ? AND dce.value = 1",
        [$userId, $userDate]
    );
}

/**
 * Get count of completed items for a user on a specific date
 * @param int $userId
 * @param string $userDate
 * @return int
 */
function getCompletedItemsCount(int $userId, string $userDate): int {
    $result = dbFetchOne(
        "SELECT COUNT(*) as count
         FROM daily_checklist_entries dce
         JOIN daily_checklist_items dci ON dce.item_id = dci.id
         WHERE dce.user_id = ?
           AND dce.user_date = ?
           AND dce.value = 1
           AND dci.active = 1
           AND dci.is_required = 1",
        [$userId, $userDate]
    );
    return (int) ($result['count'] ?? 0);
}

/**
 * Get the current number of active required checklist items.
 * @return int
 */
function getRequiredItemsCount(): int {
    ensureWeightTrackingReady();

    $result = dbFetchOne(
        "SELECT COUNT(*) as count
         FROM daily_checklist_items
         WHERE active = 1 AND is_required = 1"
    );
    $count = (int) ($result['count'] ?? 0);
    return $count > 0 ? $count : REQUIRED_ITEMS_COUNT;
}

/**
 * Check if day meets completion condition for the user's challenge mode.
 * Easy: 1+ required items. Intermediate: all required items.
 * @param int $userId
 * @param string $userDate
 * @return bool
 */
function isDayComplete(int $userId, string $userDate): bool {
    $completedCount = getCompletedItemsCount($userId, $userDate);
    $requiredCount = getRequiredItemsCount();
    $mode = getUserChallengeMode($userId);

    if ($mode === 'easy') {
        return $completedCount >= 1;
    }

    return $completedCount >= $requiredCount;
}

/**
 * A missed streak must be explicitly repaired or restarted before today's
 * completion row can be recorded. Without this guard, completing today's
 * checklist after a miss would either silently reset to day 1 or create a
 * completion row that prevents a later repair from advancing the streak.
 * @param int $userId
 * @param string $userDate
 * @return bool
 */
function canRecordCompletionForDate(int $userId, string $userDate): bool {
    $streak = dbFetchOne(
        "SELECT current_streak, last_completed_date, freeze_used_on_date FROM user_streaks WHERE user_id = ?",
        [$userId]
    );

    if (!$streak || empty($streak['last_completed_date'])) {
        return true;
    }

    $lastDate = new DateTime($streak['last_completed_date']);
    $completedDate = new DateTime($userDate);

    if ($completedDate < $lastDate) {
        return false;
    }

    $diffDays = (int) $completedDate->diff($lastDate)->days;
    if ($diffDays <= 1) {
        return true;
    }

    if ($diffDays === 2) {
        $missedDate = (clone $lastDate)->modify('+1 day')->format('Y-m-d');
        return !empty($streak['freeze_used_on_date'])
            && $streak['freeze_used_on_date'] === $missedDate;
    }

    return false;
}

/**
 * Evaluate and complete a day if conditions are met
 * Returns true if day was newly completed
 * @param int $userId
 * @param string $userDate
 * @return bool
 */
function evaluateAndCompleteDay(int $userId, string $userDate): bool {
    if (!isDayComplete($userId, $userDate)) {
        return false;
    }

    if (!canRecordCompletionForDate($userId, $userDate)) {
        return false;
    }
    
    // Check if already completed
    $existing = dbFetchOne(
        "SELECT id FROM user_daily_completion WHERE user_id = ? AND user_date = ?",
        [$userId, $userDate]
    );
    
    if ($existing) {
        return false; // Already completed, not new
    }
    
    $completedCount = getCompletedItemsCount($userId, $userDate);
    $mode = getUserChallengeMode($userId);
    $ruleVersion = getCompletionRuleVersionForMode($mode);
    
    // Insert completion record
    dbQuery(
        "INSERT INTO user_daily_completion (user_id, user_date, completed_at_utc, completion_score, completion_rule_version)
         VALUES (?, ?, UTC_TIMESTAMP(), ?, ?)",
        [$userId, $userDate, $completedCount, $ruleVersion]
    );
    
    return true;
}

/**
 * Update streak based on newly completed day
 * Uses transactions and FOR UPDATE to prevent race conditions
 * @param int $userId
 * @param string $completedUserDate
 */
function updateStreakIfNeeded(int $userId, string $completedUserDate): void {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    
    try {
        // Lock the streak row
        $streak = dbFetchOne(
            "SELECT * FROM user_streaks WHERE user_id = ? FOR UPDATE",
            [$userId]
        );
        
        if (!$streak) {
            // Create streak record if doesn't exist - this is their first day!
            dbQuery(
                "INSERT INTO user_streaks (user_id, current_streak, longest_streak, last_completed_date, streak_updated_at_utc)
                 VALUES (?, 1, 1, ?, UTC_TIMESTAMP())",
                [$userId, $completedUserDate]
            );
            $pdo->commit();
            
            // Post first day celebration to all user's circles
            postFirstDayCelebration($userId);
            return;
        }
        
        $currentStreak = (int) $streak['current_streak'];
        $longestStreak = (int) $streak['longest_streak'];
        $lastCompletedDate = $streak['last_completed_date'];
        
        // Track if this is the first day completion
        $isFirstDay = false;
        
        // Calculate new streak
        if ($lastCompletedDate === null) {
            // First completion ever
            $newStreak = 1;
            $isFirstDay = true;
        } else {
            // Calculate days difference
            $lastDate = new DateTime($lastCompletedDate);
            $completedDate = new DateTime($completedUserDate);
            $diffDays = (int) $completedDate->diff($lastDate)->days;
            
            // Determine direction (should be positive for consecutive days)
            if ($completedDate < $lastDate) {
                // Completing a past date - don't allow
                $pdo->commit();
                return;
            }
            
            if ($diffDays === 0) {
                // Same day - no change needed
                $pdo->commit();
                return;
            } elseif ($diffDays === 1) {
                // Consecutive day - increment streak
                $newStreak = $currentStreak + 1;
            } elseif ($diffDays === 2) {
                // Missed exactly one day. Only treat as covered if the user
                // already explicitly used a streak repair for that missed date
                // via /api/streak_action.php (which sets freeze_used_on_date).
                // NEVER silently consume a repair here.
                $missedDate = (clone $lastDate)->modify('+1 day')->format('Y-m-d');
                $freezeUsedOn = $streak['freeze_used_on_date'] ?? null;

                if ($freezeUsedOn !== null && $freezeUsedOn === $missedDate) {
                    // Explicit repair was recorded for the missed day -> continue streak
                    $newStreak = $currentStreak + 1;
                } else {
                    // No explicit repair -> do not silently reset or restart.
                    // The user must choose repair or restart first.
                    $pdo->commit();
                    return;
                }
            } else {
                // More than one day missed -> the user must restart first.
                $pdo->commit();
                return;
            }
        }
        
        // Update longest streak if necessary
        $newLongestStreak = max($longestStreak, $newStreak);
        
        // Update streak record
        dbQuery(
            "UPDATE user_streaks 
             SET current_streak = ?, 
                 longest_streak = ?, 
                 last_completed_date = ?,
                 streak_updated_at_utc = UTC_TIMESTAMP()
             WHERE user_id = ?",
            [$newStreak, $newLongestStreak, $completedUserDate, $userId]
        );
        
        $pdo->commit();
        
        // Post milestone celebrations after commit. Only fires on upward crossings.
        if ($isFirstDay || ($newStreak > $currentStreak)) {
            postMilestoneCelebration($userId, $currentStreak, $newStreak);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Streak update error for user $userId: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Streak milestones we celebrate in all of the user's Inner Circles.
 * Keys are streak days; values are the celebratory copy.
 */
const STREAK_MILESTONES = [
    1  => 'just completed their first day!',
    7  => 'hit a 7-day streak! One week strong.',
    14 => 'reached a 2-week streak!',
    30 => 'hit 30 days! A full month of showing up.',
    50 => 'hit 50 days! Two-thirds of the way there.',
    77 => 'completed the 77-Day Challenge! 🏆',
];

/**
 * Post milestone celebration(s) for any milestone days crossed going
 * from $previousStreak to $newStreak. Fires once per milestone.
 *
 * @param int $userId
 * @param int $previousStreak   Streak before this update (0 on first day).
 * @param int $newStreak        Streak after this update.
 */
function postMilestoneCelebration(int $userId, int $previousStreak, int $newStreak): void {
    try {
        if ($newStreak <= $previousStreak) {
            return; // No upward crossing -> nothing to celebrate.
        }

        ensureXpTablesAndColumns();
        $user = dbFetchOne("SELECT first_name, challenge_mode FROM users WHERE id = ?", [$userId]);
        if (!$user) return;

        $mode = normalizeChallengeMode($user['challenge_mode'] ?? 'intermediate');
        $circles = dbFetchAll(
            "SELECT circle_id FROM inner_circle_members WHERE user_id = ?",
            [$userId]
        );
        if (empty($circles)) return;

        $easyCopy = [
            1  => 'showed up for day 1 — progress counts!',
            7  => 'hit a 7-day show-up streak!',
            14 => 'reached 2 weeks of showing up!',
            30 => 'hit 30 days of showing up!',
            50 => 'hit 50 days — keep going!',
            77 => 'finished Easy mode (77 days)! Ready for Intermediate?',
        ];

        foreach (STREAK_MILESTONES as $day => $copy) {
            if ($day > $previousStreak && $day <= $newStreak) {
                $text = ($mode === 'easy' && isset($easyCopy[$day])) ? $easyCopy[$day] : $copy;
                $message = $user['first_name'] . ' ' . $text;
                foreach ($circles as $circle) {
                    postSystemMessage(
                        $circle['circle_id'],
                        $userId,
                        $message,
                        'system_milestone'
                    );
                }
            }
        }
    } catch (Exception $e) {
        // Never fail the streak update because a celebration message failed.
        error_log("Milestone celebration failed: " . $e->getMessage());
    }
}

/**
 * Backwards-compatible shim for older callers that posted the day-1 message.
 * Delegates to postMilestoneCelebration so the logic lives in one place.
 * @param int $userId
 */
function postFirstDayCelebration(int $userId): void {
    postMilestoneCelebration($userId, 0, 1);
}

/**
 * Invalidate in-session streak-related caches. Call after any action that
 * mutates streak/user state outside the normal toggle flow (repair, restart).
 */
function clearStreakSessionCache(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    foreach (array_keys($_SESSION) as $key) {
        if (strpos($key, 'streak_status_') === 0
            || strpos($key, 'streak_checked_') === 0
            || strpos($key, 'streak_history_') === 0) {
            unset($_SESSION[$key]);
        }
    }
    // Also clear cached user row if auth provides it.
    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }
}

/**
 * Process a checklist item toggle
 * This is the main entry point for item changes
 * @param int $userId
 * @param int $itemId
 * @param bool $checked
 * @return array Status information
 */
function toggleChecklistItem(int $userId, int $itemId, bool $checked, ?string $requestedDate = null): array {
    expireStreakIfNeeded($userId);

    $timezone = getUserTimezone($userId);
    $allowGrace = checklistAllowsGracePeriod(getUserChallengeMode($userId));
    $userDate = resolveChecklistDate($timezone, $requestedDate, null, $allowGrace)['selected_date'];
    
    // Update the entry
    upsertChecklistEntry($userId, $itemId, $userDate, $checked);

    $xpParts = [awardItemCheckXp($userId, $itemId, $checked, $userDate)];
    
    // Check if day is now complete
    $wasNewlyCompleted = false;
    if ($checked) {
        $wasNewlyCompleted = evaluateAndCompleteDay($userId, $userDate);
        
        if ($wasNewlyCompleted) {
            updateStreakIfNeeded($userId, $userDate);
            $statusPreview = getStreakStatus($userId, false, $userDate);
            $xpParts[] = awardDayCompletionXp(
                $userId,
                $userDate,
                (int) $statusPreview['items_completed'],
                (int) $statusPreview['items_required'],
                (int) $statusPreview['current_streak']
            );
        }
    }
    
    $status = getStreakStatus($userId, false, $userDate);
    $status['xp'] = mergeXpResults($xpParts);
    return $status;
}

/**
 * Expire an active streak after the user has passed their local completion
 * window. A one-day miss with repairs available stays repairable; a one-day
 * miss with no repairs, or any larger gap, is marked lost by zeroing the
 * current streak while preserving last_completed_date for UI context.
 * @param int $userId
 * @param DateTimeImmutable|null $utcNow
 * @return array
 */
function expireStreakIfNeeded(int $userId, ?DateTimeImmutable $utcNow = null): array {
    $timezone = getUserTimezone($userId);
    $userDate = computeUserDate($timezone, $utcNow);
    $mode = getUserChallengeMode($userId);

    // Easy mode: yesterday remains open until 1:00 AM local time. Do not mark
    // it missed while the user can still legitimately complete it.
    // Intermediate: no grace — expire immediately after local midnight.
    if (isChecklistGracePeriod($timezone, $utcNow, checklistAllowsGracePeriod($mode))) {
        return getStreakStatus($userId, false);
    }

    $pdo = getDbConnection();
    $pdo->beginTransaction();

    try {
        $streak = dbFetchOne(
            "SELECT * FROM user_streaks WHERE user_id = ? FOR UPDATE",
            [$userId]
        );

        if (!$streak || empty($streak['last_completed_date'])) {
            $pdo->commit();
            return getStreakStatus($userId, false);
        }

        $todayCompletion = dbFetchOne(
            "SELECT id FROM user_daily_completion WHERE user_id = ? AND user_date = ?",
            [$userId, $userDate]
        );

        if ($todayCompletion) {
            $pdo->commit();
            return getStreakStatus($userId, false);
        }

        $lastDate = new DateTime($streak['last_completed_date']);
        $today = new DateTime($userDate);
        $diffDays = (int) $today->diff($lastDate)->days;
        $currentStreak = (int) ($streak['current_streak'] ?? 0);

        if ($today < $lastDate || $diffDays <= 1 || $currentStreak <= 0) {
            $pdo->commit();
            return getStreakStatus($userId, false);
        }

        $user = dbFetchOne(
            "SELECT streak_repairs FROM users WHERE id = ? FOR UPDATE",
            [$userId]
        );
        $repairs = (int) ($user['streak_repairs'] ?? 0);

        if ($diffDays === 2 && $repairs > 0) {
            // Repairable. Keep the visible streak alive until the user chooses
            // repair or restart.
            $pdo->commit();
            return getStreakStatus($userId, false);
        }

        dbQuery(
            "UPDATE user_streaks
             SET current_streak = 0,
                 streak_updated_at_utc = UTC_TIMESTAMP()
             WHERE user_id = ?",
            [$userId]
        );

        $pdo->commit();
        clearStreakSessionCache();
        return getStreakStatus($userId, false);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Streak expiry error for user $userId: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get comprehensive streak status for a user
 * @param int $userId
 * @return array
 */
function getStreakStatus(int $userId, bool $runExpiry = true, ?string $requestedDate = null): array {
    if ($runExpiry) {
        return expireStreakIfNeeded($userId);
    }

    $timezone = getUserTimezone($userId);
    $userDate = $requestedDate ?? computeUserDate($timezone);
    
    // Get streak data
    $streak = dbFetchOne(
        "SELECT * FROM user_streaks WHERE user_id = ?",
        [$userId]
    );
    
    ensureXpTablesAndColumns();

    // Get user's streak repairs, mode, and Calm Points
    $user = dbFetchOne(
        "SELECT streak_repairs, daily_water_oz, challenge_mode, calm_points, total_calm_points FROM users WHERE id = ?",
        [$userId]
    );
    $mode = normalizeChallengeMode($user['challenge_mode'] ?? 'intermediate');
    $allowGrace = checklistAllowsGracePeriod($mode);
    $inGracePeriod = isChecklistGracePeriod($timezone, null, $allowGrace);
    
    // Check if today is completed
    $todayCompletion = dbFetchOne(
        "SELECT * FROM user_daily_completion WHERE user_id = ? AND user_date = ?",
        [$userId, $userDate]
    );
    
    // Get today's progress
    $completedItems = getCompletedItemsCount($userId, $userDate);
    $requiredItems = getRequiredItemsCount();
    
    // Easy closes at 1:00 AM next day; Intermediate at midnight.
    $timeRemaining = getTimeUntilChecklistDeadline($timezone, $userDate, $mode);
    $totalHoursRemaining = $timeRemaining['hours'] + ($timeRemaining['minutes'] / 60);
    $nextMidnightUtc = getChecklistDeadlineUtcIso($timezone, $userDate, $mode);
    
    // Calculate if streak is at risk (only show warning within 2 hours of midnight)
    $streakAtRisk = false;
    $streakLost = false;
    $streakBroken = false;
    $canRepair = false;
    $neverStarted = false;
    $missedDate = null;
    $diffDays = 0;
    $currentStreakVal = (int) ($streak['current_streak'] ?? 0);
    $lastCompletedDate = $streak['last_completed_date'] ?? null;
    $freezeUsedOn = $streak['freeze_used_on_date'] ?? null;

    // During Easy grace, yesterday is still completable — do not surface
    // lost / repair / restart prompts until after 1:00 AM.
    if (!$inGracePeriod) {
        if ($streak && $lastCompletedDate) {
            $lastDate = new DateTime($lastCompletedDate);
            $today = new DateTime($userDate);
            $diffDays = (int) $today->diff($lastDate)->days;

            // Check if streak is broken (missed at least one day).
            // NOTE: we no longer require current_streak > 0 here — checkStreakContinuity()
            // may have already zeroed it in the DB, but the user still needs to be told
            // their streak is gone and be prompted to repair/restart.
            if ($diffDays >= 2 && !$todayCompletion) {
                $missedDate = (clone $lastDate)->modify('+1 day')->format('Y-m-d');
                $missedDateRepaired = ($diffDays === 2 && $freezeUsedOn === $missedDate);

                if (!$missedDateRepaired) {
                    $streakBroken = true;
                }

                // Can they repair? Only if they missed exactly 1 day, still have a live
                // streak in the DB, and have repairs available.
                if (!$missedDateRepaired && $diffDays === 2 && $currentStreakVal > 0 && $user['streak_repairs'] > 0) {
                    $canRepair = true;
                }
            }

            // Streak at risk warning (within 2 hours of deadline)
            if ($diffDays === 1 && !$todayCompletion && $totalHoursRemaining <= 2) {
                $streakAtRisk = true;
            } elseif ($diffDays > 1 && !$todayCompletion) {
                $missedDateForRisk = (clone $lastDate)->modify('+1 day')->format('Y-m-d');
                $missedDateRepairedForRisk = ($diffDays === 2 && $freezeUsedOn === $missedDateForRisk);
                if ($diffDays === 2 && $missedDateRepairedForRisk && $totalHoursRemaining <= 2) {
                    $streakAtRisk = true;
                } elseif ($diffDays === 2 && !$missedDateRepairedForRisk && $currentStreakVal > 0 && $user['streak_repairs'] > 0 && $totalHoursRemaining <= 2) {
                    $streakAtRisk = true;
                } else if ($diffDays > 2 || ($diffDays === 2 && !$missedDateRepairedForRisk && ($currentStreakVal <= 0 || $user['streak_repairs'] <= 0))) {
                    $streakLost = true;
                }
            }
        } elseif (!$todayCompletion && $currentStreakVal === 0) {
            // User has never completed a day. Fire the "get started / restart"
            // prompt once the account is more than 1 day old so we don't nag on
            // their very first session.
            $userRow = dbFetchOne("SELECT created_at FROM users WHERE id = ?", [$userId]);
            if ($userRow && !empty($userRow['created_at'])) {
                try {
                    $createdDate = new DateTime(substr($userRow['created_at'], 0, 10));
                    $todayDate = new DateTime($userDate);
                    $accountAgeDays = (int) $todayDate->diff($createdDate)->days;
                    if ($accountAgeDays >= 2) {
                        $streakBroken = true;
                        $streakLost = true;
                        $neverStarted = true;
                        $diffDays = $accountAgeDays;
                    }
                } catch (Exception $e) {
                    // ignore bad created_at values
                }
            }
        }
    } elseif ($streak && $lastCompletedDate) {
        // Still compute days gap for status metadata, but keep UI flags clear.
        $lastDate = new DateTime($lastCompletedDate);
        $today = new DateTime($userDate);
        $diffDays = (int) $today->diff($lastDate)->days;
        if ($diffDays >= 2 && !$todayCompletion) {
            $missedDate = (clone $lastDate)->modify('+1 day')->format('Y-m-d');
        }
    }

    $totalPoints = (int) ($user['total_calm_points'] ?? 0);
    $isFullDay = $completedItems >= $requiredItems;
    $dayCounts = (bool) $todayCompletion
        || ($mode === 'easy' ? $completedItems >= 1 : $isFullDay);

    return [
        'current_streak' => $currentStreakVal,
        'longest_streak' => (int) ($streak['longest_streak'] ?? 0),
        'last_completed_date' => $lastCompletedDate,
        'is_today_completed' => (bool) $todayCompletion,
        'items_completed' => $completedItems,
        'items_required' => $requiredItems,
        'is_full_checklist' => $isFullDay,
        'day_counts' => $dayCounts,
        'challenge_mode' => $mode,
        'calm_points' => (int) ($user['calm_points'] ?? 0),
        'total_calm_points' => $totalPoints,
        'calm_level' => getCalmLevel($totalPoints),
        'streak_repairs' => (int) ($user['streak_repairs'] ?? 0),
        'streak_at_risk' => $streakAtRisk,
        'streak_lost' => $streakLost,
        'streak_broken' => $streakBroken,
        'can_repair' => $canRepair,
        'never_started' => $neverStarted,
        'in_grace_period' => $inGracePeriod,
        'missed_date' => $missedDate,
        'days_missed' => $diffDays > 1 ? $diffDays - 1 : 0,
        'user_date' => $userDate,
        'time_remaining' => $timeRemaining,
        'next_midnight_utc' => $nextMidnightUtc,
        'deadline_label' => $mode === 'easy' ? '1:00 AM' : 'midnight',
        'daily_water_oz' => (int) ($user['daily_water_oz'] ?? 64)
    ];
}

/**
 * Check and handle streak breaks at day boundary
 * This should be called when user logs in after a day change
 * @param int $userId
 * @return array Updated streak status
 */
function checkStreakContinuity(int $userId): array {
    return expireStreakIfNeeded($userId);
}

/**
 * Today's checklist progress for a set of users (for feed banner).
 * Numerator includes any checked active items (required + optional);
 * denominator is required-item count.
 *
 * @param array<int> $userIds
 * @param string $userDate
 * @return array<int, array{done:int,required:int,tone:string}>
 */
function getUsersChecklistProgressForDate(array $userIds, string $userDate): array {
    $required = getRequiredItemsCount();
    $result = [];
    foreach ($userIds as $uid) {
        $uid = (int) $uid;
        if ($uid < 1) {
            continue;
        }
        $result[$uid] = ['done' => 0, 'required' => $required, 'tone' => 'empty'];
    }
    if (empty($result)) {
        return $result;
    }

    $placeholders = implode(',', array_fill(0, count($result), '?'));
    $params = array_keys($result);
    $params[] = $userDate;

    $rows = dbFetchAll(
        "SELECT dce.user_id, COUNT(*) AS done
         FROM daily_checklist_entries dce
         JOIN daily_checklist_items dci ON dce.item_id = dci.id
         WHERE dce.user_id IN ($placeholders)
           AND dce.user_date = ?
           AND dce.value = 1
           AND dci.active = 1
         GROUP BY dce.user_id",
        $params
    );

    foreach ($rows as $row) {
        $uid = (int) $row['user_id'];
        $done = (int) ($row['done'] ?? 0);
        $tone = 'empty';
        if ($required > 0 && $done >= $required) {
            $tone = 'complete';
        } elseif ($done >= max(1, (int) ceil($required * 0.5))) {
            $tone = 'good';
        } elseif ($done > 0) {
            $tone = 'partial';
        }
        $result[$uid] = ['done' => $done, 'required' => $required, 'tone' => $tone];
    }

    return $result;
}

/**
 * Get checklist items with completion status for today
 * @param int $userId
 * @return array
 */
function getTodayChecklist(int $userId, ?string $requestedDate = null): array {
    $timezone = getUserTimezone($userId);
    $userDate = $requestedDate ?? computeUserDate($timezone);
    ensureWeightTrackingReady();

    dbQuery(
        "UPDATE daily_checklist_items
         SET name = 'Encourage/Reach out to a Friend'
         WHERE id = 5 AND name = 'Encourage a Friend'"
    );
    
    // Get all active items
    $items = dbFetchAll(
        "SELECT * FROM daily_checklist_items WHERE active = 1 ORDER BY sort_order"
    );
    
    // Get today's entries
    $entries = dbFetchAll(
        "SELECT item_id, value FROM daily_checklist_entries 
         WHERE user_id = ? AND user_date = ?",
        [$userId, $userDate]
    );
    
    $completedItems = [];
    foreach ($entries as $entry) {
        if ($entry['value']) {
            $completedItems[$entry['item_id']] = true;
        }
    }
    
    // Merge completion status
    foreach ($items as &$item) {
        $item['completed'] = isset($completedItems[$item['id']]);
    }
    
    return $items;
}

/**
 * Get streak history for calendar display
 * @param int $userId
 * @param int $days Number of days to look back
 * @return array
 */
function getStreakHistory(int $userId, int $days = 30): array {
    $timezone = getUserTimezone($userId);
    $userDate = computeUserDate($timezone);
    
    $startDate = (new DateTime($userDate))->modify("-$days days")->format('Y-m-d');
    
    $completions = dbFetchAll(
        "SELECT user_date, completion_score 
         FROM user_daily_completion 
         WHERE user_id = ? AND user_date >= ?
         ORDER BY user_date",
        [$userId, $startDate]
    );
    
    $history = [];
    foreach ($completions as $completion) {
        $history[$completion['user_date']] = (int) $completion['completion_score'];
    }
    
    return $history;
}
