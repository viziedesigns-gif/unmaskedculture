<?php
/** Authenticated native widget API; never accepts a user ID from the caller. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/widget_service.php';

header('Content-Type: application/json');
header('Cache-Control: private, no-store');
if (!isLoggedIn()) jsonResponse(['success' => false, 'error' => 'Sign in to Kinto'], 401);
$user = getCurrentUser();
if (!$user || empty($user['onboarding_completed'])) {
    jsonResponse(['success' => false, 'error' => 'Finish setting up Kinto'], 403);
}
$_SESSION['widget_csrf'] = $_SESSION['widget_csrf'] ?? bin2hex(random_bytes(32));
$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !is_string($input['csrf'] ?? null)
        || !hash_equals($_SESSION['widget_csrf'], $input['csrf'])) {
        jsonResponse(['success' => false, 'error' => 'Refresh the widget and try again'], 403);
    }
    if (!is_bool($input['checked'] ?? null) || !is_string($input['user_date'] ?? null)) {
        jsonResponse(['success' => false, 'error' => 'Invalid checklist action'], 422);
    }
    $item = dbFetchOne('SELECT * FROM daily_checklist_items WHERE id = ? AND active = 1', [(int) ($input['item_id'] ?? 0)]);
    if (!$item || !widgetCanToggle($item)) {
        jsonResponse(['success' => false, 'error' => 'Log this activity in Kinto'], 422);
    }
    try {
        $date = resolveChecklistDate($user['timezone'] ?? DEFAULT_TIMEZONE, $input['user_date'], null,
            checklistAllowsGracePeriod(normalizeChallengeMode($user['challenge_mode'] ?? null)))['selected_date'];
        // Explicit desired state makes repeated taps idempotent. Reuse streak and XP rules.
        toggleChecklistItem($userId, (int) $item['id'], $input['checked'], $date);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
    }
} elseif ($method !== 'GET') {
    header('Allow: GET, POST');
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}
jsonResponse(widgetSnapshot($userId, $user));
