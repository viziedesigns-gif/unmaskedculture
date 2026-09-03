<?php
/**
 * API: Log 30-minute workout activity
 * 77-Day Challenge App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/xp_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$workoutType = trim((string) ($input['workout_type'] ?? ''));
$customWorkout = trim((string) ($input['workout_custom'] ?? ''));
$durationMinutes = (int) ($input['duration_minutes'] ?? 30);

$options = getWorkoutTypeOptions();
if (!array_key_exists($workoutType, $options)) {
    jsonResponse(['success' => false, 'error' => 'Choose a workout type']);
}

if ($workoutType === 'custom') {
    if ($customWorkout === '') {
        jsonResponse(['success' => false, 'error' => 'Enter your custom workout']);
    }
    if (strlen($customWorkout) > 100) {
        jsonResponse(['success' => false, 'error' => 'Custom workout must be 100 characters or fewer']);
    }
} else {
    $customWorkout = '';
}

if ($durationMinutes < 1 || $durationMinutes > 240) {
    jsonResponse(['success' => false, 'error' => 'Workout duration must be between 1 and 240 minutes']);
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
$workoutItemId = 3;

$previousStatus = getStreakStatus($userId, false, $userDate);
$wasComplete = $previousStatus['is_today_completed'];

ensureWorkoutLogTable();

dbQuery(
    "INSERT INTO workout_log (
        user_id, user_date, workout_type, workout_custom, duration_minutes, logged_at_utc, updated_at_utc
     ) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE
        workout_type = VALUES(workout_type),
        workout_custom = VALUES(workout_custom),
        duration_minutes = VALUES(duration_minutes),
        updated_at_utc = UTC_TIMESTAMP()",
    [$userId, $userDate, $workoutType, $customWorkout !== '' ? $customWorkout : null, $durationMinutes]
);

upsertChecklistEntry($userId, $workoutItemId, $userDate, true);

$xpParts = [awardItemCheckXp($userId, $workoutItemId, true, $userDate)];

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

$status = getStreakStatus($userId, false, $userDate);
$dayJustCompleted = !$wasComplete && $status['is_today_completed'];
$status['xp'] = mergeXpResults($xpParts);

jsonResponse([
    'success' => true,
    'item_id' => $workoutItemId,
    'checked' => true,
    'workout' => [
        'type' => $workoutType,
        'label' => getWorkoutTypeLabel($workoutType, $customWorkout),
        'custom' => $customWorkout,
        'duration_minutes' => $durationMinutes
    ],
    'streak' => $status,
    'xp' => $status['xp'],
    'day_just_completed' => $dayJustCompleted
]);
