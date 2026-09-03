<?php
/**
 * API: Save Mood/Journal Entry
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

// Mood/Journal item ID is 4
$moodItemId = 4;

$wasComplete = $previousStatus['is_today_completed'];

// Check if this is an external journal entry. External journalers still
// track mood in the app; they just do not save written notes here.
$externalJournal = isset($input['external_journal']) ? (bool) $input['external_journal'] : false;

// Save mood for both in-app and external journal modes.
$moodLevel = (int) ($input['mood_level'] ?? 0);
$notes = $externalJournal ? '' : trim($input['notes'] ?? '');

if ($moodLevel < 1 || $moodLevel > 10) {
    jsonResponse(['success' => false, 'error' => 'Invalid mood level (1-10)']);
}

if (!$externalJournal && $notes === '') {
    jsonResponse(['success' => false, 'error' => 'Please write a journal entry before submitting.']);
}

// Save mood entry (upsert)
dbQuery(
    "INSERT INTO mood_entries (user_id, user_date, mood_level, notes, created_at_utc) 
     VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE mood_level = VALUES(mood_level), notes = VALUES(notes)",
    [$userId, $userDate, $moodLevel, $notes]
);

// Mark the journal checklist item as complete
upsertChecklistEntry($userId, $moodItemId, $userDate, true);

$xpParts = [
    awardItemCheckXp($userId, $moodItemId, true, $userDate),
    awardCalmPoints($userId, 'mood_log', XP_MOOD_LOG, $userDate, true),
];

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

// Get updated streak status
$status = getStreakStatus($userId, false, $userDate);
$dayJustCompleted = !$wasComplete && $status['is_today_completed'];
$status['xp'] = mergeXpResults($xpParts);

jsonResponse([
    'success' => true,
    'mood_level' => $moodLevel,
    'mood_saved' => true,
    'streak' => $status,
    'xp' => $status['xp'],
    'day_just_completed' => $dayJustCompleted
]);
