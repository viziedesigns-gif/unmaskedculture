<?php
$pageTitle = 'Journal Settings';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Journal Settings'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <div class="settings-card settings-detail-card">
        <form method="POST" class="settings-detail-form">
            <input type="hidden" name="action" value="update_journal">

            <div class="form-group">
                <label>Where do you journal?</label>
                <div class="radio-options">
                    <label class="radio-option">
                        <input type="radio" name="journal_in_app" value="1"
                               <?= ($user['journal_in_app'] ?? 1) ? 'checked' : '' ?>>
                        <span class="radio-label">Inside the app</span>
                        <span class="radio-desc">Track mood (1-10 scale) and write journal entries</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="journal_in_app" value="0"
                               <?= !($user['journal_in_app'] ?? 1) ? 'checked' : '' ?>>
                        <span class="radio-label">Outside the app</span>
                        <span class="radio-desc">Track mood in-app, write journal entries somewhere else</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
