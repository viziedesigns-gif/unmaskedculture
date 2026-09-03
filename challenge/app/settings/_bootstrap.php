<?php
/**
 * Shared bootstrap for settings sub-pages.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/streak_service.php';
require_once __DIR__ . '/../../includes/settings_handlers.php';

requireOnboarding();

$user = getCurrentUser();
$userId = getCurrentUserId();
$error = '';
$success = '';

$flash = getFlash();
if ($flash) {
    if ($flash['type'] === 'error') {
        $error = $flash['message'];
    } else {
        $success = $flash['message'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$postError, $postSuccess] = processSettingsRequest($userId, $user);
    if ($postError !== '') {
        $error = $postError;
    }
    if ($postSuccess !== '') {
        $success = $postSuccess;
    }
    $user = getCurrentUser();
}

$userLocalDate = computeUserDate($user['timezone'] ?? DEFAULT_TIMEZONE);
$cacheKey = 'streak_status_' . $userLocalDate;
if (isset($_SESSION[$cacheKey])) {
    $streakStatus = $_SESSION[$cacheKey];
} else {
    $streakStatus = getStreakStatus($userId);
    $_SESSION[$cacheKey] = $streakStatus;
}

$timezones = getTimezoneList();
$profilePromptOptions = getProfilePromptOptions();
$pushStatus = getPushConfigStatus();
$pushSubscriptionCount = getUserPushSubscriptionCount($userId);
$canRestart = canRestartChallenge($streakStatus);
$heightFeet = floor(($user['height_inches'] ?? 66) / 12);
$heightInches = ($user['height_inches'] ?? 66) % 12;
