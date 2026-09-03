<?php
/**
 * Kinto Jar - private encouragement and memory keepsake.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/jar_service.php';

requireOnboarding();
$user = getCurrentUser();
$userId = (int) getCurrentUserId();
ensureJarTable();

$entryCount = getJarEntryCount($userId);
$visualTypes = getJarVisualTypes($userId);
$unreadCount = getUnreadJarCount($userId);
$types = jarEntryTypes();
$csrf = jarCsrfToken();
$timezone = (string) ($user['timezone'] ?? DEFAULT_TIMEZONE);
markJarSeen($userId);

$pageTitle = 'Jar';
$bodyClass = 'jar-page-body';
include __DIR__ . '/../includes/header.php';
?>

<div class="jar-page" id="jarPage" data-entry-count="<?= $entryCount ?>" data-entry-types="<?= h((string) json_encode($visualTypes)) ?>" data-csrf="<?= h($csrf) ?>">
    <header class="jar-heading">
        <p class="jar-eyebrow">A place to keep the good</p>
        <h1>Your Jar</h1>
        <p>Save encouragement, memories, gratitude, and the moments you want to find again.</p>
        <?php if ($unreadCount > 0): ?>
            <span class="jar-new-badge"><i data-lucide="sparkles"></i><?= $unreadCount ?> new from your circle</span>
        <?php endif; ?>
    </header>

    <section class="jar-experience" aria-labelledby="jarVisualTitle">
        <h2 class="visually-hidden" id="jarVisualTitle">Your visual keepsake Jar</h2>
        <div class="jar-scene-wrap" id="jarSceneWrap">
            <canvas id="jarSceneCanvas" class="jar-scene-canvas" aria-hidden="true"></canvas>
            <div class="jar-css-fallback" id="jarCssFallback" aria-hidden="true">
                <div class="jar-css-rim"></div>
                <div class="jar-css-glass"><div class="jar-css-notes"></div></div>
            </div>
            <div class="jar-count-pill"><strong id="jarEntryCount"><?= $entryCount ?></strong><span><?= $entryCount === 1 ? 'kept moment' : 'kept moments' ?></span></div>
        </div>
        <button type="button" class="btn btn-primary btn-lg jar-pull-button" id="jarPullButton" <?= $entryCount < 1 ? 'disabled' : '' ?>>
            <i data-lucide="sparkles"></i><span><?= $entryCount > 0 ? 'Pull a random note' : 'Add your first note' ?></span>
        </button>
        <p class="jar-cycle-hint">Every note gets a turn before the Jar repeats one.</p>
    </section>

    <section class="jar-compose-card" aria-labelledby="jarComposeTitle">
        <div class="jar-section-heading">
            <div><p class="jar-eyebrow">Add something meaningful</p><h2 id="jarComposeTitle">Place a note in your Jar</h2></div>
            <i data-lucide="heart-handshake" aria-hidden="true"></i>
        </div>
        <form id="jarAddForm" class="jar-add-form">
            <div class="form-group">
                <label for="jarEntryType">Type <span class="form-hint">(optional)</span></label>
                <select id="jarEntryType" class="form-select">
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= h($value) ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="jarMessage">Your note</label>
                <textarea id="jarMessage" class="form-textarea" maxlength="600" rows="5" required placeholder="Write an encouragement, a memory, a happy moment, or anything worth keeping..."></textarea>
                <div class="jar-input-meta"><span id="jarFormStatus" role="status" aria-live="polite"></span><span><strong id="jarCharacterCount">0</strong>/600</span></div>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i data-lucide="archive"></i> Place it in the Jar</button>
        </form>
    </section>
    <a class="jar-history-link" href="/challenge/app/jar_history.php">
        <span><i data-lucide="history"></i><span><strong>Jar history</strong><small>Review and manage every note you have kept</small></span></span>
        <i data-lucide="chevron-right"></i>
    </a>
</div>

<div id="jarRevealModal" class="modal jar-reveal-modal" role="dialog" aria-modal="true" aria-labelledby="jarRevealType">
    <div class="modal-content">
        <div class="modal-body jar-reveal-content">
            <div class="jar-unfolded-note" id="jarUnfoldedNote">
                <span class="jar-type-label" id="jarRevealType">A note from your Jar</span>
                <blockquote id="jarRevealMessage"></blockquote>
                <div class="jar-reveal-meta"><span id="jarRevealAuthor"></span><time id="jarRevealDate"></time></div>
            </div>
            <button type="button" class="btn btn-primary btn-lg" id="jarReturnButton"><i data-lucide="archive-restore"></i> Return it to the Jar</button>
        </div>
    </div>
</div>

<script type="importmap">{"imports":{"three":"https://unpkg.com/three@0.160.0/build/three.module.js"}}</script>
<script type="module" src="<?= h(assetUrl('/challenge/assets/js/jar-scene.js')) ?>"></script>
<script type="module" src="<?= h(assetUrl('/challenge/assets/js/jar-page.js')) ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
