<?php
/**
 * Settings Hub
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/settings_handlers.php';
require_once __DIR__ . '/../includes/settings_layout.php';
require_once __DIR__ . '/../includes/avatar_render.php';

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

$userLocalDate = computeUserDate($user['timezone'] ?? DEFAULT_TIMEZONE);
$cacheKey = 'streak_status_' . $userLocalDate;
if (isset($_SESSION[$cacheKey])) {
    $streakStatus = $_SESSION[$cacheKey];
} else {
    $streakStatus = getStreakStatus($userId);
    $_SESSION[$cacheKey] = $streakStatus;
}

$subtitles = getSettingsHubSubtitles($user);
$canRestart = canRestartChallenge($streakStatus);
$currentStreak = (int) ($streakStatus['current_streak'] ?? 0);
$dayNumber = min(77, $currentStreak);
$pushSubscriptionCount = getUserPushSubscriptionCount($userId);

$pageTitle = 'Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="profile-page settings-hub-page">
    <div class="page-header">
        <h1>Settings</h1>
    </div>

    <?php renderSettingsAlerts($error, $success); ?>

    <section class="settings-install-checklist" id="installedAppChecklist" data-push-enabled="<?= $pushSubscriptionCount > 0 ? '1' : '0' ?>" hidden>
        <div class="settings-install-checklist__header">
            <span class="settings-install-checklist__icon" aria-hidden="true"><i data-lucide="smartphone"></i></span>
            <div>
                <h2>Installed App Setup</h2>
                <p>Finish these two device settings for the best app experience.</p>
            </div>
        </div>
        <div class="settings-install-checklist__items">
            <a class="settings-install-checklist__item" data-check-item="notifications" href="/challenge/app/settings/notifications.php">
                <span class="settings-install-checklist__status" data-check-status="notifications" aria-hidden="true"><i data-lucide="circle"></i></span>
                <span>
                    <strong>Turn on app notifications</strong>
                    <small>Enable reminders and streak alerts on this device.</small>
                </span>
                <i data-lucide="chevron-right" aria-hidden="true"></i>
            </a>
            <a class="settings-install-checklist__item" data-check-item="tilt" href="/challenge/app/settings/appearance.php">
                <span class="settings-install-checklist__status" data-check-status="tilt" aria-hidden="true"><i data-lucide="circle"></i></span>
                <span>
                    <strong>Turn on water tilt</strong>
                    <small>Allow motion controls for the water tracker.</small>
                </span>
                <i data-lucide="chevron-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <div class="settings-hub-header">
        <div class="settings-hub-avatar">
            <?= renderUserPublicFace($user, 'sm') ?>
        </div>
        <div class="settings-hub-identity">
            <h2><?= h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h2>
            <p><?= h($user['email']) ?></p>
            <p class="settings-hub-meta">
                Day <?= $dayNumber ?> of 77 <span aria-hidden="true">&middot;</span> <?= $currentStreak ?>-day streak <span aria-hidden="true">&middot;</span> <?= (int) ($streakStatus['streak_repairs'] ?? 0) ?> repairs
            </p>
            <a href="/challenge/app/member_profile.php?id=<?= (int) $userId ?>" class="btn btn-sm btn-secondary">
                <i data-lucide="user"></i> View Profile
            </a>
        </div>
    </div>

    <?php renderSettingsHubSection('Account', function () use ($subtitles) {
        renderSettingsHubRow('/challenge/app/settings/profile.php', 'user', 'Profile Information', $subtitles['profile']);
        renderSettingsHubRow('/challenge/app/avatar.php', 'smile', 'Avatar Studio', $subtitles['avatar'] ?? 'Dress your character');
        renderSettingsHubRow('/challenge/app/settings/shop.php', 'sparkles', 'Calm Shop', $subtitles['shop']);
        renderSettingsHubRow('/challenge/app/settings/password.php', 'lock', 'Change Password', $subtitles['password']);
    }); ?>

    <?php renderSettingsHubSection('Challenge', function () use ($subtitles, $canRestart) {
        renderSettingsHubRow('/challenge/app/settings/health.php', 'activity', 'Health & Water', $subtitles['health']);
        renderSettingsHubRow('/challenge/app/settings/journal.php', 'book-open', 'Journal Settings', $subtitles['journal']);
        if ($canRestart) {
            renderSettingsHubRow('/challenge/app/settings/account.php#restart', 'rotate-ccw', 'Challenge Status', 'Restart your challenge');
        }
    }); ?>

    <?php renderSettingsHubSection('App', function () use ($subtitles) {
        renderSettingsHubRow('/challenge/app/settings/circles.php', 'users', 'Circles & Feed', $subtitles['circles'] ?? 'Manage circles, members, invites');
        renderSettingsHubRow('/challenge/app/settings/notifications.php', 'bell', 'Notifications', $subtitles['notifications']);
        renderSettingsHubRow('/challenge/app/settings/appearance.php', 'palette', 'Appearance', $subtitles['appearance']);
        renderSettingsHubRow('/challenge/app/settings/install.php', 'smartphone', 'Download App', $subtitles['install']);
    }); ?>

    <?php renderSettingsHubSection('Support', function () use ($subtitles) {
        renderSettingsHubRow('/challenge/app/settings/app_updates.php', 'sparkles', 'App Updates', $subtitles['app_updates'] ?? 'Latest features and fixes');
        renderSettingsHubRow('/challenge/app/settings/videos.php', 'circle-play', 'Unmasked Culture Productions', $subtitles['videos'] ?? 'Watch productions inside the app');
        renderSettingsHubRow('/challenge/app/settings/podcast.php', 'podcast', 'The Unmasked Podcast', $subtitles['podcast'] ?? 'Listen with the in-app player');
        renderSettingsHubRow('/challenge/app/settings/resources.php', 'life-buoy', 'Mental Health Resources', $subtitles['resources']);
        renderSettingsHubRow('/challenge/app/settings/support.php', 'heart', 'Support Kinto', $subtitles['support']);
    }); ?>

    <?php if (isCurrentUserSuperAdmin()): ?>
        <?php renderSettingsHubSection('Super Admin', function () {
            renderSettingsHubRow('/challenge/app/admin/', 'shield-check', 'Admin Console', 'Users, app stats, resets, and push tools');
        }); ?>
    <?php endif; ?>

    <?php renderSettingsHubSection('Account & Data', function () use ($subtitles) {
        renderSettingsHubRow('/challenge/app/settings/suggestion_support.php', 'message-square', 'Suggestion and Support', $subtitles['suggestion_support']);
        renderSettingsHubRow('/challenge/app/settings/account.php', 'shield', 'Account & Data', $subtitles['account'], 'danger');
    }); ?>

    <div class="settings-hub-footer">
        <a href="/challenge/app/logout.php" class="btn btn-danger btn-block">
            <i data-lucide="log-out"></i> Sign Out
        </a>
    </div>
</div>

<script>
(function () {
    const checklist = document.getElementById('installedAppChecklist');
    if (!checklist) return;

    const isInstalled = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true
        || document.referrer.startsWith('android-app://');

    if (!isInstalled) return;

    const setStatus = (name, complete) => {
        const status = checklist.querySelector(`[data-check-status="${name}"]`);
        const item = checklist.querySelector(`[data-check-item="${name}"]`);
        if (!status) return;
        status.classList.toggle('is-complete', complete);
        status.innerHTML = complete ? '<i data-lucide="check-circle-2"></i>' : '<i data-lucide="circle"></i>';
        if (item) {
            item.hidden = complete;
        }
    };

    const notificationsComplete = checklist.dataset.pushEnabled === '1';
    setStatus('notifications', notificationsComplete);

    let tiltComplete = false;
    try {
        tiltComplete = localStorage.getItem('waterTiltEnabled') === '1';
    } catch (error) {
        tiltComplete = false;
    }
    setStatus('tilt', tiltComplete);

    checklist.hidden = notificationsComplete && tiltComplete;

    if (window.lucide) {
        window.lucide.createIcons();
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
