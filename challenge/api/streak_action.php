<?php
/**
 * Streak Action API
 * Handles streak repair and restart actions
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/xp_service.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = getCurrentUserId();
$action = $_POST['action'] ?? '';
$redirectTo = trim((string) ($_POST['redirect'] ?? '/challenge/app/dashboard.php'));
if ($redirectTo === '' || strpos($redirectTo, '/challenge/') !== 0) {
    $redirectTo = '/challenge/app/dashboard.php';
}

// Get current streak status
$streakStatus = getStreakStatus($userId);
$userDate = $streakStatus['user_date'];

switch ($action) {
    case 'repair':
        // Use a streak repair to save the streak
        if (!$streakStatus['can_repair']) {
            setFlash('error', 'Unable to use streak repair at this time.');
            redirect('/challenge/app/dashboard.php');
        }

        $pdo = getDbConnection();
        $missedDate = $streakStatus['missed_date'];
        $repairsRemaining = 0;

        try {
            $pdo->beginTransaction();

            $user = dbFetchOne(
                "SELECT first_name, streak_repairs FROM users WHERE id = ? FOR UPDATE",
                [$userId]
            );
            $streak = dbFetchOne(
                "SELECT freeze_used_on_date FROM user_streaks WHERE user_id = ? FOR UPDATE",
                [$userId]
            );

            if (!$user || ((int) ($user['streak_repairs'] ?? 0)) <= 0) {
                $pdo->rollBack();
                setFlash('error', 'You have no streak repairs available.');
                redirect('/challenge/app/dashboard.php');
            }

            if (!empty($streak['freeze_used_on_date']) && $streak['freeze_used_on_date'] === $missedDate) {
                $pdo->rollBack();
                setFlash('info', 'This missed day has already been repaired.');
                redirect('/challenge/app/dashboard.php');
            }

            $deduct = dbQuery(
                "UPDATE users SET streak_repairs = streak_repairs - 1 WHERE id = ? AND streak_repairs > 0",
                [$userId]
            );

            if ($deduct->rowCount() !== 1) {
                $pdo->rollBack();
                setFlash('error', 'Unable to use streak repair at this time.');
                redirect('/challenge/app/dashboard.php');
            }

            dbQuery(
                "UPDATE user_streaks SET freeze_used_on_date = ? WHERE user_id = ?",
                [$missedDate, $userId]
            );

            $repairsRemaining = ((int) $user['streak_repairs']) - 1;
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Streak repair failed: " . $e->getMessage());
            setFlash('error', 'Unable to use streak repair at this time.');
            redirect('/challenge/app/dashboard.php');
        }

        // If the user completed today's checklist before choosing repair,
        // finish the day now that the missed date is explicitly covered.
        if (isDayComplete($userId, $userDate)) {
            $newlyCompleted = evaluateAndCompleteDay($userId, $userDate);
            if ($newlyCompleted) {
                updateStreakIfNeeded($userId, $userDate);
            }
        }
        
        // Post system message to user's circles about the streak repair
        try {
            $user = getCurrentUser();
            $circles = dbFetchAll(
                "SELECT circle_id FROM inner_circle_members WHERE user_id = ?",
                [$userId]
            );
            foreach ($circles as $circle) {
                postSystemMessage(
                    $circle['circle_id'],
                    $userId,
                    $user['first_name'] . ' used a streak repair! (' . $repairsRemaining . ' remaining)',
                    'system_milestone'
                );
            }
        } catch (Exception $e) {
            error_log("Streak repair message failed: " . $e->getMessage());
        }
        
        // Mark as handled for today
        $_SESSION['streak_break_handled_' . $userDate] = true;
        
        // Invalidate cached streak status so Settings/Dashboard reflect the repair
        clearStreakSessionCache();
        
        setFlash('success', 'Streak repair used! Complete today to keep your streak going.');
        redirect('/challenge/app/dashboard.php');
        break;
        
    case 'restart':
        ensureXpTablesAndColumns();
        $rawMode = trim((string) ($_POST['challenge_mode'] ?? ''));
        if (!in_array($rawMode, ['easy', 'intermediate'], true)) {
            setFlash('error', 'Choose Easy or Intermediate mode to restart.');
            redirect($redirectTo);
        }
        $mode = normalizeChallengeMode($rawMode);

        // Reset the challenge - reset streak to 0 and update start date
        dbQuery(
            "UPDATE user_streaks SET current_streak = 0, last_completed_date = NULL, freeze_used_on_date = NULL WHERE user_id = ?",
            [$userId]
        );
        
        // Update user's created_at to the user's local restart date so the
        // Settings card never drifts to tomorrow because of server/UTC time.
        // Also reset streak repairs back to 3 and set challenge mode.
        // Lifetime Calm Points are preserved.
        dbQuery(
            "UPDATE users SET created_at = ?, streak_repairs = 3, challenge_mode = ? WHERE id = ?",
            [$userDate . ' 00:00:00', $mode, $userId]
        );

        // Clear only the current local challenge day so Day 1 can be earned
        // cleanly after restart. Older journal/checklist history is preserved.
        dbQuery(
            "DELETE FROM user_daily_completion WHERE user_id = ? AND user_date = ?",
            [$userId, $userDate]
        );
        dbQuery(
            "DELETE FROM daily_checklist_entries WHERE user_id = ? AND user_date = ?",
            [$userId, $userDate]
        );
        dbQuery(
            "DELETE FROM water_log WHERE user_id = ? AND user_date = ?",
            [$userId, $userDate]
        );
        ensureWorkoutLogTable();
        dbQuery(
            "DELETE FROM workout_log WHERE user_id = ? AND user_date = ?",
            [$userId, $userDate]
        );
        dbQuery(
            "DELETE FROM mood_entries WHERE user_id = ? AND user_date = ?",
            [$userId, $userDate]
        );
        // Clear today's XP events only (lifetime totals stay)
        dbQuery(
            "DELETE FROM user_xp_events WHERE user_id = ? AND user_date = ?",
            [$userId, $userDate]
        );
        
        // Mark as handled for today
        $_SESSION['streak_break_handled_' . $userDate] = true;
        
        // Invalidate cached streak status / user so the restart is immediately visible
        clearStreakSessionCache();
        
        $modeLabel = $mode === 'easy' ? 'Easy' : 'Intermediate';
        setFlash('success', "Challenge restarted in {$modeLabel} mode. Complete today to earn Day 1. You have 3 streak repairs.");
        redirect('/challenge/app/dashboard.php');
        break;
        
    default:
        setFlash('error', 'Invalid action.');
        redirect('/challenge/app/dashboard.php');
}
