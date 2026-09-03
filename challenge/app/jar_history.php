<?php
/** Private, paginated Jar history. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/jar_service.php';
requireOnboarding();

$user = getCurrentUser();
$userId = (int) getCurrentUserId();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$entryCount = getJarEntryCount($userId);
$totalPages = max(1, (int) ceil($entryCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$history = getJarHistory($userId, $perPage, ($page - 1) * $perPage);
$types = jarEntryTypes();
$timezone = (string) ($user['timezone'] ?? DEFAULT_TIMEZONE);
$csrf = jarCsrfToken();

$pageTitle = 'Jar History';
$bodyClass = 'jar-page-body';
include __DIR__ . '/../includes/header.php';
?>
<div class="jar-page jar-history-page" id="jarHistoryPage" data-entry-count="<?= $entryCount ?>" data-csrf="<?= h($csrf) ?>">
    <header class="settings-detail-header jar-history-header">
        <a href="/challenge/app/jar.php" class="settings-back-link"><i data-lucide="chevron-left"></i><span>Jar</span></a>
        <p class="jar-eyebrow">Private to you</p>
        <h1>Jar history</h1>
        <p>Review the moments you have kept and remove anything you no longer want in your Jar.</p>
    </header>
    <section class="jar-history" aria-labelledby="jarHistoryTitle">
        <div class="jar-section-heading"><h2 id="jarHistoryTitle">All notes</h2><span><?= $entryCount ?> total</span></div>
        <div class="jar-history-list" id="jarHistoryList">
            <?php if (!$history): ?><div class="jar-empty-history"><i data-lucide="archive"></i><h3>No notes yet</h3><p>Return to your Jar to add the first one.</p><a href="/challenge/app/jar.php" class="btn btn-primary btn-sm">Open Jar</a></div><?php endif; ?>
            <?php foreach ($history as $entry): $type = normalizeJarEntryType($entry['entry_type'] ?? null); ?>
                <article class="jar-history-card jar-type-<?= h($type) ?>" data-entry-id="<?= (int) $entry['id'] ?>">
                    <div class="jar-history-card__top"><span class="jar-type-label"><?= h($types[$type]) ?></span><button type="button" class="jar-delete-button" data-delete-jar-entry="<?= (int) $entry['id'] ?>" aria-label="Delete this Jar entry"><i data-lucide="trash-2"></i></button></div>
                    <p><?= nl2br(h((string) $entry['message'])) ?></p>
                    <footer><span>From <?= h(jarAuthorName($entry, $userId)) ?></span><time datetime="<?= h((string) $entry['created_at_utc']) ?>"><?= h(formatJarTimestamp($entry['created_at_utc'] ?? null, $timezone)) ?></time><span><?= (int) $entry['pull_count'] ?> pull<?= (int) $entry['pull_count'] === 1 ? '' : 's' ?></span></footer>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?><nav class="jar-pagination" aria-label="Jar history pages"><?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?page=<?= $page - 1 ?>"><i data-lucide="chevron-left"></i> Newer</a><?php endif; ?><span>Page <?= $page ?> of <?= $totalPages ?></span><?php if ($page < $totalPages): ?><a class="btn btn-secondary btn-sm" href="?page=<?= $page + 1 ?>">Older <i data-lucide="chevron-right"></i></a><?php endif; ?></nav><?php endif; ?>
    </section>
</div>
<script type="module" src="<?= h(assetUrl('/challenge/assets/js/jar-page.js')) ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
