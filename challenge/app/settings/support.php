<?php
$pageTitle = 'Support';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Support'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <div class="settings-card settings-detail-card donation-card">
        <div class="donation-content">
            <div class="donation-icon">
                <i data-lucide="heart"></i>
            </div>
            <h3>Enjoying this app?</h3>
            <p>Help support it by donating to Kinto. Your contribution helps us continue building tools for personal growth.</p>
            <div class="donation-actions">
                <a href="https://unmaskedculture.org/about" target="_blank" rel="noopener" class="btn btn-secondary">
                    <i data-lucide="info"></i> Learn More
                </a>
                <a href="https://unmaskedculture.org/donate" target="_blank" rel="noopener" class="btn btn-donate">
                    <i data-lucide="heart"></i> Donate
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
