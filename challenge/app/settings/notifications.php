<?php
$pageTitle = 'Notifications';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';

$reminderTimeValue = substr((string) ($user['daily_reminder_time'] ?? '18:00:00'), 0, 5);
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Notifications'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <div class="settings-card settings-detail-card">
        <form method="POST" class="settings-detail-form">
            <input type="hidden" name="action" value="update_reminders">
            <h3>Reminder Schedule</h3>
            <p class="form-hint">Reminders use your profile timezone (<?= h($user['timezone'] ?? DEFAULT_TIMEZONE) ?>).</p>
            <label class="checkbox-large profile-visible-choice">
                <input type="checkbox" name="daily_reminder_enabled" value="1" <?= (($user['daily_reminder_enabled'] ?? 1) ? 'checked' : '') ?>>
                <span class="checkbox-label">Daily check-in reminder</span>
            </label>
            <div class="form-group">
                <label for="daily_reminder_time">Reminder time</label>
                <input type="time" id="daily_reminder_time" name="daily_reminder_time" value="<?= h($reminderTimeValue) ?>">
            </div>
            <label class="checkbox-large profile-visible-choice">
                <input type="checkbox" name="streak_risk_enabled" value="1" <?= (($user['streak_risk_enabled'] ?? 1) ? 'checked' : '') ?>>
                <span class="checkbox-label">Streak-at-risk alert (9:00 PM local time)</span>
            </label>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Preferences</button>
            </div>
        </form>
    </div>

    <div class="settings-card settings-detail-card notification-card">
        <div class="settings-card-header">
            <h3>Push Notifications</h3>
            <span class="notification-status" id="pushStatusBadge">
                <?= $pushSubscriptionCount > 0 ? 'Enabled on ' . $pushSubscriptionCount . ' device' . ($pushSubscriptionCount === 1 ? '' : 's') : 'Not enabled' ?>
            </span>
        </div>
        <div class="notification-card-body">
            <p class="notification-copy">Enable push on this device to receive your reminders and streak alerts.</p>
            <?php if (!$pushStatus['has_vapid_keys'] || !$pushStatus['has_web_push_library'] || !$pushStatus['has_database_table']): ?>
                <div class="alert alert-warning notification-config-warning">
                    Push setup is not finished on the server yet.
                </div>
            <?php endif; ?>
            <div class="notification-actions">
                <button type="button" class="btn btn-primary" id="enablePushBtn" onclick="enablePushNotifications()">
                    <i data-lucide="bell"></i> Enable Notifications
                </button>
                <button type="button" class="btn btn-secondary" id="testPushBtn" onclick="sendTestPush()" <?= $pushSubscriptionCount > 0 ? '' : 'disabled' ?>>
                    <i data-lucide="send"></i> Send Test
                </button>
                <button type="button" class="btn btn-secondary" id="disablePushBtn" onclick="disablePushNotifications()" <?= $pushSubscriptionCount > 0 ? '' : 'disabled' ?>>
                    <i data-lucide="bell-off"></i> Disable This Device
                </button>
            </div>
            <p class="form-hint" id="pushHelpText">
                iPhone users may need to add this app to the Home Screen before notifications can be enabled.
            </p>
        </div>
    </div>
</div>

<script src="<?= h(assetUrl('/challenge/assets/js/settings.js')) ?>"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
