<?php
/**
 * Shared layout fragments for settings pages.
 */

function renderSettingsAlerts(string $error, string $success): void {
    if ($error !== ''): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif;

    if ($success !== ''): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif;
}

function renderSettingsBackNav(string $title, string $href = '/challenge/app/profile.php', string $label = 'Settings'): void { ?>
    <div class="settings-detail-header">
        <a href="<?= h($href) ?>" class="settings-back-link">
            <i data-lucide="chevron-left"></i>
            <span><?= h($label) ?></span>
        </a>
        <h1><?= h($title) ?></h1>
    </div>
<?php }

function renderSettingsHubRow(string $href, string $icon, string $label, string $subtitle, ?string $tone = null): void {
    $class = 'settings-hub-row';
    if ($tone === 'danger') {
        $class .= ' settings-hub-row-danger';
    }
    ?>
    <a href="<?= h($href) ?>" class="<?= h($class) ?>">
        <span class="settings-hub-row-icon" aria-hidden="true">
            <i data-lucide="<?= h($icon) ?>"></i>
        </span>
        <span class="settings-hub-row-copy">
            <span class="settings-hub-row-label"><?= h($label) ?></span>
            <span class="settings-hub-row-subtitle"><?= h($subtitle) ?></span>
        </span>
        <i data-lucide="chevron-right" class="settings-hub-row-chevron"></i>
    </a>
<?php }

function renderSettingsHubSection(string $heading, callable $rows): void { ?>
    <section class="settings-hub-section">
        <h2 class="settings-hub-section-title"><?= h($heading) ?></h2>
        <div class="settings-hub-list">
            <?php $rows(); ?>
        </div>
    </section>
<?php }
