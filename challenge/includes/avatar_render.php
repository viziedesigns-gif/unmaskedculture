<?php
/**
 * Layered SVG avatar renderer.
 */

require_once __DIR__ . '/avatar_catalog.php';
require_once __DIR__ . '/avatar_service.php';
require_once __DIR__ . '/functions.php';

/**
 * @param array<string, string|null> $config
 * @param array{size?:string,animate?:bool,class?:string} $options
 */
function renderKintoAvatar(array $config, array $options = []): string {
    $size = (string) ($options['size'] ?? 'md');
    if (!in_array($size, ['sm', 'md', 'lg', 'xl'], true)) {
        $size = 'md';
    }
    $animate = array_key_exists('animate', $options)
        ? (bool) $options['animate']
        : in_array($size, ['lg', 'xl'], true);
    $extraClass = trim((string) ($options['class'] ?? ''));

    $resolved = decodeEquippedAvatar($config);
    $layers = avatarResolvedLayers($resolved);

    $classes = trim('kinto-avatar kinto-avatar--' . $size . ($animate ? ' kinto-avatar--animate' : '') . ' ' . $extraClass);
    $skin = $layers['skin'];
    $line = $skin['stroke'] ?? '#B07848';

    $html = '<svg class="' . h($classes) . '" viewBox="0 0 200 240" role="img" aria-hidden="true" focusable="false">';
    $html .= '<g class="kinto-avatar__bob">';
    $html .= avatarHairBackSvg($layers['hair']);
    $html .= avatarBodySvg($skin);
    $html .= avatarOutfitSvg($layers['outfit']);
    $html .= '<g class="kinto-avatar__head">';
    $html .= avatarHeadSvg($skin);
    $html .= '<g class="kinto-avatar__face">';
    $html .= avatarBlushSvg($layers['extra']);
    $html .= avatarEyesSvg($layers['eyes']);
    $html .= avatarBrowsSvg($layers['hair'], $line);
    $html .= avatarMouthSvg($line);
    $html .= '</g></g>';
    $html .= avatarHairFrontSvg($layers['hair']);
    $html .= avatarLeafClipSvg($layers['extra']);
    $html .= avatarHatSvg($layers['hat']);
    $html .= avatarGlassesSvg($layers['accessory']);
    $html .= avatarPinSvg($layers['extra']);
    $html .= avatarSparklesSvg($layers['extra']);
    $html .= '</g></svg>';

    return $html;
}

/**
 * @param array<string, string|null> $config
 * @return array<string, array|null>
 */
function avatarResolvedLayers(array $config): array {
    $slots = [AVATAR_SLOT_SKIN, AVATAR_SLOT_HAIR, AVATAR_SLOT_EYES, AVATAR_SLOT_OUTFIT, AVATAR_SLOT_HAT, AVATAR_SLOT_ACCESSORY, AVATAR_SLOT_EXTRA];
    $out = [];
    foreach ($slots as $slot) {
        $id = $config[$slot] ?? null;
        $item = $id ? getAvatarItem($id) : null;
        $out[$slot] = $item && !isAvatarNoneItem($item) ? $item : null;
    }
    if ($out[AVATAR_SLOT_SKIN] === null) {
        $out[AVATAR_SLOT_SKIN] = getAvatarItem('skin_warm');
    }
    if ($out[AVATAR_SLOT_HAIR] === null) {
        $out[AVATAR_SLOT_HAIR] = getAvatarItem('hair_short');
    }
    if ($out[AVATAR_SLOT_EYES] === null) {
        $out[AVATAR_SLOT_EYES] = getAvatarItem('eyes_round');
    }
    if ($out[AVATAR_SLOT_OUTFIT] === null) {
        $out[AVATAR_SLOT_OUTFIT] = getAvatarItem('outfit_tee');
    }
    return $out;
}

function renderUserPublicFace(array $user, string $size = 'sm', array $options = []): string {
    require_once __DIR__ . '/avatar_service.php';
    if (userUsesAvatarFace($user)) {
        $opts = array_merge(['size' => $size, 'animate' => $size === 'lg' || $size === 'xl'], $options);
        return renderKintoAvatar(resolveEquippedAvatar($user), $opts);
    }

    $url = profilePicUrl($user['profile_pic'] ?? null);
    if ($url !== '') {
        return '<img src="' . h($url) . '" alt="">';
    }

    $initial = strtoupper(substr((string) ($user['first_name'] ?: 'U'), 0, 1));
    return '<span class="avatar-placeholder">' . h($initial) . '</span>';
}

function attachUserPublicFace(array $row): array {
    require_once __DIR__ . '/avatar_service.php';
    $use = userUsesAvatarFace($row);
    $row['use_avatar'] = $use;
    if ($use) {
        $config = resolveEquippedAvatar($row);
        $row['avatar'] = $config;
        $row['avatar_html'] = renderKintoAvatar($config, ['size' => 'sm', 'animate' => false]);
    } else {
        $row['avatar'] = null;
        $row['avatar_html'] = '';
    }
    return $row;
}

function avatarHeadSvg(array $skin): string {
    $fill = h((string) $skin['fill']);
    $stroke = h((string) ($skin['stroke'] ?? $skin['fill2']));
    return '<g class="kinto-avatar__skin">'
        . '<ellipse class="kinto-avatar__ear kinto-avatar__ear--l" cx="48" cy="90" rx="11" ry="14" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2"/>'
        . '<ellipse class="kinto-avatar__ear kinto-avatar__ear--r" cx="152" cy="90" rx="11" ry="14" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2"/>'
        . '<circle cx="100" cy="86" r="54" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2.4"/>'
        . '</g>';
}

function avatarBodySvg(array $skin): string {
    $fill = h((string) $skin['fill']);
    $stroke = h((string) ($skin['stroke'] ?? $skin['fill2']));
    return '<g class="kinto-avatar__body">'
        . '<rect x="90" y="132" width="20" height="16" rx="7" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2"/>'
        . '<ellipse cx="100" cy="188" rx="44" ry="36" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2.2"/>'
        . '</g>';
}

function avatarHairBackSvg(?array $hair): string {
    if (!$hair) {
        return '';
    }
    $fill = h((string) $hair['fill']);
    $fill2 = h((string) ($hair['fill2'] ?? $hair['fill']));
    $shape = (string) $hair['shape'];
    if (in_array($shape, ['wave', 'long', 'locs'], true)) {
        $d = $shape === 'long'
            ? 'M46 92c-8 22-6 58 8 78 6-18 18-28 46-28s40 10 46 28c14-20 16-56 8-78-18 14-38 20-54 20s-36-6-54-20z'
            : 'M40 88c-6 20-2 52 12 66 8-16 20-24 48-24s40 8 48 24c14-14 18-46 12-66-16 12-36 20-60 20S56 100 40 88z';
        return '<path class="kinto-avatar__hair-back" d="' . $d . '" fill="' . $fill2 . '"/>';
    }
    if ($shape === 'bob') {
        return '<ellipse class="kinto-avatar__hair-back" cx="100" cy="96" rx="62" ry="58" fill="' . $fill2 . '"/>';
    }
    return '';
}

function avatarHairFrontSvg(?array $hair): string {
    if (!$hair) {
        return '';
    }
    $fill = h((string) $hair['fill']);
    $fill2 = h((string) ($hair['fill2'] ?? $hair['fill']));
    $shape = (string) $hair['shape'];
    $out = '<g class="kinto-avatar__hair-front">';

    switch ($shape) {
        case 'short':
            $out .= '<path d="M48 86c2-42 28-62 52-62s50 20 52 62c-14-18-30-26-52-26S62 68 48 86z" fill="' . $fill . '"/>';
            break;
        case 'bob':
            $out .= '<path d="M42 92c4-48 30-70 58-70s54 22 58 70c-12-10-28-16-58-16S54 82 42 92z" fill="' . $fill . '"/>';
            $out .= '<path d="M46 118c6 18 18 28 54 28s48-10 54-28c-10 8-28 14-54 14s-44-6-54-14z" fill="' . $fill2 . '"/>';
            break;
        case 'curl':
            $out .= '<path d="M48 84c4-40 26-58 52-58s48 18 52 58c-10-14-24-22-52-22S58 70 48 84z" fill="' . $fill . '"/>';
            $out .= '<circle cx="62" cy="52" r="14" fill="' . $fill . '"/><circle cx="86" cy="40" r="15" fill="' . $fill2 . '"/>';
            $out .= '<circle cx="114" cy="38" r="16" fill="' . $fill . '"/><circle cx="140" cy="52" r="14" fill="' . $fill2 . '"/>';
            break;
        case 'wave':
            $out .= '<path d="M44 90c6-46 30-68 56-68s50 22 56 68c-14-16-32-24-56-24S58 74 44 90z" fill="' . $fill . '"/>';
            $out .= '<path d="M38 100c8 8 14 4 20-2 8 10 18 8 26 0 8 10 20 8 28 0 8 8 18 10 26 2 8 8 16 10 24 0-6 22-18 38-50 38s-46-14-54-38z" fill="' . $fill2 . '" opacity=".55"/>';
            break;
        case 'bun':
            $out .= '<circle cx="100" cy="28" r="18" fill="' . $fill . '"/>';
            $out .= '<circle cx="100" cy="28" r="10" fill="' . $fill2 . '"/>';
            $out .= '<path d="M50 86c4-40 26-56 50-56s46 16 50 56c-12-16-28-24-50-24S62 70 50 86z" fill="' . $fill . '"/>';
            break;
        case 'locs':
            $out .= '<path d="M48 84c4-40 28-58 52-58s48 18 52 58c-12-16-30-24-52-24S60 68 48 84z" fill="' . $fill . '"/>';
            foreach ([58, 72, 86, 100, 114, 128, 142] as $i => $x) {
                $hgt = 46 + ($i % 3) * 8;
                $out .= '<rect x="' . ($x - 5) . '" y="42" width="10" height="' . $hgt . '" rx="5" fill="' . ($i % 2 ? $fill2 : $fill) . '"/>';
            }
            break;
        case 'fade':
            $out .= '<path d="M54 88c2-36 24-50 46-50s44 14 46 50c-12-12-26-18-46-18S66 76 54 88z" fill="' . $fill . '"/>';
            $out .= '<path d="M46 96c8-8 16-12 22-8 0 14-6 28-22 34-4-8-4-18 0-26z" fill="' . $fill2 . '"/>';
            $out .= '<path d="M154 96c-8-8-16-12-22-8 0 14 6 28 22 34 4-8 4-18 0-26z" fill="' . $fill2 . '"/>';
            break;
        case 'long':
            $out .= '<path d="M46 88c6-46 30-66 54-66s48 20 54 66c-16-18-34-26-54-26S62 70 46 88z" fill="' . $fill . '"/>';
            break;
        case 'braid':
            $out .= '<path d="M48 86c4-42 28-60 52-60s48 18 52 60c-14-16-30-24-52-24S62 70 48 86z" fill="' . $fill . '"/>';
            $out .= '<path d="M58 48c12 8 28 10 42 10s30-2 42-10c-8 16-24 26-42 26S66 64 58 48z" fill="' . $fill2 . '"/>';
            $out .= '<circle cx="78" cy="52" r="6" fill="#F6E7B2"/><circle cx="100" cy="56" r="6" fill="#F6E7B2"/><circle cx="122" cy="52" r="6" fill="#F6E7B2"/>';
            break;
        default:
            $out .= '<path d="M48 86c2-42 28-62 52-62s50 20 52 62c-14-18-30-26-52-26S62 68 48 86z" fill="' . $fill . '"/>';
    }

    $out .= '</g>';
    return $out;
}

function avatarOutfitSvg(?array $outfit): string {
    if (!$outfit) {
        return '';
    }
    $fill = h((string) $outfit['fill']);
    $fill2 = h((string) ($outfit['fill2'] ?? $outfit['fill']));
    $accent = h((string) ($outfit['stroke'] ?? $outfit['fill2'] ?? $outfit['fill']));
    $shape = (string) $outfit['shape'];
    $out = '<g class="kinto-avatar__outfit">';
    $out .= '<path d="M56 164c8-18 20-28 44-28s36 10 44 28c6 16 8 36 0 48H56c-8-12-6-32 0-48z" fill="' . $fill . '"/>';

    switch ($shape) {
        case 'henley':
            $out .= '<path d="M88 138c4 10 8 14 12 14s8-4 12-14" fill="none" stroke="' . $accent . '" stroke-width="2"/>';
            $out .= '<circle cx="100" cy="158" r="2.4" fill="' . $accent . '"/><circle cx="100" cy="168" r="2.4" fill="' . $accent . '"/>';
            break;
        case 'collar':
            $out .= '<path d="M78 140l22 16 22-16" fill="' . $fill2 . '"/>';
            break;
        case 'wrap':
            $out .= '<path d="M62 168c20-10 38-6 38 6 0-16 22-22 40-8-18 18-40 24-78 2z" fill="' . $fill2 . '" opacity=".7"/>';
            break;
        case 'vneck':
            $out .= '<path d="M82 140l18 22 18-22" fill="none" stroke="' . $accent . '" stroke-width="2.4"/>';
            break;
        case 'sash':
            $out .= '<path d="M70 176l60-8 8 16-64 10z" fill="' . $fill2 . '"/>';
            $out .= '<path d="M86 140l14 18 14-18" fill="none" stroke="' . $accent . '" stroke-width="2"/>';
            break;
        case 'gold':
            $out .= '<path d="M74 142h52l-8 14H82z" fill="' . $fill2 . '"/>';
            $out .= '<path d="M70 184h60" stroke="' . $accent . '" stroke-width="3"/>';
            break;
        default:
            $out .= '<path d="M86 140c4 8 8 12 14 12s10-4 14-12" fill="none" stroke="' . $accent . '" stroke-width="2" opacity=".55"/>';
    }

    $out .= '</g>';
    return $out;
}

function avatarEyesSvg(?array $eyes): string {
    if (!$eyes) {
        return '';
    }
    $iris = h((string) $eyes['fill']);
    $white = h((string) ($eyes['fill2'] ?? '#F7F1E6'));
    $shape = (string) $eyes['shape'];
    $out = '<g class="kinto-avatar__look"><g class="kinto-avatar__eyes">';

    $specs = [
        'round' => [10, 10, 5.2],
        'soft' => [11, 9, 4.8],
        'almond' => [12, 8, 4.6],
        'bright' => [11.5, 11, 5.6],
        'sleepy' => [12, 7, 3.8],
        'spark' => [10.5, 10.5, 5],
    ];
    [$rx, $ry, $pr] = $specs[$shape] ?? $specs['round'];

    foreach ([[80, 88], [120, 88]] as $i => $pos) {
        [$cx, $cy] = $pos;
        $out .= '<g class="kinto-avatar__eye" transform="translate(' . $cx . ' ' . $cy . ')">';
        $out .= '<ellipse class="kinto-avatar__eye-white" cx="0" cy="0" rx="' . $rx . '" ry="' . $ry . '" fill="' . $white . '"/>';
        $out .= '<circle class="kinto-avatar__iris" cx="0" cy="1" r="' . $pr . '" fill="' . $iris . '"/>';
        $out .= '<circle class="kinto-avatar__pupil" cx="0" cy="1.4" r="' . ($pr * 0.46) . '" fill="#1A1714"/>';
        $out .= '<circle class="kinto-avatar__shine" cx="-2" cy="-1.4" r="1.6" fill="#fff"/>';
        if ($shape === 'spark') {
            $out .= '<path d="M6-7l1.1 2.4 2.5.2-2 1.8.7 2.5L6-1.6 3.7-.1l.7-2.5-2-1.8 2.5-.2z" fill="#C4A35A"/>';
        }
        if ($shape === 'sleepy') {
            $out .= '<path d="M-' . ($rx) . ' -1 Q0 ' . (-$ry - 1) . ' ' . $rx . ' -1" fill="' . $white . '" stroke="#1A1714" stroke-width="1.4"/>';
        }
        $out .= '</g>';
    }

    $out .= '</g></g>';
    return $out;
}

function avatarBrowsSvg(?array $hair, string $line): string {
    $color = h((string) ($hair['fill'] ?? $line));
    return '<g class="kinto-avatar__brows" stroke="' . $color . '" stroke-width="3.2" stroke-linecap="round" fill="none">'
        . '<path d="M68 72q12-8 24 0"/><path d="M108 72q12-8 24 0"/>'
        . '</g>';
}

function avatarMouthSvg(string $line): string {
    return '<path class="kinto-avatar__mouth" d="M88 112q12 10 24 0" fill="none" stroke="' . h($line) . '" stroke-width="2.8" stroke-linecap="round"/>';
}

function avatarBlushSvg(?array $extra): string {
    if (!$extra || ($extra['shape'] ?? '') !== 'blush') {
        return '';
    }
    $fill = h((string) $extra['fill']);
    return '<g class="kinto-avatar__blush" fill="' . $fill . '" opacity=".55">'
        . '<ellipse cx="62" cy="104" rx="10" ry="6"/><ellipse cx="138" cy="104" rx="10" ry="6"/>'
        . '</g>';
}

function avatarHatSvg(?array $hat): string {
    if (!$hat) {
        return '';
    }
    $fill = h((string) $hat['fill']);
    $fill2 = h((string) ($hat['fill2'] ?? $hat['fill']));
    $shape = (string) $hat['shape'];
    $out = '<g class="kinto-avatar__hat">';
    switch ($shape) {
        case 'beanie':
            $out .= '<path d="M52 70c6-38 28-54 48-54s42 16 48 54c-16-10-32-14-48-14S68 60 52 70z" fill="' . $fill . '"/>';
            $out .= '<ellipse cx="100" cy="70" rx="50" ry="8" fill="' . $fill2 . '"/>';
            $out .= '<circle cx="100" cy="18" r="6" fill="' . h((string) ($hat['stroke'] ?? $fill2)) . '"/>';
            break;
        case 'cap':
            $out .= '<path d="M54 72c8-34 26-46 46-46s38 12 46 46H54z" fill="' . $fill . '"/>';
            $out .= '<path d="M50 72h92c-8 8-28 12-46 12s-38-4-46-12z" fill="' . $fill2 . '"/>';
            $out .= '<path d="M132 70c18 2 28 8 34 14-16 2-30 0-40-6z" fill="' . $fill . '"/>';
            break;
        case 'leaf':
            $out .= '<path d="M58 48c18-22 40-18 42-2 2-16 24-20 42 2-18 8-32 16-42 16S76 56 58 48z" fill="' . $fill . '"/>';
            $out .= '<path d="M100 46c-8 8-14 16-16 24" fill="none" stroke="' . $fill2 . '" stroke-width="2"/>';
            break;
        case 'beret':
            $out .= '<ellipse cx="96" cy="48" rx="46" ry="20" fill="' . $fill . '"/>';
            $out .= '<ellipse cx="100" cy="62" rx="40" ry="8" fill="' . $fill2 . '"/>';
            $out .= '<circle cx="56" cy="42" r="5" fill="' . $fill . '"/>';
            break;
        case 'crown':
            $out .= '<path d="M58 64l10-22 16 14 16-20 16 20 16-14 10 22z" fill="' . $fill . '" stroke="' . $fill2 . '" stroke-width="2"/>';
            $out .= '<circle cx="84" cy="50" r="3.2" fill="#F6E7B2"/><circle cx="100" cy="42" r="3.2" fill="#F6E7B2"/><circle cx="116" cy="50" r="3.2" fill="#F6E7B2"/>';
            break;
    }
    $out .= '</g>';
    return $out;
}

function avatarGlassesSvg(?array $accessory): string {
    if (!$accessory || ($accessory['shape'] ?? '') !== 'glasses') {
        return '';
    }
    $fill = h((string) $accessory['fill']);
    $fill2 = h((string) ($accessory['fill2'] ?? $accessory['fill']));
    return '<g class="kinto-avatar__glasses" fill="none" stroke="' . $fill . '" stroke-width="3">'
        . '<circle cx="80" cy="88" r="14"/><circle cx="120" cy="88" r="14"/>'
        . '<path d="M94 88h12" stroke="' . $fill2 . '" stroke-width="2.4"/>'
        . '</g>';
}

function avatarLeafClipSvg(?array $extra): string {
    if (!$extra || ($extra['shape'] ?? '') !== 'leafclip') {
        return '';
    }
    $fill = h((string) $extra['fill']);
    $fill2 = h((string) ($extra['fill2'] ?? $extra['fill']));
    return '<g class="kinto-avatar__leafclip" transform="translate(138 44) rotate(28)">'
        . '<ellipse cx="0" cy="0" rx="9" ry="16" fill="' . $fill . '"/>'
        . '<path d="M0-12v22" stroke="' . $fill2 . '" stroke-width="1.6"/>'
        . '</g>';
}

function avatarPinSvg(?array $extra): string {
    if (!$extra || ($extra['shape'] ?? '') !== 'pin') {
        return '';
    }
    $fill = h((string) $extra['fill']);
    $fill2 = h((string) ($extra['fill2'] ?? '#1A1714'));
    return '<g class="kinto-avatar__pin" transform="translate(132 168)">'
        . '<circle cx="0" cy="0" r="8" fill="' . $fill . '"/>'
        . '<circle cx="0" cy="0" r="4.2" fill="' . $fill2 . '"/>'
        . '</g>';
}

function avatarSparklesSvg(?array $extra): string {
    if (!$extra || ($extra['shape'] ?? '') !== 'sparkles') {
        return '';
    }
    $fill = h((string) $extra['fill']);
    $fill2 = h((string) ($extra['fill2'] ?? $extra['fill']));
    return '<g class="kinto-avatar__sparkles" fill="' . $fill . '">'
        . '<path class="kinto-avatar__spark kinto-avatar__spark--a" d="M36 70l2.2 5 5.2.4-4.2 3.4 1.4 5.2L36 80.6 31.4 83.8l1.4-5.2-4.2-3.4 5.2-.4z"/>'
        . '<path class="kinto-avatar__spark kinto-avatar__spark--b" d="M164 62l2 4.4 4.6.4-3.6 3 1.2 4.6L164 71.4 159.8 74.4l1.2-4.6-3.6-3 4.6-.4z" fill="' . $fill2 . '"/>'
        . '<path class="kinto-avatar__spark kinto-avatar__spark--c" d="M168 120l1.6 3.6 3.8.3-3 2.5 1 3.8L168 128l-3.4 2.4 1-3.8-3-2.5 3.8-.3z"/>'
        . '</g>';
}
