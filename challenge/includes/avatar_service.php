<?php
/**
 * Avatar Studio: columns, loadout, purchase, equip, public-face flag.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/xp_service.php';
require_once __DIR__ . '/shop_service.php';
require_once __DIR__ . '/avatar_catalog.php';

function ensureAvatarTablesAndColumns(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    ensureShopTablesAndColumns();

    $columns = [
        'equipped_avatar' => "ALTER TABLE users ADD COLUMN equipped_avatar TEXT DEFAULT NULL AFTER equipped_stickers",
        'avatar_public_face' => "ALTER TABLE users ADD COLUMN avatar_public_face TINYINT(1) NOT NULL DEFAULT 0 AFTER equipped_avatar",
    ];

    $allReady = true;
    foreach ($columns as $column => $sql) {
        $exists = function_exists('userColumnExists') && userColumnExists($column);
        if ($exists || !empty($GLOBALS['__avatar_columns_ready'])) {
            continue;
        }
        try {
            dbQuery($sql);
        } catch (Exception $e) {
            error_log("ensureAvatarTablesAndColumns failed for $column: " . $e->getMessage());
            $allReady = false;
        }
    }

    if ($allReady || !empty($GLOBALS['__avatar_columns_ready'])) {
        $GLOBALS['__avatar_columns_ready'] = true;
    }
}

function hasAvatarColumns(): bool {
    ensureAvatarTablesAndColumns();
    if (!empty($GLOBALS['__avatar_columns_ready'])) {
        return true;
    }
    $ready = function_exists('userColumnExists')
        && userColumnExists('equipped_avatar')
        && userColumnExists('avatar_public_face');
    if ($ready) {
        $GLOBALS['__avatar_columns_ready'] = true;
    }
    return $ready;
}

function avatarSelectColumns(): string {
    if (!hasAvatarColumns()) {
        return 'NULL AS equipped_avatar, 0 AS avatar_public_face';
    }
    return 'equipped_avatar, avatar_public_face';
}

function avatarSelectSql(string $alias = 'u'): string {
    if (!hasAvatarColumns()) {
        return ', NULL AS equipped_avatar, 0 AS avatar_public_face';
    }
    return ', ' . $alias . '.equipped_avatar, ' . $alias . '.avatar_public_face';
}

function avatarCsrfToken(): string {
    if (empty($_SESSION['avatar_csrf_token'])) {
        $_SESSION['avatar_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['avatar_csrf_token'];
}

function validAvatarCsrf(?string $token): bool {
    $expected = (string) ($_SESSION['avatar_csrf_token'] ?? '');
    return is_string($token) && $expected !== '' && hash_equals($expected, $token);
}

/**
 * @return array<string, string|null>
 */
function decodeEquippedAvatar($raw): array {
    $defaults = getAvatarDefaultLoadout();
    if (is_array($raw)) {
        $decoded = $raw;
    } elseif (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
    } else {
        $decoded = [];
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $out = $defaults;
    foreach ($defaults as $slot => $defaultId) {
        if (!array_key_exists($slot, $decoded)) {
            continue;
        }
        $value = $decoded[$slot];
        if ($value === null || $value === '') {
            $out[$slot] = in_array($slot, [AVATAR_SLOT_HAT, AVATAR_SLOT_ACCESSORY, AVATAR_SLOT_EXTRA], true)
                ? null
                : $defaultId;
            continue;
        }
        if (!is_string($value)) {
            continue;
        }
        $item = getAvatarItem($value);
        if (!$item || ($item['slot'] ?? '') !== $slot) {
            continue;
        }
        $out[$slot] = isAvatarNoneItem($item) ? null : $value;
    }

    return $out;
}

/**
 * @param array $user Row with equipped_avatar
 * @return array<string, string|null>
 */
function resolveEquippedAvatar(array $user): array {
    return decodeEquippedAvatar($user['equipped_avatar'] ?? null);
}

function userUsesAvatarFace(array $user): bool {
    return !empty($user['avatar_public_face']);
}

function userOwnsAvatarItem(int $userId, string $itemId): bool {
    $item = getAvatarItem($itemId);
    if (!$item) {
        return false;
    }
    if ((int) $item['price'] <= 0) {
        return true;
    }
    return userOwnsShopItem($userId, $itemId);
}

function isAvatarItemEquipped(array $config, string $itemId): bool {
    $item = getAvatarItem($itemId);
    if (!$item) {
        return false;
    }
    $slot = $item['slot'];
    $current = $config[$slot] ?? null;
    if (isAvatarNoneItem($item)) {
        return $current === null || $current === '';
    }
    return $current === $itemId;
}

/**
 * @return array{
 *   balance:int,
 *   total:int,
 *   level:int,
 *   owned:array<int,string>,
 *   owned_set:array<string,bool>,
 *   config:array<string,string|null>,
 *   public_face:bool
 * }
 */
function getAvatarState(int $userId): array {
    ensureAvatarTablesAndColumns();
    $shop = getShopState($userId);
    $row = dbFetchOne(
        'SELECT ' . avatarSelectColumns() . ' FROM users WHERE id = ?',
        [$userId]
    );

    return [
        'balance' => (int) ($shop['balance'] ?? 0),
        'total' => (int) ($shop['total'] ?? 0),
        'level' => (int) ($shop['level'] ?? 1),
        'owned' => $shop['owned'],
        'owned_set' => $shop['owned_set'],
        'config' => decodeEquippedAvatar($row['equipped_avatar'] ?? null),
        'public_face' => !empty($row['avatar_public_face']),
    ];
}

/**
 * @return array{ok:bool,error:string,state?:array}
 */
function purchaseAvatarItem(int $userId, string $itemId): array {
    $item = getAvatarItem($itemId);
    if (!$item) {
        return ['ok' => false, 'error' => 'That avatar item does not exist.'];
    }
    if ((int) $item['price'] <= 0) {
        return ['ok' => true, 'error' => '', 'state' => getAvatarState($userId)];
    }
    $result = purchaseShopItem($userId, $itemId);
    if (!$result['ok']) {
        return $result;
    }
    return [
        'ok' => true,
        'error' => '',
        'state' => getAvatarState($userId),
    ];
}

/**
 * @return array{ok:bool,error:string,state?:array}
 */
function equipAvatarItem(int $userId, string $itemId): array {
    ensureAvatarTablesAndColumns();
    $item = getAvatarItem($itemId);
    if (!$item) {
        return ['ok' => false, 'error' => 'That avatar item does not exist.'];
    }
    if (!userOwnsAvatarItem($userId, $itemId)) {
        return ['ok' => false, 'error' => 'Buy this item before equipping it.'];
    }

    $state = getAvatarState($userId);
    $config = $state['config'];
    $slot = $item['slot'];
    $config[$slot] = isAvatarNoneItem($item) ? null : $itemId;

    try {
        dbQuery(
            'UPDATE users SET equipped_avatar = ? WHERE id = ?',
            [json_encode($config, JSON_UNESCAPED_SLASHES), $userId]
        );
    } catch (Exception $e) {
        error_log('equipAvatarItem failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Unable to equip that item.'];
    }

    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }

    return [
        'ok' => true,
        'error' => '',
        'state' => getAvatarState($userId),
    ];
}

/**
 * @return array{ok:bool,error:string,state?:array}
 */
function setAvatarPublicFace(int $userId, bool $enabled): array {
    ensureAvatarTablesAndColumns();
    try {
        dbQuery(
            'UPDATE users SET avatar_public_face = ? WHERE id = ?',
            [$enabled ? 1 : 0, $userId]
        );
    } catch (Exception $e) {
        error_log('setAvatarPublicFace failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Unable to update your public face.'];
    }

    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }

    return [
        'ok' => true,
        'error' => '',
        'state' => getAvatarState($userId),
    ];
}
