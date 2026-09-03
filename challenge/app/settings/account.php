<?php
$pageTitle = 'Account & Data';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Account & Data'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <?php if (isCurrentUserAdmin()): ?>
    <div class="settings-card settings-detail-card admin-tools-card">
        <div class="settings-card-header">
            <h3>Admin Tools</h3>
        </div>
        <div class="admin-tools-body">
            <p>Send push notifications to every device that has notifications enabled.</p>
            <a href="/challenge/app/admin_notifications.php" class="btn btn-primary">
                <i data-lucide="send"></i> Open Notifications
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canRestart): ?>
    <div class="settings-card settings-detail-card challenge-status-card" id="restart">
        <div class="settings-card-header">
            <h3>Challenge Status</h3>
        </div>
        <div class="challenge-status-body">
            <div class="challenge-status-icon">
                <i data-lucide="rotate-ccw"></i>
            </div>
            <?php if (!empty($streakStatus['streak_broken'])): ?>
                <h4>Your streak is broken</h4>
                <p>You missed a day and don't have a repair available. Restart to begin Day 1 fresh with 3 new streak repairs.</p>
            <?php elseif ((int) $streakStatus['current_streak'] === 0): ?>
                <h4>No active streak</h4>
                <p>Ready for a fresh start? Restart the challenge to reset your start date and get 3 fresh streak repairs.</p>
            <?php else: ?>
                <h4>Streak lost</h4>
                <p>Restart to begin Day 1 with 3 fresh streak repairs. Your journal history will be preserved.</p>
            <?php endif; ?>
            <button type="button" class="btn btn-danger" onclick="openRestartModal()">
                <i data-lucide="refresh-cw"></i> Restart Challenge
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="settings-card settings-detail-card danger-zone-card">
        <div class="settings-card-header">
            <h3>Delete Account</h3>
        </div>
        <div class="danger-zone-body">
            <p>Permanently delete your account, journal entries, challenge history, messages, and settings.</p>
            <button type="button" class="btn btn-danger" onclick="openDeleteAccountModal()">
                <i data-lucide="trash-2"></i> Delete Account
            </button>
        </div>
    </div>
</div>

<?php if ($canRestart): ?>
<div id="restartModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Restart the 77-Day Challenge?</h3>
            <button type="button" class="modal-close" onclick="closeRestartModal()">&times;</button>
        </div>
        <form method="POST" action="/challenge/api/streak_action.php" id="restartForm">
            <input type="hidden" name="action" value="restart">
            <input type="hidden" name="redirect" value="/challenge/app/settings/account.php">
            <div class="modal-body">
                <p><strong>This will reset your current streak to 0 and set your challenge start date to today.</strong></p>
                <ul class="restart-bullets">
                    <li>Your streak repairs will be reset to <strong>3</strong>.</li>
                    <li>Your journal entries and mood history will be <strong>kept</strong>.</li>
                    <li>Your Calm Points lifetime total is <strong>kept</strong>.</li>
                    <li>Your daily completion history is preserved for reference.</li>
                </ul>
                <fieldset class="mode-select-fieldset">
                    <legend>Start in which mode?</legend>
                    <label class="mode-radio">
                        <input type="radio" name="challenge_mode" value="easy" required>
                        <span><strong>Easy</strong> â€” 1+ items advances the day</span>
                    </label>
                    <label class="mode-radio">
                        <input type="radio" name="challenge_mode" value="intermediate" checked>
                        <span><strong>Intermediate</strong> â€” all items required</span>
                    </label>
                </fieldset>
                <div class="form-group">
                    <label for="restartConfirm">Type <strong>RESTART</strong> to confirm:</label>
                    <input type="text" id="restartConfirm" autocomplete="off"
                           oninput="validateRestartConfirm(this.value)"
                           placeholder="RESTART">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRestartModal()">Cancel</button>
                <button type="submit" id="restartSubmitBtn" class="btn btn-danger" disabled>
                    <i data-lucide="refresh-cw"></i> Restart Challenge
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="deleteAccountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Account?</h3>
            <button type="button" class="modal-close" onclick="closeDeleteAccountModal()">&times;</button>
        </div>
        <form method="POST" id="deleteAccountForm">
            <input type="hidden" name="action" value="delete_account">
            <div class="modal-body">
                <p><strong>This permanently removes your Kinto account and cannot be undone.</strong></p>
                <ul class="restart-bullets">
                    <li>Your journal entries and mood history will be deleted.</li>
                    <li>Your challenge streaks, checklist history, and water logs will be deleted.</li>
                    <li>Your circles, messages, profile, and notification subscriptions will be deleted.</li>
                </ul>
                <div class="form-group">
                    <label for="deleteAccountConfirm">Type <strong>DELETE ACCOUNT</strong> to confirm:</label>
                    <input type="text" id="deleteAccountConfirm" name="delete_confirm" autocomplete="off"
                           oninput="validateDeleteAccountConfirm(this.value)"
                           placeholder="DELETE ACCOUNT">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteAccountModal()">Cancel</button>
                <button type="submit" id="deleteAccountSubmitBtn" class="btn btn-danger" disabled>
                    <i data-lucide="trash-2"></i> Delete Account
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/challenge/assets/js/settings.js?v=1.0"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
