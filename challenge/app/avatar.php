<?php
/**
 * Avatar Studio — customize a Kinto character with Calm Points.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings_layout.php';
require_once __DIR__ . '/../includes/avatar_service.php';
require_once __DIR__ . '/../includes/avatar_render.php';

requireOnboarding();

$user = getCurrentUser();
$userId = (int) getCurrentUserId();
$state = getAvatarState($userId);
$csrf = avatarCsrfToken();
$trays = getAvatarTrays();
$activeTray = trim((string) ($_GET['tray'] ?? 'face'));
if (!isset($trays[$activeTray])) {
    $activeTray = 'face';
}

$pageTitle = 'Avatar Studio';
$bodyClass = 'avatar-studio-body';
include __DIR__ . '/../includes/header.php';
?>

<div class="avatar-studio" id="avatarStudio"
     data-api="/challenge/api/avatar_action.php"
     data-csrf="<?= h($csrf) ?>"
     data-tray="<?= h($activeTray) ?>">
    <?php renderSettingsBackNav('Avatar Studio'); ?>

    <div class="shop-balance-bar">
        <div class="shop-balance-main">
            <span class="shop-balance-value" id="avatarBalance"><?= number_format($state['balance']) ?></span>
            <span class="shop-balance-label">Calm Points available</span>
        </div>
        <div class="shop-balance-meta">
            Lifetime <?= number_format($state['total']) ?> · Level <?= (int) $state['level'] ?>
        </div>
    </div>

    <section class="avatar-stage" aria-label="Your avatar">
        <p class="avatar-stage-kicker">Your character</p>
        <div class="avatar-stage-preview" id="avatarPreview">
            <?= renderKintoAvatar($state['config'], ['size' => 'xl', 'animate' => true]) ?>
        </div>
        <p class="avatar-stage-hint" id="avatarStatus" role="status" aria-live="polite">Tap a look to try it on.</p>
    </section>

    <label class="avatar-public-toggle">
        <input type="checkbox" id="avatarPublicFace" <?= !empty($state['public_face']) ? 'checked' : '' ?>>
        <span>
            <strong>Use this avatar as my public face</strong>
            <small>Show it on Feed, Circles, and your profile instead of your photo.</small>
        </span>
    </label>

    <div class="avatar-tray-tabs" role="tablist" aria-label="Customize">
        <?php foreach ($trays as $trayKey => $tray): ?>
            <button type="button"
                    class="avatar-tray-tab<?= $activeTray === $trayKey ? ' is-active' : '' ?>"
                    data-tray="<?= h($trayKey) ?>"
                    role="tab"
                    aria-selected="<?= $activeTray === $trayKey ? 'true' : 'false' ?>">
                <?= h($tray['label']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($trays as $trayKey => $tray): ?>
        <?php $items = getAvatarItemsForTray($trayKey); ?>
        <div class="avatar-rail<?= $activeTray === $trayKey ? ' is-active' : '' ?>"
             data-rail="<?= h($trayKey) ?>"
             role="tabpanel"
             <?= $activeTray === $trayKey ? '' : 'hidden' ?>>
            <?php foreach ($items as $item): ?>
                <?php
                $owned = userOwnsAvatarItem($userId, $item['id']);
                $equipped = isAvatarItemEquipped($state['config'], $item['id']);
                $canAfford = $state['balance'] >= (int) $item['price'];
                $swatchFill = $item['fill'] ?: '#F5F0E8';
                $swatchFill2 = $item['fill2'] ?: $swatchFill;
                ?>
                <button type="button"
                        class="avatar-item rarity-<?= h($item['rarity']) ?><?= $equipped ? ' is-equipped' : '' ?><?= $owned ? ' is-owned' : ' is-locked' ?>"
                        data-item-id="<?= h($item['id']) ?>"
                        data-slot="<?= h($item['slot']) ?>"
                        data-price="<?= (int) $item['price'] ?>"
                        data-owned="<?= $owned ? '1' : '0' ?>"
                        data-name="<?= h($item['name']) ?>"
                        aria-pressed="<?= $equipped ? 'true' : 'false' ?>">
                    <span class="avatar-item-swatch" style="--swatch:<?= h($swatchFill) ?>;--swatch2:<?= h($swatchFill2) ?>" aria-hidden="true">
                        <?php if (($item['shape'] ?? '') === 'none'): ?>
                            <span class="avatar-item-none">×</span>
                        <?php endif; ?>
                    </span>
                    <span class="avatar-item-name"><?= h($item['name']) ?></span>
                    <span class="avatar-item-meta">
                        <?php if ($equipped): ?>
                            Equipped
                        <?php elseif ($owned): ?>
                            Owned
                        <?php elseif ((int) $item['price'] <= 0): ?>
                            Free
                        <?php else: ?>
                            <?= number_format((int) $item['price']) ?> pts
                        <?php endif; ?>
                    </span>
                    <?php if (!$owned && (int) $item['price'] > 0 && !$canAfford): ?>
                        <span class="avatar-item-need">Need more</span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <p class="avatar-studio-note">Purchases use the same Calm Points as the <a href="/challenge/app/settings/shop.php">Calm Shop</a>.</p>
</div>

<script>
window.KINTO_AVATAR = <?= json_encode([
    'catalog' => getAvatarClientCatalog(),
    'config' => $state['config'],
    'defaults' => getAvatarDefaultLoadout(),
    'balance' => $state['balance'],
    'publicFace' => (bool) $state['public_face'],
], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= h(assetUrl('/challenge/assets/js/kinto-avatar.js')) ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
