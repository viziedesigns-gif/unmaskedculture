<?php
$pageTitle = 'Calm Shop';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
require_once __DIR__ . '/../../includes/shop_service.php';
require_once __DIR__ . '/../../includes/shop_catalog.php';

$shopCsrfToken = $_SESSION['shop_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['shop_csrf_token'] = $shopCsrfToken;

$shopState = getShopState($userId);
$categoryLabels = getShopCategoryLabels();
$categoryLabels['typography'] = 'Font & Color';
$activeTab = trim((string) ($_GET['tab'] ?? SHOP_CATEGORY_BACKGROUND));
if (!isset($categoryLabels[$activeTab])) {
    $activeTab = SHOP_CATEGORY_BACKGROUND;
}
$items = $activeTab === 'typography' ? [] : getShopItemsByCategory($activeTab);
$cosmetics = resolveEquippedCosmetics([
    'equipped_background' => $shopState['equipped']['background'],
    'equipped_banner_pattern' => $shopState['equipped']['banner_pattern'],
    'equipped_frame' => $shopState['equipped']['frame'],
    'equipped_stickers' => json_encode($shopState['equipped']['stickers']),
]);

$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = 'You';
}
$heroStyle = '--profile-x:' . (int) ($user['profile_banner_x'] ?? 50) . '%;'
    . '--profile-y:' . (int) ($user['profile_banner_y'] ?? 50) . '%;'
    . '--profile-zoom:' . (float) ($user['profile_banner_zoom'] ?? 1) . ';';
$previewStreak = (int) ($streakStatus['current_streak'] ?? 0);
$previewDay = min(77, max(0, $previewStreak));
$previewBannerTextColor = strtolower(trim((string) ($user['profile_banner_text_color'] ?? '')));
$previewHasTextColor = preg_match('/^#[0-9a-f]{6}$/', $previewBannerTextColor) === 1;

include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page shop-page">
    <?php renderSettingsBackNav('Calm Shop'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <a class="shop-avatar-link" href="/challenge/app/avatar.php">
        <strong>Dress your avatar</strong>
        <small>Spend the same Calm Points on hair, clothes, hats, and fun extras.</small>
    </a>

    <div class="shop-balance-bar">
        <div class="shop-balance-main">
            <span class="shop-balance-value"><?= number_format($shopState['balance']) ?></span>
            <span class="shop-balance-label">Calm Points available</span>
        </div>
        <div class="shop-balance-meta">
            Lifetime <?= number_format($shopState['total']) ?> · Level <?= (int) $shopState['level'] ?>
        </div>
    </div>

    <div class="shop-preview-card member-profile-card shop-preview-profile<?= $cosmetics['background_css'] !== '' ? ' has-shop-bg ' . h($cosmetics['background_css']) : '' ?>">
        <div class="member-profile-hero<?= !empty($user['profile_pic']) ? ' has-photo' : '' ?><?= $cosmetics['frame_css'] !== '' ? ' ' . h($cosmetics['frame_css']) : '' ?>" style="<?= h($heroStyle) ?>">
            <?php if (!empty($user['profile_pic'])): ?><img class="member-profile-cover" src="<?= h(profilePicUrl($user['profile_pic'])) ?>" alt=""><?php endif; ?>
            <div class="member-profile-hero-overlay" aria-hidden="true"></div>
            <?php if ($cosmetics['frame_css'] !== ''): ?>
                <div class="shop-frame-overlay" aria-hidden="true"></div>
            <?php endif; ?>
            <?php if (empty($user['profile_pic'])): ?>
                <div class="member-profile-placeholder avatar-placeholder" aria-hidden="true">
                    <?= h(strtoupper(substr((string) ($user['first_name'] ?: 'U'), 0, 1))) ?>
                </div>
            <?php endif; ?>
            <span class="member-profile-cover-label">Style preview</span>
        </div>
        <div class="member-profile-identity">
            <div class="member-profile-identity-copy"><h1><?= h($displayName) ?></h1><p class="member-profile-activity">Active today</p></div>
        </div>
        <div class="member-profile-stats<?= $cosmetics['banner_css'] !== '' ? ' ' . h($cosmetics['banner_css']) : '' ?><?= $previewHasTextColor ? ' has-custom-banner-text' : '' ?>" id="shopProfileStats"<?= $previewHasTextColor ? ' style="--profile-banner-text:' . h($previewBannerTextColor) . '"' : '' ?> aria-label="Challenge progress">
            <div class="member-profile-progress-heading">
                <div><span>Journey progress</span><strong>Day <?= $previewDay ?> of 77</strong></div>
            </div>
            <div class="member-profile-progress-track"><span style="width:<?= h((string) round(($previewDay / 77) * 100, 2)) ?>%"></span></div>
            <div class="member-profile-stat-grid">
                <div class="member-profile-stat"><span>Current streak</span><strong><?= $previewStreak ?> <small>days</small></strong></div>
                <div class="member-profile-stat"><span>Style</span><strong>Equipped</strong></div>
                <div class="member-profile-stat"><span>Journey</span><strong>77 days</strong></div>
            </div>
            <div class="member-profile-stickers" aria-label="Stickers">
                <p class="member-profile-stickers-label">Stickers</p>
                <div class="member-profile-stickers-row">
                    <?php if (empty($cosmetics['stickers'])): ?>
                        <span class="member-profile-stickers-empty">No stickers equipped</span>
                    <?php else: ?>
                        <?php foreach ($cosmetics['stickers'] as $sticker): ?>
                            <span class="shop-sticker <?= h($sticker['css']) ?>" aria-hidden="true"></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="shop-category-control">
        <label for="shopCategory">Customize</label>
        <select id="shopCategory" class="form-select" onchange="window.location.href=this.value">
            <?php foreach ($categoryLabels as $catKey => $catLabel): ?>
                <option value="/challenge/app/settings/shop.php?tab=<?= h($catKey) ?>"<?= $activeTab === $catKey ? ' selected' : '' ?>><?= h($catLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($activeTab === 'typography'): ?>
        <form method="POST" class="settings-card shop-typography-card" id="shopTypographyForm">
            <input type="hidden" name="csrf_token" value="<?= h($shopCsrfToken) ?>">
            <input type="hidden" name="action" value="update_profile_typography">
            <div>
                <p class="profile-kicker">Free customization</p>
                <h2>Banner font color</h2>
                <p>Choose a readable color for the journey banner on your profile.</p>
            </div>
            <label for="profile_banner_text_color">Font color</label>
            <select id="profile_banner_text_color" name="profile_banner_text_color" class="form-select">
                <option value=""<?= !$previewHasTextColor ? ' selected' : '' ?>>Automatic</option>
                <option value="#ffffff"<?= $previewBannerTextColor === '#ffffff' ? ' selected' : '' ?>>White</option>
                <option value="#173b37"<?= $previewBannerTextColor === '#173b37' ? ' selected' : '' ?>>Dark ink</option>
                <option value="#0f766e"<?= $previewBannerTextColor === '#0f766e' ? ' selected' : '' ?>>Deep teal</option>
                <option value="#f5d98b"<?= $previewBannerTextColor === '#f5d98b' ? ' selected' : '' ?>>Soft gold</option>
            </select>
            <button type="submit" class="btn btn-primary">Save Font Color</button>
        </form>
    <?php endif; ?>

    <?php if ($activeTab !== 'typography'): ?><div class="shop-grid">
        <?php foreach ($items as $item): ?>
            <?php
            $owned = !empty($shopState['owned_set'][$item['id']]);
            $equipped = isShopItemEquipped($shopState, $item['id']);
            $canAfford = $shopState['balance'] >= (int) $item['price'];
            ?>
            <article class="shop-item-card rarity-<?= h($item['rarity']) ?><?= $equipped ? ' is-equipped' : '' ?>">
                <div class="shop-item-swatch<?= $item['category'] !== SHOP_CATEGORY_STICKER ? ' ' . h($item['css']) : ' shop-swatch-sticker' ?>" aria-hidden="true">
                    <?php if ($item['category'] === SHOP_CATEGORY_STICKER): ?>
                        <span class="shop-sticker <?= h($item['css']) ?>"></span>
                    <?php elseif ($item['category'] === SHOP_CATEGORY_FRAME): ?>
                        <span class="shop-frame-swatch-inner"></span>
                        <span class="shop-frame-overlay"></span>
                    <?php endif; ?>
                </div>
                <div class="shop-item-body">
                    <div class="shop-item-heading">
                        <h2><?= h($item['name']) ?></h2>
                        <span class="shop-rarity-badge rarity-<?= h($item['rarity']) ?>"><?= h(ucfirst($item['rarity'])) ?></span>
                    </div>
                    <p class="shop-item-desc"><?= h($item['description']) ?></p>
                    <div class="shop-item-footer">
                        <span class="shop-item-price"><?= number_format((int) $item['price']) ?> pts</span>
                        <?php if ($equipped): ?>
                            <form method="POST" class="shop-item-action">
                                <input type="hidden" name="csrf_token" value="<?= h($shopCsrfToken) ?>">
                                <input type="hidden" name="action" value="unequip_shop_item">
                                <input type="hidden" name="item_id" value="<?= h($item['id']) ?>">
                                <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Unequip</button>
                            </form>
                        <?php elseif ($owned): ?>
                            <form method="POST" class="shop-item-action">
                                <input type="hidden" name="csrf_token" value="<?= h($shopCsrfToken) ?>">
                                <input type="hidden" name="action" value="equip_shop_item">
                                <input type="hidden" name="item_id" value="<?= h($item['id']) ?>">
                                <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Equip</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="shop-item-action">
                                <input type="hidden" name="csrf_token" value="<?= h($shopCsrfToken) ?>">
                                <input type="hidden" name="action" value="buy_shop_item">
                                <input type="hidden" name="item_id" value="<?= h($item['id']) ?>">
                                <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
                                <button type="submit" class="btn btn-primary btn-sm" <?= $canAfford ? '' : 'disabled' ?>>
                                    <?= $canAfford ? 'Buy' : 'Need more' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div><?php endif; ?>

    <?php if ($activeTab === SHOP_CATEGORY_STICKER): ?>
        <p class="form-hint shop-sticker-hint">You can equip up to <?= (int) SHOP_MAX_STICKERS ?> stickers at once.</p>
    <?php endif; ?>
</div>

<?php if ($activeTab === 'typography'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const colorSelect = document.getElementById('profile_banner_text_color');
    const preview = document.getElementById('shopProfileStats');
    colorSelect?.addEventListener('change', () => {
        const color = colorSelect.value;
        preview?.classList.toggle('has-custom-banner-text', color !== '');
        if (color) preview?.style.setProperty('--profile-banner-text', color);
        else preview?.style.removeProperty('--profile-banner-text');
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
