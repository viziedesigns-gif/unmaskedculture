<?php
$pageTitle = 'Download App';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Download App'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <div class="settings-card settings-detail-card pwa-install-card" id="pwaInstallCard">
        <div class="settings-card-header">
            <h3>Install on Your Phone</h3>
            <span class="notification-status" id="pwaInstallStatusBadge">Checking</span>
        </div>
        <div class="pwa-install-body">
            <div class="pwa-install-icon">
                <i data-lucide="smartphone"></i>
            </div>
            <div class="pwa-install-copy">
                <p>Install Kinto on your phone for a full-screen home screen app with quicker access to daily check-ins.</p>
                <ol class="pwa-ios-steps" id="pwaIosInstallSteps" hidden>
                    <li>Open this page in Safari.</li>
                    <li>Tap Share.</li>
                    <li>Choose Add to Home Screen.</li>
                </ol>
            </div>
            <button type="button" class="btn btn-primary" id="installPwaBtn" onclick="installKintoApp()">
                <i data-lucide="download"></i> Download App
            </button>
            <p class="form-hint" id="pwaInstallHelpText">Checking install support for this device.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
