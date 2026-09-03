<?php
$pageTitle = 'Suggestion and Support';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';

if (empty($_SESSION['feedback_csrf_token'])) {
    $_SESSION['feedback_csrf_token'] = bin2hex(random_bytes(32));
}

$feedbackCsrfToken = $_SESSION['feedback_csrf_token'];
$firstName = trim((string) ($user['first_name'] ?? ''));
$lastName = trim((string) ($user['last_name'] ?? ''));
$email = trim((string) ($user['email'] ?? ''));

include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Suggestion and Support'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <section class="settings-card settings-detail-card">
        <div class="settings-card-header">
            <h3>Send a Note</h3>
        </div>
        <p>Share an app idea, a rough edge, or something you need help with.</p>

        <form method="POST" action="/challenge/api/submit-feedback.php" class="settings-detail-form">
            <input type="hidden" name="csrf_token" value="<?= h($feedbackCsrfToken) ?>">
            <input type="hidden" name="feedback_type" value="App Suggestion">

            <div class="form-group">
                <label for="feedback_first_name">First Name</label>
                <input type="text" id="feedback_first_name" name="first_name" value="<?= h($firstName) ?>" maxlength="100" autocomplete="given-name" required>
            </div>

            <div class="form-group">
                <label for="feedback_last_name">Last Name</label>
                <input type="text" id="feedback_last_name" name="last_name" value="<?= h($lastName) ?>" maxlength="100" autocomplete="family-name" required>
            </div>

            <div class="form-group">
                <label for="feedback_email">Email Address</label>
                <input type="email" id="feedback_email" name="email" value="<?= h($email) ?>" maxlength="254" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="feedback_message">Suggestion or Support Need</label>
                <textarea id="feedback_message" name="feedback" rows="6" maxlength="3000" required placeholder="What should we know?"></textarea>
                <p class="form-hint">This goes to the Kinto team through Formcan.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="send"></i> Send Feedback
                </button>
            </div>
        </form>
    </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
