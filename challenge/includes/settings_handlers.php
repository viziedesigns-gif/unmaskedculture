<?php
/**
 * Shared POST handlers for settings sub-pages.
 */

require_once __DIR__ . '/push_service.php';
require_once __DIR__ . '/retention_service.php';
require_once __DIR__ . '/shop_service.php';

/**
 * Process a settings form submission. Redirects and exits on success.
 *
 * @param int $userId
 * @param array $user
 * @return array{0: string, 1: string} [$error, $success]
 */
function processSettingsRequest(int $userId, array $user): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['', ''];
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        return handleSettingsProfileUpdate($userId, $user);
    }

    if ($action === 'update_health') {
        return handleSettingsHealthUpdate($userId);
    }

    if ($action === 'update_journal') {
        return handleSettingsJournalUpdate($userId);
    }

    if ($action === 'update_password') {
        return handleSettingsPasswordUpdate($userId);
    }

    if ($action === 'delete_account') {
        return handleSettingsDeleteAccount($userId);
    }

    if ($action === 'update_reminders') {
        return handleSettingsRemindersUpdate($userId);
    }

    if ($action === 'update_appearance') {
        return handleSettingsAppearanceUpdate($userId);
    }

    if ($action === 'dismiss_milestone') {
        return handleDismissMilestone($userId, $user);
    }

    if ($action === 'buy_shop_item' || $action === 'equip_shop_item' || $action === 'unequip_shop_item') {
        return handleSettingsShopAction($userId, $action);
    }

    if ($action === 'update_profile_typography') {
        return handleSettingsProfileTypography($userId);
    }

    return ['Unknown action.', ''];
}

function handleSettingsProfileUpdate(int $userId, array $user): array {
    $postedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $sessionToken = is_string($_SESSION['profile_csrf_token'] ?? null) ? $_SESSION['profile_csrf_token'] : '';
    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        return ['Your profile form expired. Please refresh and try again.', ''];
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $timezone = $_POST['timezone'] ?? DEFAULT_TIMEZONE;
    $profileBio = trim($_POST['profile_bio'] ?? '');
    $promptKey = $_POST['profile_prompt_key'] ?? 'motivation';
    $promptAnswer = trim($_POST['profile_prompt_answer'] ?? '');
    $profileVisible = isset($_POST['profile_visible']) ? 1 : 0;
    $profileBannerX = max(0, min(100, (int) ($_POST['profile_banner_x'] ?? 50)));
    $profileBannerY = max(0, min(100, (int) ($_POST['profile_banner_y'] ?? 50)));
    $profileBannerZoom = max(1, min(2.5, (float) ($_POST['profile_banner_zoom'] ?? 1)));
    $profileBannerTextColor = strtolower(trim((string) ($_POST['profile_banner_text_color'] ?? ($user['profile_banner_text_color'] ?? ''))));
    $profilePromptOptions = getProfilePromptOptions();

    if (empty($firstName) || empty($lastName)) {
        return ['Please enter your first and last name', ''];
    }
    if (!array_key_exists($promptKey, $profilePromptOptions)) {
        return ['Please choose a valid profile prompt', ''];
    }
    if (strlen($profileBio) > 500) {
        return ['Bio must be 500 characters or less', ''];
    }
    if (strlen($promptAnswer) > 500) {
        return ['Prompt answer must be 500 characters or less', ''];
    }
    if ($profileBannerTextColor !== '' && !preg_match('/^#[0-9a-f]{6}$/', $profileBannerTextColor)) {
        return ['Choose a valid banner text color', ''];
    }

    $updateData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'timezone' => $timezone,
        'profile_bio' => $profileBio,
        'profile_prompt_key' => $promptKey,
        'profile_prompt_answer' => $promptAnswer,
        'profile_visible' => $profileVisible,
        'profile_banner_x' => $profileBannerX,
        'profile_banner_y' => $profileBannerY,
        'profile_banner_zoom' => $profileBannerZoom,
        'profile_banner_text_color' => $profileBannerTextColor !== '' ? $profileBannerTextColor : null,
    ];
    if ($profileVisible && empty($user['public_profile_slug'])) {
        $updateData['public_profile_slug'] = generatePublicProfileSlug();
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        [$uploadSuccess, $result] = handleProfilePicUpload($_FILES['profile_pic'], $userId);
        if ($uploadSuccess) {
            $updateData['profile_pic'] = $result;
        } else {
            return [$result, ''];
        }
    }

    if (!updateUserProfile($userId, $updateData)) {
        return ['Profile changes could not be saved. Please try again.', ''];
    }
    clearStreakSessionCache();
    settingsRedirectSuccess('/challenge/app/settings/profile.php', 'Profile updated successfully');
}

function handleSettingsHealthUpdate(int $userId): array {
    $heightFeet = intval($_POST['height_feet'] ?? 0);
    $heightInches = intval($_POST['height_inches'] ?? 0);
    $bottleMode = $_POST['bottle_mode'] ?? 'preset';

    if ($bottleMode === 'custom') {
        $bottleOz = intval($_POST['water_bottle_custom'] ?? $_POST['water_bottle_oz'] ?? 24);
    } else {
        $bottleOz = intval($_POST['water_bottle_oz'] ?? 24);
    }

    $totalInches = ($heightFeet * 12) + $heightInches;

    if ($totalInches < 36 || $totalInches > 96) {
        return ['Please enter a valid height', ''];
    }
    if ($bottleOz < 1 || $bottleOz > 128) {
        return ['Bottle size must be between 1 and 128 oz', ''];
    }

    $currentHealth = dbFetchOne(
        "SELECT weight_lbs, daily_water_oz FROM users WHERE id = ?",
        [$userId]
    ) ?: [];
    $weightLbs = (float) ($currentHealth['weight_lbs'] ?? 0);
    $dailyWater = (int) ($currentHealth['daily_water_oz'] ?? 64);
    $updates = [
        'height_inches' => $totalInches,
        'water_bottle_oz' => $bottleOz,
    ];

    if ($weightLbs >= 50 && $weightLbs <= 700) {
        $dailyWater = calculateDailyWater($weightLbs);
        $updates['bmi'] = calculateBMI($weightLbs, $totalInches);
        $updates['daily_water_oz'] = $dailyWater;
        saveWeightEntry($userId, computeUserDate(getUserTimezone($userId)), $weightLbs, $totalInches);
    }

    updateUserProfile($userId, $updates);

    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }

    settingsRedirectSuccess(
        '/challenge/app/settings/health.php',
        'Health stats updated. Water goal: ' . $dailyWater . ' oz, bottle: ' . $bottleOz . ' oz'
    );
}

function handleSettingsJournalUpdate(int $userId): array {
    $journalInApp = isset($_POST['journal_in_app']) && $_POST['journal_in_app'] === '1' ? 1 : 0;
    updateUserProfile($userId, ['journal_in_app' => $journalInApp]);
    settingsRedirectSuccess('/challenge/app/settings/journal.php', 'Journal settings updated');
}

function handleSettingsPasswordUpdate(int $userId): array {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        return ['New passwords do not match', ''];
    }

    [$passSuccess, $passResult] = updatePassword($userId, $currentPassword, $newPassword);
    if (!$passSuccess) {
        return [$passResult, ''];
    }

    settingsRedirectSuccess('/challenge/app/settings/password.php', $passResult);
}

function handleSettingsDeleteAccount(int $userId): array {
    $confirmText = trim($_POST['delete_confirm'] ?? '');

    if ($confirmText !== 'DELETE ACCOUNT') {
        return ['Type DELETE ACCOUNT to confirm account deletion.', ''];
    }

    [$deleteSuccess, $deleteMessage] = deleteUserAccount($userId);
    if ($deleteSuccess) {
        logoutUser();
        redirect('/kinto?status=account-deleted#signin');
    }

    return [$deleteMessage, ''];
}

function handleSettingsRemindersUpdate(int $userId): array {
    $dailyReminder = isset($_POST['daily_reminder_enabled']) ? 1 : 0;
    $streakRisk = isset($_POST['streak_risk_enabled']) ? 1 : 0;
    $reminderTime = trim($_POST['daily_reminder_time'] ?? '18:00');

    if (!preg_match('/^\d{2}:\d{2}$/', $reminderTime)) {
        return ['Please choose a valid reminder time.', ''];
    }

    updateUserProfile($userId, [
        'daily_reminder_enabled' => $dailyReminder,
        'streak_risk_enabled' => $streakRisk,
        'daily_reminder_time' => $reminderTime . ':00',
    ]);
    syncPushSubscriptionPrefs($userId, $dailyReminder, $streakRisk);
    settingsRedirectSuccess('/challenge/app/settings/notifications.php', 'Notification preferences updated');
}

function handleSettingsAppearanceUpdate(int $userId): array {
    $newColor = $_POST['bubble_color'] ?? '#1C1917';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $newColor)) {
        return ['Please choose a valid color.', ''];
    }

    updateUserProfile($userId, ['chat_bubble_color' => $newColor]);
    settingsRedirectSuccess('/challenge/app/settings/appearance.php', 'Appearance updated');
}

function handleDismissMilestone(int $userId, array $user): array {
    $day = (int) ($_POST['milestone_day'] ?? 0);
    if (!in_array($day, [7, 30, 77], true)) {
        return ['Invalid milestone.', ''];
    }

    $userDate = computeUserDate($user['timezone'] ?? DEFAULT_TIMEZONE);
    dismissMilestoneCelebration($userId, $day, $userDate);
    redirect('/challenge/app/dashboard.php');
}

function settingsRedirectSuccess(string $url, string $message): void {
    setFlash('success', $message);
    redirect($url);
}

function handleSettingsProfileTypography(int $userId): array {
    $postedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $sessionToken = is_string($_SESSION['shop_csrf_token'] ?? null) ? $_SESSION['shop_csrf_token'] : '';
    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        return ['Your customization form expired. Please refresh and try again.', ''];
    }

    $color = strtolower(trim((string) ($_POST['profile_banner_text_color'] ?? '')));
    $allowedColors = ['', '#ffffff', '#173b37', '#0f766e', '#f5d98b'];
    if (!in_array($color, $allowedColors, true)) {
        return ['Choose a valid font color.', ''];
    }

    if (!updateUserProfile($userId, ['profile_banner_text_color' => $color !== '' ? $color : null])) {
        return ['Your font color could not be saved. Please try again.', ''];
    }

    settingsRedirectSuccess('/challenge/app/settings/shop.php?tab=typography', 'Font color updated.');
}

function handleSettingsShopAction(int $userId, string $action): array {
    $postedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $sessionToken = is_string($_SESSION['shop_csrf_token'] ?? null) ? $_SESSION['shop_csrf_token'] : '';
    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        return ['Your shop form expired. Please refresh and try again.', ''];
    }

    $itemId = trim((string) ($_POST['item_id'] ?? ''));
    $tab = trim((string) ($_POST['tab'] ?? 'background'));
    $allowedTabs = array_keys(getShopCategoryLabels());
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = SHOP_CATEGORY_BACKGROUND;
    }
    $redirectUrl = '/challenge/app/settings/shop.php?tab=' . rawurlencode($tab);

    if ($itemId === '' || !isValidShopItemId($itemId)) {
        return ['Please choose a valid shop item.', ''];
    }

    if ($action === 'buy_shop_item') {
        $result = purchaseShopItem($userId, $itemId);
        if (!$result['ok']) {
            return [$result['error'], ''];
        }
        $item = getShopItem($itemId);
        settingsRedirectSuccess($redirectUrl, 'Purchased ' . ($item['name'] ?? 'item') . '!');
    }

    if ($action === 'equip_shop_item') {
        $result = equipShopItem($userId, $itemId);
        if (!$result['ok']) {
            return [$result['error'], ''];
        }
        $item = getShopItem($itemId);
        settingsRedirectSuccess($redirectUrl, 'Equipped ' . ($item['name'] ?? 'item') . '.');
    }

    if ($action === 'unequip_shop_item') {
        $result = unequipShopItem($userId, $itemId);
        if (!$result['ok']) {
            return [$result['error'], ''];
        }
        $item = getShopItem($itemId);
        settingsRedirectSuccess($redirectUrl, 'Unequipped ' . ($item['name'] ?? 'item') . '.');
    }

    return ['Unknown shop action.', ''];
}

/**
 * Build subtitle strings for the settings hub list.
 *
 * @param array $user
 * @return array<string, string>
 */
function getSettingsHubSubtitles(array $user): array {
    $heightFeet = floor(($user['height_inches'] ?? 66) / 12);
    $heightInches = ($user['height_inches'] ?? 66) % 12;
    $pushCount = getUserPushSubscriptionCount((int) $user['id']);
    $spendable = (int) ($user['calm_points'] ?? 0);

    return [
        'profile' => 'Name, bio, photo, timezone',
        'avatar' => !empty($user['avatar_public_face']) ? 'Avatar is your public face' : 'Customize your character',
        'shop' => number_format($spendable) . ' Calm Points to spend',
        'health' => ($user['weight_lbs'] ?? null)
            ? (int) $user['weight_lbs'] . ' lbs Â· ' . (int) ($user['water_bottle_oz'] ?? 24) . ' oz bottle'
            : 'Weight, height, water goal',
        'journal' => ($user['journal_in_app'] ?? 1) ? 'Inside the app' : 'Outside the app',
        'notifications' => (($user['daily_reminder_enabled'] ?? 1) ? formatReminderTimeDisplay($user['daily_reminder_time'] ?? '18:00:00') . ' reminder' : 'Reminders off')
            . ' Â· ' . ($pushCount > 0 ? $pushCount . ' device' . ($pushCount === 1 ? '' : 's') : 'push off'),
        'appearance' => 'Chat bubble color, haptics, and water tilt',
        'circles' => 'Manage circles, members, invites',
        'password' => 'Update your password',
        'install' => 'Add to home screen',
        'resources' => 'Videos, podcasts, crisis help, newsletter',
        'support' => 'Donate to Kinto',
        'app_updates' => 'Latest features and fixes',
        'videos' => 'Watch productions inside the app',
        'podcast' => 'Listen with the in-app player',
        'suggestion_support' => 'Send feedback, ideas, or support needs',
        'account' => 'Restart or delete account',
    ];
}

/**
 * Whether the user can restart the challenge from settings.
 *
 * @param array $streakStatus
 * @return bool
 */
function canRestartChallenge(array $streakStatus): bool {
    return ((int) ($streakStatus['current_streak'] ?? 0) === 0)
        || !empty($streakStatus['streak_broken'])
        || !empty($streakStatus['streak_lost']);
}
