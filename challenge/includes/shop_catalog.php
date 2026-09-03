<?php
/**
 * Calm Points cosmetics catalog (config, not DB rows).
 */

const SHOP_CATEGORY_BACKGROUND = 'background';
const SHOP_CATEGORY_BANNER = 'banner_pattern';
const SHOP_CATEGORY_STICKER = 'sticker';
const SHOP_CATEGORY_FRAME = 'frame';

const SHOP_MAX_STICKERS = 3;

/**
 * @return array<string, array{id:string,category:string,name:string,price:int,rarity:string,css:string,description:string}>
 */
function getShopCatalog(): array {
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $items = [
        // Backgrounds â€” hero atmosphere
        [
            'id' => 'bg_mist',
            'category' => SHOP_CATEGORY_BACKGROUND,
            'name' => 'Morning Mist',
            'price' => 75,
            'rarity' => 'common',
            'css' => 'shop-bg-mist',
            'description' => 'Soft blue-grey wash for a calm start.',
        ],
        [
            'id' => 'bg_sage',
            'category' => SHOP_CATEGORY_BACKGROUND,
            'name' => 'Sage Drift',
            'price' => 100,
            'rarity' => 'common',
            'css' => 'shop-bg-sage',
            'description' => 'Gentle green gradient with quiet depth.',
        ],
        [
            'id' => 'bg_dusk',
            'category' => SHOP_CATEGORY_BACKGROUND,
            'name' => 'Harbor Dusk',
            'price' => 150,
            'rarity' => 'common',
            'css' => 'shop-bg-dusk',
            'description' => 'Warm evening tones across the hero.',
        ],
        [
            'id' => 'bg_aurora',
            'category' => SHOP_CATEGORY_BACKGROUND,
            'name' => 'Quiet Aurora',
            'price' => 350,
            'rarity' => 'rare',
            'css' => 'shop-bg-aurora',
            'description' => 'Layered teal and indigo light bands.',
        ],
        [
            'id' => 'bg_deep_tide',
            'category' => SHOP_CATEGORY_BACKGROUND,
            'name' => 'Deep Tide',
            'price' => 450,
            'rarity' => 'rare',
            'css' => 'shop-bg-deep-tide',
            'description' => 'Rich oceanic blues with soft light.',
        ],
        [
            'id' => 'bg_obsidian_gold',
            'category' => SHOP_CATEGORY_BACKGROUND,
            'name' => 'Obsidian Gold',
            'price' => 1200,
            'rarity' => 'luxury',
            'css' => 'shop-bg-obsidian-gold',
            'description' => 'Dark luxury field with gold shimmer.',
        ],
        [
            'id' => 'bg_marble_ink',
            'category' => SHOP_CATEGORY_BACKGROUND,
            'name' => 'Marble Ink',
            'price' => 1800,
            'rarity' => 'luxury',
            'css' => 'shop-bg-marble-ink',
            'description' => 'Ink-veined marble atmosphere.',
        ],

        // Banner patterns â€” streak / start / goal strip
        [
            'id' => 'banner_dots',
            'category' => SHOP_CATEGORY_BANNER,
            'name' => 'Soft Dots',
            'price' => 75,
            'rarity' => 'common',
            'css' => 'shop-banner-dots',
            'description' => 'Subtle dotted texture under your streak stats.',
        ],
        [
            'id' => 'banner_lines',
            'category' => SHOP_CATEGORY_BANNER,
            'name' => 'Quiet Lines',
            'price' => 100,
            'rarity' => 'common',
            'css' => 'shop-banner-lines',
            'description' => 'Fine horizontal rules for a clean look.',
        ],
        [
            'id' => 'banner_grid',
            'category' => SHOP_CATEGORY_BANNER,
            'name' => 'Calm Grid',
            'price' => 140,
            'rarity' => 'common',
            'css' => 'shop-banner-grid',
            'description' => 'Light grid under Current Streak and dates.',
        ],
        [
            'id' => 'banner_waves',
            'category' => SHOP_CATEGORY_BANNER,
            'name' => 'Ripple Waves',
            'price' => 300,
            'rarity' => 'rare',
            'css' => 'shop-banner-waves',
            'description' => 'Soft wave pattern across the stats banner.',
        ],
        [
            'id' => 'banner_leaves',
            'category' => SHOP_CATEGORY_BANNER,
            'name' => 'Leaf Vein',
            'price' => 400,
            'rarity' => 'rare',
            'css' => 'shop-banner-leaves',
            'description' => 'Organic leaf-vein pattern in muted green.',
        ],
        [
            'id' => 'banner_filigree',
            'category' => SHOP_CATEGORY_BANNER,
            'name' => 'Gold Filigree',
            'price' => 1000,
            'rarity' => 'luxury',
            'css' => 'shop-banner-filigree',
            'description' => 'Ornate gold filigree on ivory glass.',
        ],
        [
            'id' => 'banner_constellation',
            'category' => SHOP_CATEGORY_BANNER,
            'name' => 'Night Constellation',
            'price' => 1500,
            'rarity' => 'luxury',
            'css' => 'shop-banner-constellation',
            'description' => 'Star-map pattern for a premium streak banner.',
        ],

        // Stickers
        [
            'id' => 'sticker_leaf',
            'category' => SHOP_CATEGORY_STICKER,
            'name' => 'Fresh Leaf',
            'price' => 80,
            'rarity' => 'common',
            'css' => 'shop-sticker-leaf',
            'description' => 'A simple leaf accent for your profile card.',
        ],
        [
            'id' => 'sticker_drop',
            'category' => SHOP_CATEGORY_STICKER,
            'name' => 'Water Drop',
            'price' => 90,
            'rarity' => 'common',
            'css' => 'shop-sticker-drop',
            'description' => 'A calm water droplet sticker.',
        ],
        [
            'id' => 'sticker_sun',
            'category' => SHOP_CATEGORY_STICKER,
            'name' => 'Daybreak Sun',
            'price' => 120,
            'rarity' => 'common',
            'css' => 'shop-sticker-sun',
            'description' => 'Warm sunburst for morning energy.',
        ],
        [
            'id' => 'sticker_flame',
            'category' => SHOP_CATEGORY_STICKER,
            'name' => 'Streak Flame',
            'price' => 280,
            'rarity' => 'rare',
            'css' => 'shop-sticker-flame',
            'description' => 'A fire accent for streak pride.',
        ],
        [
            'id' => 'sticker_lotus',
            'category' => SHOP_CATEGORY_STICKER,
            'name' => 'Lotus Bloom',
            'price' => 420,
            'rarity' => 'rare',
            'css' => 'shop-sticker-lotus',
            'description' => 'Elegant lotus mark for quiet strength.',
        ],
        [
            'id' => 'sticker_crown',
            'category' => SHOP_CATEGORY_STICKER,
            'name' => 'Calm Crown',
            'price' => 1100,
            'rarity' => 'luxury',
            'css' => 'shop-sticker-crown',
            'description' => 'Gold crown badge for dedicated finishers.',
        ],
        [
            'id' => 'sticker_seal',
            'category' => SHOP_CATEGORY_STICKER,
            'name' => 'Kinto Seal',
            'price' => 1600,
            'rarity' => 'luxury',
            'css' => 'shop-sticker-seal',
            'description' => 'Ornate seal mark for your profile card.',
        ],

        // Frames â€” around hero photo
        [
            'id' => 'frame_thin',
            'category' => SHOP_CATEGORY_FRAME,
            'name' => 'Thin Edge',
            'price' => 100,
            'rarity' => 'common',
            'css' => 'shop-frame-thin',
            'description' => 'A clean thin border around your photo.',
        ],
        [
            'id' => 'frame_soft',
            'category' => SHOP_CATEGORY_FRAME,
            'name' => 'Soft Halo',
            'price' => 130,
            'rarity' => 'common',
            'css' => 'shop-frame-soft',
            'description' => 'Soft glowing edge around your photo.',
        ],
        [
            'id' => 'frame_double',
            'category' => SHOP_CATEGORY_FRAME,
            'name' => 'Double Line',
            'price' => 150,
            'rarity' => 'common',
            'css' => 'shop-frame-double',
            'description' => 'Classic double-line photo frame.',
        ],
        [
            'id' => 'frame_oak',
            'category' => SHOP_CATEGORY_FRAME,
            'name' => 'Oak Border',
            'price' => 320,
            'rarity' => 'rare',
            'css' => 'shop-frame-oak',
            'description' => 'Warm wood-toned frame corners.',
        ],
        [
            'id' => 'frame_frost',
            'category' => SHOP_CATEGORY_FRAME,
            'name' => 'Frost Glass',
            'price' => 480,
            'rarity' => 'rare',
            'css' => 'shop-frame-frost',
            'description' => 'Icy glass rim with soft corners.',
        ],
        [
            'id' => 'frame_gilded',
            'category' => SHOP_CATEGORY_FRAME,
            'name' => 'Gilded Ornate',
            'price' => 1400,
            'rarity' => 'luxury',
            'css' => 'shop-frame-gilded',
            'description' => 'Multi-layer gold ornate photo frame.',
        ],
        [
            'id' => 'frame_obsidian',
            'category' => SHOP_CATEGORY_FRAME,
            'name' => 'Obsidian Relic',
            'price' => 2000,
            'rarity' => 'luxury',
            'css' => 'shop-frame-obsidian',
            'description' => 'Dark relic frame with gold inlay.',
        ],
    ];

    $catalog = [];
    foreach ($items as $item) {
        $catalog[$item['id']] = $item;
    }
    return $catalog;
}

/**
 * @return array<string, string>
 */
function getShopCategoryLabels(): array {
    return [
        SHOP_CATEGORY_BACKGROUND => 'Backgrounds',
        SHOP_CATEGORY_BANNER => 'Banner Patterns',
        SHOP_CATEGORY_STICKER => 'Stickers',
        SHOP_CATEGORY_FRAME => 'Frames',
    ];
}

/**
 * @return array<int, array{id:string,category:string,name:string,price:int,rarity:string,css:string,description:string}>
 */
function getShopItemsByCategory(string $category): array {
    $out = [];
    foreach (getShopCatalog() as $item) {
        if ($item['category'] === $category) {
            $out[] = $item;
        }
    }
    return $out;
}

function getShopItem(string $itemId): ?array {
    $catalog = getShopCatalog();
    return $catalog[$itemId] ?? null;
}

function isValidShopItemId(string $itemId): bool {
    return getShopItem($itemId) !== null;
}

function resolveFrameCssFromId(?string $frameId): string {
    $frameId = trim((string) $frameId);
    if ($frameId === '') {
        return '';
    }
    $item = getShopItem($frameId);
    if (!$item || ($item['category'] ?? '') !== SHOP_CATEGORY_FRAME) {
        return '';
    }
    return (string) $item['css'];
}

/**
 * Resolve equipped cosmetic CSS helpers for profile rendering.
 *
 * @param array $user Row with equipped_* fields
 * @return array{
 *   background_css:string,
 *   banner_css:string,
 *   frame_css:string,
 *   stickers:array<int, array{id:string,css:string,slot:int}>
 * }
 */
function resolveEquippedCosmetics(array $user): array {
    $catalog = getShopCatalog();
    $bgId = trim((string) ($user['equipped_background'] ?? ''));
    $bannerId = trim((string) ($user['equipped_banner_pattern'] ?? ''));
    $frameId = trim((string) ($user['equipped_frame'] ?? ''));

    $bgCss = '';
    if ($bgId !== '' && isset($catalog[$bgId]) && $catalog[$bgId]['category'] === SHOP_CATEGORY_BACKGROUND) {
        $bgCss = $catalog[$bgId]['css'];
    }

    $bannerCss = '';
    if ($bannerId !== '' && isset($catalog[$bannerId]) && $catalog[$bannerId]['category'] === SHOP_CATEGORY_BANNER) {
        $bannerCss = $catalog[$bannerId]['css'];
    }

    $frameCss = resolveFrameCssFromId($frameId);

    $stickers = [];
    $rawStickers = $user['equipped_stickers'] ?? '[]';
    if (is_string($rawStickers)) {
        $decoded = json_decode($rawStickers, true);
    } elseif (is_array($rawStickers)) {
        $decoded = $rawStickers;
    } else {
        $decoded = [];
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $slot = 0;
    foreach ($decoded as $stickerId) {
        if ($slot >= SHOP_MAX_STICKERS) {
            break;
        }
        $stickerId = is_string($stickerId) ? $stickerId : '';
        if ($stickerId === '' || !isset($catalog[$stickerId])) {
            continue;
        }
        if ($catalog[$stickerId]['category'] !== SHOP_CATEGORY_STICKER) {
            continue;
        }
        $stickers[] = [
            'id' => $stickerId,
            'css' => $catalog[$stickerId]['css'],
            'slot' => $slot,
        ];
        $slot++;
    }

    return [
        'background_css' => $bgCss,
        'banner_css' => $bannerCss,
        'frame_css' => $frameCss,
        'stickers' => $stickers,
    ];
}
