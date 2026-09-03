<?php
/**
 * API: Toggle Checklist Item
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

$itemId = (int) ($input['item_id'] ?? 0);
$checked = (bool) ($input['checked'] ?? false);

if ($itemId < 1) {
    jsonResponse(['success' => false, 'error' => 'Invalid item ID']);
}

// Validate item exists and is not water/mood type (those have special handlers)
$item = dbFetchOne("SELECT * FROM daily_checklist_items WHERE id = ? AND active = 1", [$itemId]);

if (!$item) {
    jsonResponse(['success' => false, 'error' => 'Item not found']);
}

if (in_array($item['item_type'], ['water_tracker', 'mood_tracker', 'weight_tracker'])) {
    jsonResponse(['success' => false, 'error' => 'Use the specific API for this item type']);
}

$userId = getCurrentUserId();
$user = getCurrentUser();
try {
    $allowGrace = checklistAllowsGracePeriod(normalizeChallengeMode($user['challenge_mode'] ?? null));
    $userDate = resolveChecklistDate($user['timezone'] ?? DEFAULT_TIMEZONE, $input['user_date'] ?? null, null, $allowGrace)['selected_date'];
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
}

// Get previous streak status
$previousStatus = getStreakStatus($userId, false, $userDate);
$wasComplete = $previousStatus['is_today_completed'];

// Toggle the item
$status = toggleChecklistItem($userId, $itemId, $checked, $userDate);

// Check if day just became complete
$dayJustCompleted = !$wasComplete && $status['is_today_completed'];

jsonResponse([
    'success' => true,
    'item_id' => $itemId,
    'checked' => $checked,
    'streak' => $status,
    'xp' => $status['xp'] ?? getXpSummary($userId),
    'day_just_completed' => $dayJustCompleted
]);
