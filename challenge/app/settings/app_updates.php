<?php
$pageTitle = 'App Updates';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';

$updates = require __DIR__ . '/../../config/app_updates.php';
$displayTimezone = new DateTimeZone($user['timezone'] ?? DEFAULT_TIMEZONE);

include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page app-updates-page">
    <?php renderSettingsBackNav('App Updates'); ?>

    <p class="app-updates-intro">New features and fixes from each app update.</p>

    <div class="app-updates-list">
        <?php foreach ($updates as $index => $update): ?>
            <?php
            try {
                $publishedAt = new DateTimeImmutable((string) ($update['published_at'] ?? 'now'));
                $publishedAt = $publishedAt->setTimezone($displayTimezone);
                $publishedLabel = $publishedAt->format('M j, Y \a\t g:i A T');
                $publishedIso = $publishedAt->format(DateTimeInterface::ATOM);
            } catch (Exception $e) {
                $publishedLabel = 'Recently';
                $publishedIso = '';
            }
            ?>
            <article class="settings-card settings-detail-card app-update-card">
                <div class="app-update-card__header">
                    <span class="app-update-card__icon" aria-hidden="true"><i data-lucide="sparkles"></i></span>
                    <div>
                        <h2><?= h((string) ($update['title'] ?? ($index === 0 ? 'Latest update' : 'App update'))) ?></h2>
                        <time<?= $publishedIso !== '' ? ' datetime="' . h($publishedIso) . '"' : '' ?>>
                            <?= h($publishedLabel) ?>
                        </time>
                    </div>
                </div>
                <ul class="app-update-items">
                    <?php foreach (($update['items'] ?? []) as $item): ?>
                        <li><?= h((string) $item) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($update['href'])): ?>
                    <a class="btn btn-secondary btn-sm app-update-action" href="<?= h((string) $update['href']) ?>"><i data-lucide="arrow-right"></i><?= h((string) ($update['link_label'] ?? 'View update')) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
