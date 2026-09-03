<?php
/**
 * Calm Points cosmetics shop: inventory, purchase, equip.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/xp_service.php';
require_once __DIR__ . '/shop_catalog.php';

function ensureShopTablesAndColumns(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    ensureXpTablesAndColumns();

    $columns = [
        'equipped_background' => "ALTER TABLE users ADD COLUMN equipped_background VARCHAR(64) DEFAULT NULL AFTER total_calm_points",
        'equipped_banner_pattern' => "ALTER TABLE users ADD COLUMN equipped_banner_pattern VARCHAR(64) DEFAULT NULL AFTER equipped_background",
        'equipped_frame' => "ALTER TABLE users ADD COLUMN equipped_frame VARCHAR(64) DEFAULT NULL AFTER equipped_banner_pattern",
        'equipped_stickers' => "ALTER TABLE users ADD COLUMN equipped_stickers TEXT DEFAULT NULL AFTER equipped_frame",
    ];

    $allReady = true;
    foreach ($columns as $column => $sql) {
        $exists = function_exists('userColumnExists') && userColumnExists($column);
        if ($exists || !empty($GLOBALS['__shop_equip_columns_ready'])) {
            continue;
        }
        try {
            dbQuery($sql);
        } catch (Exception $e) {
            error_log("ensureShopTablesAndColumns failed for $column: " . $e->getMessage());
            $allReady = false;
        }
    }

    if ($allReady || !empty($GLOBALS['__shop_equip_columns_ready'])) {
        $GLOBALS['__shop_equip_columns_ready'] = true;
    }

    try {
        dbQuery(
            "CREATE TABLE IF NOT EXISTS user_shop_inventory (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                item_id VARCHAR(64) NOT NULL,
                purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_item (user_id, item_id),
                INDEX idx_user_shop (user_id),
                CONSTRAINT fk_shop_inv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Exception $e) {
        error_log('ensureShopTablesAndColumns table failed: ' . $e->getMessage());
    }
}

function hasShopEquipColumns(): bool {
    ensureShopTablesAndColumns();
    if (!empty($GLOBALS['__shop_equip_columns_ready'])) {
        return true;
    }
    $ready = function_exists('userColumnExists')
        && userColumnExists('equipped_background')
        && userColumnExists('equipped_banner_pattern')
        && userColumnExists('equipped_frame')
        && userColumnExists('equipped_stickers');
    if ($ready) {
        $GLOBALS['__shop_equip_columns_ready'] = true;
    }
    return $ready;
}

/**
 * SQL fragment for selecting equipped cosmetic columns.
 */
function shopEquipSelectColumns(): string {
    if (!hasShopEquipColumns()) {
        return "NULL AS equipped_background, NULL AS equipped_banner_pattern, NULL AS equipped_frame, NULL AS equipped_stickers";
    }
    return 'equipped_background, equipped_banner_pattern, equipped_frame, equipped_stickers';
}

/**
 * @return array<int, string>
 */
function getOwnedShopItemIds(int $userId): array {
    ensureShopTablesAndColumns();
    $rows = dbFetchAll(
        "SELECT item_id FROM user_shop_inventory WHERE user_id = ?",
        [$userId]
    );
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (string) $row['item_id'];
    }
    return $ids;
}

function userOwnsShopItem(int $userId, string $itemId): bool {
    ensureShopTablesAndColumns();
    $row = dbFetchOne(
        "SELECT id FROM user_shop_inventory WHERE user_id = ? AND item_id = ?",
        [$userId, $itemId]
    );
    return $row !== null;
}

/**
 * @return array{
 *   balance:int,
 *   total:int,
 *   level:int,
 *   owned:array<int,string>,
 *   owned_set:array<string,bool>,
 *   equipped:array{background:?string,banner_pattern:?string,frame:?string,stickers:array<int,string>}
 * }
 */
function getShopState(int $userId): array {
    ensureShopTablesAndColumns();
    $summary = getXpSummary($userId);
    $owned = getOwnedShopItemIds($userId);
    $ownedSet = [];
    foreach ($owned as $id) {
        $ownedSet[$id] = true;
    }

    $equipCols = shopEquipSelectColumns();
    $row = dbFetchOne(
        "SELECT $equipCols FROM users WHERE id = ?",
        [$userId]
    );

    $stickers = [];
    $raw = $row['equipped_stickers'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $sid) {
                if (is_string($sid) && $sid !== '') {
                    $stickers[] = $sid;
                }
            }
        }
    }

    return [
        'balance' => (int) ($summary['current'] ?? 0),
        'total' => (int) ($summary['total'] ?? 0),
        'level' => (int) ($summary['level'] ?? 1),
        'owned' => $owned,
        'owned_set' => $ownedSet,
        'equipped' => [
            'background' => ($row['equipped_background'] ?? null) ?: null,
            'banner_pattern' => ($row['equipped_banner_pattern'] ?? null) ?: null,
            'frame' => ($row['equipped_frame'] ?? null) ?: null,
            'stickers' => $stickers,
        ],
    ];
}

/**
 * Resolve a purchasable item from the Calm Shop or Avatar Studio catalogs.
 */
function resolvePurchasableItem(string $itemId): ?array {
    $item = getShopItem($itemId);
    if ($item) {
        return $item;
    }
    require_once __DIR__ . '/avatar_catalog.php';
    return getAvatarItem($itemId);
}

function purchaseShopItem(int $userId, string $itemId): array {
    ensureShopTablesAndColumns();
    $item = resolvePurchasableItem($itemId);
    if (!$item) {
        return ['ok' => false, 'error' => 'That shop item does not exist.'];
    }

    if (userOwnsShopItem($userId, $itemId)) {
        return ['ok' => false, 'error' => 'You already own this item.'];
    }

    $price = (int) $item['price'];
    $pdo = getDbConnection();

    try {
        $pdo->beginTransaction();

        $user = dbFetchOne(
            "SELECT calm_points FROM users WHERE id = ? FOR UPDATE",
            [$userId]
        );
        if (!$user) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Unable to complete purchase.'];
        }

        if ((int) ($user['calm_points'] ?? 0) < $price) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Not enough Calm Points for this item.'];
        }

        $existing = dbFetchOne(
            "SELECT id FROM user_shop_inventory WHERE user_id = ? AND item_id = ? FOR UPDATE",
            [$userId, $itemId]
        );
        if ($existing) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'You already own this item.'];
        }

        if (!spendCalmPoints($userId, $price)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Not enough Calm Points for this item.'];
        }

        dbQuery(
            "INSERT INTO user_shop_inventory (user_id, item_id, purchased_at) VALUES (?, ?, UTC_TIMESTAMP())",
            [$userId, $itemId]
        );

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('purchaseShopItem failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Unable to complete purchase. Please try again.'];
    }

    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }

    return [
        'ok' => true,
        'error' => '',
        'state' => getShopState($userId),
    ];
}

/**
 * @return array{ok:bool,error:string,state?:array}
 */
function equipShopItem(int $userId, string $itemId): array {
    ensureShopTablesAndColumns();
    $item = getShopItem($itemId);
    if (!$item) {
        return ['ok' => false, 'error' => 'That shop item does not exist.'];
    }

    if (!userOwnsShopItem($userId, $itemId)) {
        return ['ok' => false, 'error' => 'Buy this item before equipping it.'];
    }

    $category = $item['category'];
    $state = getShopState($userId);
    $equipped = $state['equipped'];

    try {
        if ($category === SHOP_CATEGORY_BACKGROUND) {
            dbQuery(
                "UPDATE users SET equipped_background = ? WHERE id = ?",
                [$itemId, $userId]
            );
        } elseif ($category === SHOP_CATEGORY_BANNER) {
            dbQuery(
                "UPDATE users SET equipped_banner_pattern = ? WHERE id = ?",
                [$itemId, $userId]
            );
        } elseif ($category === SHOP_CATEGORY_FRAME) {
            dbQuery(
                "UPDATE users SET equipped_frame = ? WHERE id = ?",
                [$itemId, $userId]
            );
        } elseif ($category === SHOP_CATEGORY_STICKER) {
            $stickers = $equipped['stickers'];
            if (in_array($itemId, $stickers, true)) {
                return ['ok' => true, 'error' => '', 'state' => $state];
            }
            if (count($stickers) >= SHOP_MAX_STICKERS) {
                return [
                    'ok' => false,
                    'error' => 'You can equip up to ' . SHOP_MAX_STICKERS . ' stickers. Unequip one first.',
                ];
            }
            $stickers[] = $itemId;
            dbQuery(
                "UPDATE users SET equipped_stickers = ? WHERE id = ?",
                [json_encode(array_values($stickers)), $userId]
            );
        } else {
            return ['ok' => false, 'error' => 'Unknown item category.'];
        }
    } catch (Exception $e) {
        error_log('equipShopItem failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Unable to equip that item.'];
    }

    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }

    return [
        'ok' => true,
        'error' => '',
        'state' => getShopState($userId),
    ];
}

/**
 * @return array{ok:bool,error:string,state?:array}
 */
function unequipShopItem(int $userId, string $itemId): array {
    ensureShopTablesAndColumns();
    $item = getShopItem($itemId);
    if (!$item) {
        return ['ok' => false, 'error' => 'That shop item does not exist.'];
    }

    $category = $item['category'];

    try {
        if ($category === SHOP_CATEGORY_BACKGROUND) {
            dbQuery(
                "UPDATE users SET equipped_background = NULL WHERE id = ? AND equipped_background = ?",
                [$userId, $itemId]
            );
        } elseif ($category === SHOP_CATEGORY_BANNER) {
            dbQuery(
                "UPDATE users SET equipped_banner_pattern = NULL WHERE id = ? AND equipped_banner_pattern = ?",
                [$userId, $itemId]
            );
        } elseif ($category === SHOP_CATEGORY_FRAME) {
            dbQuery(
                "UPDATE users SET equipped_frame = NULL WHERE id = ? AND equipped_frame = ?",
                [$userId, $itemId]
            );
        } elseif ($category === SHOP_CATEGORY_STICKER) {
            $state = getShopState($userId);
            $stickers = array_values(array_filter(
                $state['equipped']['stickers'],
                static fn($sid) => $sid !== $itemId
            ));
            dbQuery(
                "UPDATE users SET equipped_stickers = ? WHERE id = ?",
                [json_encode($stickers), $userId]
            );
        } else {
            return ['ok' => false, 'error' => 'Unknown item category.'];
        }
    } catch (Exception $e) {
        error_log('unequipShopItem failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Unable to unequip that item.'];
    }

    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }

    return [
        'ok' => true,
        'error' => '',
        'state' => getShopState($userId),
    ];
}

/**
 * SQL fragment to select equipped_frame from a users alias.
 */
function shopFrameSelectSql(string $alias = 'u'): string {
    if (!hasShopEquipColumns()) {
        return ', NULL AS equipped_frame';
    }
    return ', ' . $alias . '.equipped_frame';
}

/**
 * Attach resolved profile pic URL + frame CSS for feed payloads.
 */
function withFeedUserCosmetics(array $row): array {
    $row = withProfilePicUrl($row);
    $row['frame_css'] = resolveFrameCssFromId($row['equipped_frame'] ?? null);
    if (file_exists(__DIR__ . '/avatar_render.php')) {
        require_once __DIR__ . '/avatar_render.php';
        $row = attachUserPublicFace($row);
    }
    return $row;
}

/**
 * @param array<int, array> $rows
 * @return array<int, array>
 */
function withFeedUserCosmeticsList(array $rows): array {
    return array_map('withFeedUserCosmetics', $rows);
}

/**
 * Whether an item id is currently equipped.
 */
function isShopItemEquipped(array $state, string $itemId): bool {
    $eq = $state['equipped'] ?? [];
    if (($eq['background'] ?? null) === $itemId) {
        return true;
    }
    if (($eq['banner_pattern'] ?? null) === $itemId) {
        return true;
    }
    if (($eq['frame'] ?? null) === $itemId) {
        return true;
    }
    return in_array($itemId, $eq['stickers'] ?? [], true);
}
