<?php
/**
 * API: Log Water Intake
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/xp_service.php';

header('Content-Type: application/json');

// Require authentication
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

// Require POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$amount = (int) ($input['amount'] ?? 0);

if ($amount < 1 || $amount > 128) {
    jsonResponse(['success' => false, 'error' => 'Invalid water amount (1-128 oz)']);
}

$userId = getCurrentUserId();
$user = getCurrentUser();
$timezone = $user['timezone'] ?? DEFAULT_TIMEZONE;
try {
    $allowGrace = checklistAllowsGracePeriod(normalizeChallengeMode($user['challenge_mode'] ?? null));
    $userDate = resolveChecklistDate($timezone, $input['user_date'] ?? null, null, $allowGrace)['selected_date'];
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
}
$previousStatus = getStreakStatus($userId, false, $userDate);
$waterGoal = (int) ($user['daily_water_oz'] ?? 64);

// Water item ID is 1
$waterItemId = 1;

$wasComplete = $previousStatus['is_today_completed'];
$xpParts = [];

// Log the water
dbQuery(
    "INSERT INTO water_log (user_id, user_date, oz_amount, logged_at_utc) 
     VALUES (?, ?, ?, UTC_TIMESTAMP())",
    [$userId, $userDate, $amount]
);

// Calculate new total
$waterTotal = dbFetchOne(
    "SELECT COALESCE(SUM(oz_amount), 0) as total 
     FROM water_log 
     WHERE user_id = ? AND user_date = ?",
    [$userId, $userDate]
);
$waterProgress = (int) ($waterTotal['total'] ?? 0);
$waterPercent = min(100, round(($waterProgress / $waterGoal) * 100));
$waterComplete = $waterProgress >= $waterGoal;

// If water goal is met, mark the water checklist item as complete
if ($waterComplete) {
    upsertChecklistEntry($userId, $waterItemId, $userDate, true);
    $xpParts[] = awardItemCheckXp($userId, $waterItemId, true, $userDate);
    $xpParts[] = awardCalmPoints($userId, 'water_goal', XP_WATER_GOAL, $userDate, true);
    
    // Check if this completes the day
    $newlyCompleted = evaluateAndCompleteDay($userId, $userDate);
    if ($newlyCompleted) {
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

// Get updated streak status
$status = getStreakStatus($userId, false, $userDate);
$dayJustCompleted = !$wasComplete && $status['is_today_completed'];
$status['xp'] = mergeXpResults($xpParts);

jsonResponse([
    'success' => true,
    'water_progress' => $waterProgress,
    'water_goal' => $waterGoal,
    'water_percent' => $waterPercent,
    'water_complete' => $waterComplete,
    'streak' => $status,
    'xp' => $status['xp'],
    'day_just_completed' => $dayJustCompleted
]);
