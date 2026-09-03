<?php
/**
 * Avatar Studio catalog (config, not DB rows).
 * Item ids share user_shop_inventory with the Calm Shop.
 */

const AVATAR_SLOT_SKIN = 'skin';
const AVATAR_SLOT_HAIR = 'hair';
const AVATAR_SLOT_EYES = 'eyes';
const AVATAR_SLOT_OUTFIT = 'outfit';
const AVATAR_SLOT_HAT = 'hat';
const AVATAR_SLOT_ACCESSORY = 'accessory';
const AVATAR_SLOT_EXTRA = 'extra';

/**
 * @return array<string, string>
 */
function getAvatarDefaultLoadout(): array {
    return [
        AVATAR_SLOT_SKIN => 'skin_warm',
        AVATAR_SLOT_HAIR => 'hair_short',
        AVATAR_SLOT_EYES => 'eyes_round',
        AVATAR_SLOT_OUTFIT => 'outfit_tee',
        AVATAR_SLOT_HAT => null,
        AVATAR_SLOT_ACCESSORY => null,
        AVATAR_SLOT_EXTRA => null,
    ];
}

/**
 * @return array<string, array{label:string,slots:array<int,string>}>
 */
function getAvatarTrays(): array {
    return [
        'face' => ['label' => 'Face', 'slots' => [AVATAR_SLOT_SKIN]],
        'hair' => ['label' => 'Hair', 'slots' => [AVATAR_SLOT_HAIR]],
        'eyes' => ['label' => 'Eyes', 'slots' => [AVATAR_SLOT_EYES]],
        'clothes' => ['label' => 'Clothes', 'slots' => [AVATAR_SLOT_OUTFIT]],
        'hats' => ['label' => 'Hats', 'slots' => [AVATAR_SLOT_HAT]],
        'fun' => ['label' => 'Fun', 'slots' => [AVATAR_SLOT_ACCESSORY, AVATAR_SLOT_EXTRA]],
    ];
}

/**
 * @return array<string, array{
 *   id:string,slot:string,tray:string,name:string,price:int,rarity:string,
 *   shape:string,fill:?string,fill2:?string,stroke:?string,description:string
 * }>
 */
function getAvatarCatalog(): array {
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $items = [
        // Face / skin — all free
        avatarItem('skin_fair', AVATAR_SLOT_SKIN, 'face', 'Fair Sand', 0, 'common', 'skin', '#F6D4B8', '#E8C09A', '#D7A57C', 'A light, sun-warmed complexion.'),
        avatarItem('skin_warm', AVATAR_SLOT_SKIN, 'face', 'Warm Clay', 0, 'common', 'skin', '#E8B88A', '#C99262', '#B07848', 'The default warm clay tone.'),
        avatarItem('skin_gold', AVATAR_SLOT_SKIN, 'face', 'Honey Gold', 0, 'common', 'skin', '#C98A5A', '#A86E42', '#8A5834', 'Golden mid-tone skin.'),
        avatarItem('skin_olive', AVATAR_SLOT_SKIN, 'face', 'Olive Grove', 0, 'common', 'skin', '#A67C52', '#855F3C', '#6A4A2E', 'Soft olive warmth.'),
        avatarItem('skin_brown', AVATAR_SLOT_SKIN, 'face', 'River Brown', 0, 'common', 'skin', '#8B5A3C', '#6A422C', '#4E2F1E', 'Rich river-brown skin.'),
        avatarItem('skin_deep', AVATAR_SLOT_SKIN, 'face', 'Deep Earth', 0, 'common', 'skin', '#5C3A28', '#3F261A', '#2A1810', 'Deep earth complexion.'),

        // Hair — free bases + paid styles
        avatarItem('hair_short', AVATAR_SLOT_HAIR, 'hair', 'Short Ink', 0, 'common', 'short', '#2C241C', '#1A1510', null, 'A simple rounded cut.'),
        avatarItem('hair_bob', AVATAR_SLOT_HAIR, 'hair', 'Quiet Bob', 0, 'common', 'bob', '#5A3D2B', '#3D281C', null, 'A soft chin-length bob.'),
        avatarItem('hair_curl', AVATAR_SLOT_HAIR, 'hair', 'Soft Curls', 0, 'common', 'curl', '#1C1612', '#0E0B09', null, 'Short rounded curls.'),
        avatarItem('hair_wave_gold', AVATAR_SLOT_HAIR, 'hair', 'Golden Waves', 90, 'common', 'wave', '#C4A35A', '#A8843E', null, 'Warm waves with a gold sheen.'),
        avatarItem('hair_bun_ink', AVATAR_SLOT_HAIR, 'hair', 'Ink Bun', 120, 'common', 'bun', '#241C16', '#100C09', null, 'A tidy high bun.'),
        avatarItem('hair_locs_dark', AVATAR_SLOT_HAIR, 'hair', 'Calm Locs', 140, 'common', 'locs', '#2A2018', '#16110C', null, 'Neat locs with soft weight.'),
        avatarItem('hair_fade_umber', AVATAR_SLOT_HAIR, 'hair', 'Umber Fade', 300, 'rare', 'fade', '#6B4228', '#3D2416', null, 'A tapered umber fade.'),
        avatarItem('hair_long_auburn', AVATAR_SLOT_HAIR, 'hair', 'Auburn Flow', 380, 'rare', 'long', '#8A3B24', '#5C2416', null, 'Long flowing auburn hair.'),
        avatarItem('hair_braid_gold', AVATAR_SLOT_HAIR, 'hair', 'Gilded Braid', 1200, 'luxury', 'braid', '#C4A35A', '#8A6C2E', null, 'A crown braid with gold thread.'),

        // Eyes
        avatarItem('eyes_round', AVATAR_SLOT_EYES, 'eyes', 'Round Calm', 0, 'common', 'round', '#2C241C', '#F7F1E6', null, 'Open, friendly round eyes.'),
        avatarItem('eyes_soft', AVATAR_SLOT_EYES, 'eyes', 'Soft Oval', 0, 'common', 'soft', '#3D2A1C', '#F7F1E6', null, 'Gentle oval eyes.'),
        avatarItem('eyes_almond', AVATAR_SLOT_EYES, 'eyes', 'Almond Light', 100, 'common', 'almond', '#4A3324', '#FFF8EC', null, 'Almond-shaped eyes.'),
        avatarItem('eyes_bright', AVATAR_SLOT_EYES, 'eyes', 'Bright Gaze', 140, 'common', 'bright', '#1F4A46', '#F4FFFB', null, 'Teal-bright curious eyes.'),
        avatarItem('eyes_sleepy', AVATAR_SLOT_EYES, 'eyes', 'Sleepy Tide', 320, 'rare', 'sleepy', '#3A2A40', '#F6F0EA', null, 'Half-lidded restful eyes.'),
        avatarItem('eyes_spark', AVATAR_SLOT_EYES, 'eyes', 'Spark Glance', 450, 'rare', 'spark', '#6B4228', '#FFF6D8', null, 'Eyes with a gold spark.'),

        // Clothes
        avatarItem('outfit_tee', AVATAR_SLOT_OUTFIT, 'clothes', 'Cream Tee', 0, 'common', 'tee', '#F4EFE4', '#E4D8C4', '#C4A35A', 'The starter cream tee.'),
        avatarItem('outfit_sage', AVATAR_SLOT_OUTFIT, 'clothes', 'Sage Henley', 90, 'common', 'henley', '#7E9A86', '#5E7A66', '#F4EFE4', 'A quiet sage henley.'),
        avatarItem('outfit_ocean', AVATAR_SLOT_OUTFIT, 'clothes', 'Harbor Collar', 130, 'common', 'collar', '#2F6B66', '#1C4542', '#E8F4F1', 'Ocean-teal collared shirt.'),
        avatarItem('outfit_sunset', AVATAR_SLOT_OUTFIT, 'clothes', 'Sunset Wrap', 280, 'rare', 'wrap', '#C47A5A', '#8A4A32', '#F6D4B8', 'A warm wrap top.'),
        avatarItem('outfit_linen', AVATAR_SLOT_OUTFIT, 'clothes', 'Linen V', 360, 'rare', 'vneck', '#E8DCC8', '#C8B89A', '#8A6C2E', 'Soft linen v-neck.'),
        avatarItem('outfit_kinto', AVATAR_SLOT_OUTFIT, 'clothes', 'Kinto Sash', 1100, 'luxury', 'sash', '#1A1714', '#C4A35A', '#F5F0E8', 'Dark sash with a gold kintsugi stripe.'),
        avatarItem('outfit_gold', AVATAR_SLOT_OUTFIT, 'clothes', 'Gilded Collar', 1600, 'luxury', 'gold', '#C4A35A', '#8A6C2E', '#1A1714', 'Ornate gold collar.'),

        // Hats
        avatarItem('hat_none', AVATAR_SLOT_HAT, 'hats', 'No hat', 0, 'common', 'none', null, null, null, 'Go without a hat.'),
        avatarItem('hat_beanie', AVATAR_SLOT_HAT, 'hats', 'Soft Beanie', 100, 'common', 'beanie', '#3F5E58', '#2A403C', '#C4A35A', 'A cozy teal beanie.'),
        avatarItem('hat_cap', AVATAR_SLOT_HAT, 'hats', 'Day Cap', 140, 'common', 'cap', '#2C241C', '#C4A35A', null, 'A simple brimmed cap.'),
        avatarItem('hat_leaf', AVATAR_SLOT_HAT, 'hats', 'Leaf Crown', 320, 'rare', 'leaf', '#7E9A86', '#C4A35A', null, 'A botanical leaf crown.'),
        avatarItem('hat_beret', AVATAR_SLOT_HAT, 'hats', 'Quiet Beret', 400, 'rare', 'beret', '#5C3A28', '#C4A35A', null, 'A tilted earth-tone beret.'),
        avatarItem('hat_crown', AVATAR_SLOT_HAT, 'hats', 'Calm Crown', 1400, 'luxury', 'crown', '#C4A35A', '#8A6C2E', '#F6E7B2', 'A small gold crown.'),

        // Fun: accessories + extras
        avatarItem('acc_none', AVATAR_SLOT_ACCESSORY, 'fun', 'No glasses', 0, 'common', 'none', null, null, null, 'Skip glasses.'),
        avatarItem('acc_glasses', AVATAR_SLOT_ACCESSORY, 'fun', 'Round Glasses', 120, 'common', 'glasses', '#2C241C', '#C4A35A', null, 'Soft round frames.'),
        avatarItem('extra_none', AVATAR_SLOT_EXTRA, 'fun', 'No extra', 0, 'common', 'none', null, null, null, 'Keep the face simple.'),
        avatarItem('extra_blush', AVATAR_SLOT_EXTRA, 'fun', 'Warm Blush', 80, 'common', 'blush', '#E8A090', null, null, 'A quiet rosy blush.'),
        avatarItem('extra_leaf', AVATAR_SLOT_EXTRA, 'fun', 'Leaf Clip', 300, 'rare', 'leafclip', '#7E9A86', '#C4A35A', null, 'A leaf tucked in the hair.'),
        avatarItem('extra_pin', AVATAR_SLOT_EXTRA, 'fun', 'Kinto Pin', 420, 'rare', 'pin', '#C4A35A', '#1A1714', null, 'A tiny gold Kinto pin.'),
        avatarItem('extra_sparkles', AVATAR_SLOT_EXTRA, 'fun', 'Quiet Sparkles', 1300, 'luxury', 'sparkles', '#C4A35A', '#F6E7B2', null, 'Soft gold sparkles around the face.'),
    ];

    $catalog = [];
    foreach ($items as $item) {
        $catalog[$item['id']] = $item;
    }
    return $catalog;
}

/**
 * @return array{id:string,slot:string,tray:string,name:string,price:int,rarity:string,shape:string,fill:?string,fill2:?string,stroke:?string,description:string}
 */
function avatarItem(
    string $id,
    string $slot,
    string $tray,
    string $name,
    int $price,
    string $rarity,
    string $shape,
    ?string $fill,
    ?string $fill2,
    ?string $stroke,
    string $description
): array {
    return [
        'id' => $id,
        'slot' => $slot,
        'tray' => $tray,
        'name' => $name,
        'price' => $price,
        'rarity' => $rarity,
        'shape' => $shape,
        'fill' => $fill,
        'fill2' => $fill2,
        'stroke' => $stroke,
        'description' => $description,
    ];
}

function getAvatarItem(string $itemId): ?array {
    $catalog = getAvatarCatalog();
    return $catalog[$itemId] ?? null;
}

function isValidAvatarItemId(string $itemId): bool {
    return getAvatarItem($itemId) !== null;
}

function isAvatarNoneItem(?array $item): bool {
    return $item !== null && ($item['shape'] ?? '') === 'none';
}

/**
 * @return array<int, array>
 */
function getAvatarItemsForTray(string $tray): array {
    $out = [];
    foreach (getAvatarCatalog() as $item) {
        if ($item['tray'] === $tray) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * Slim visual map for client-side SVG rendering.
 *
 * @return array<string, array{id:string,slot:string,shape:string,fill:?string,fill2:?string,stroke:?string}>
 */
function getAvatarClientCatalog(): array {
    $out = [];
    foreach (getAvatarCatalog() as $id => $item) {
        $out[$id] = [
            'id' => $id,
            'slot' => $item['slot'],
            'shape' => $item['shape'],
            'fill' => $item['fill'],
            'fill2' => $item['fill2'],
            'stroke' => $item['stroke'],
        ];
    }
    return $out;
}
