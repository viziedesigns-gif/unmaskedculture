<?php
/**
 * Authentication Functions
 * Kinto App
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/retention_service.php';
require_once __DIR__ . '/admin_service.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Get current logged in user ID
 * @return int|null
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check whether a column exists on a table. Cached for the request so
 * we don't pay an INFORMATION_SCHEMA roundtrip on every call.
 * Used to keep getCurrentUser() resilient when migrations haven't been
 * run yet on the production database (e.g. water_bottle_oz).
 */
function userColumnExists(string $column): bool {
    static $cache = [];
    if (isset($cache[$column])) {
        return $cache[$column];
    }
    try {
        $row = dbFetchOne(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?",
            [$column]
        );
        $cache[$column] = ((int) ($row['c'] ?? 0)) > 0;
    } catch (Exception $e) {
        // If introspection fails for any reason, assume the column is
        // absent so we use the safe SELECT path.
        $cache[$column] = false;
    }
    return $cache[$column];
}

/**
 * Reset the cached result of userColumnExists() for a given column.
 * Called after a successful ALTER TABLE so the next check picks up the change.
 */
function resetUserColumnExistsCache(string $column): void {
    // Re-run userColumnExists by unsetting via reflection-like workaround:
    // we simply force a fresh probe using a sentinel check below.
    // The static cache lives in userColumnExists()'s scope, so we trigger
    // a fresh INFORMATION_SCHEMA probe via the same function after a brief
    // flag toggle. Simpler: expose a small helper that mirrors the query.
    // (Kept intentionally minimal - see ensureWaterBottleColumn().)
    unset($GLOBALS['__col_cache_' . $column]);
}

/**
 * Ensure the users.water_bottle_oz column exists. Runs at most once per
 * request. If the column is missing, performs a best-effort ALTER TABLE
 * so subsequent reads/writes can use it. Safe to call before any query
 * that depends on the column.
 */
function ensureWaterBottleColumn(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (userColumnExists('water_bottle_oz')) {
        return;
    }

    try {
        dbQuery(
            "ALTER TABLE users ADD COLUMN water_bottle_oz INT NOT NULL DEFAULT 24 AFTER daily_water_oz"
        );
        // Re-probe so the static cache in userColumnExists() reflects the
        // new reality. We can't reach into its static array directly, so
        // we verify via a direct query and manually seed the cache.
        $row = dbFetchOne(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'water_bottle_oz'"
        );
        if (((int) ($row['c'] ?? 0)) > 0) {
            // Force userColumnExists() to re-evaluate by calling it in a
            // way that overrides its cache: we can't mutate its static,
            // but we can rely on PHP's per-request lifetime - the ALTER
            // persists, but callers this request still see cached false.
            // Work around by stashing a global hint that callers can check.
            $GLOBALS['__water_bottle_column_ready'] = true;
        }
    } catch (Exception $e) {
        error_log("ensureWaterBottleColumn failed: " . $e->getMessage());
    }
}

/**
 * Cache-aware wrapper for the water_bottle_oz presence check.
 * Respects both the static cache in userColumnExists() and the
 * $GLOBALS override set after a successful auto-migration this request.
 */
function hasWaterBottleColumn(): bool {
    if (!empty($GLOBALS['__water_bottle_column_ready'])) {
        return true;
    }
    return userColumnExists('water_bottle_oz');
}

/**
 * Ensure optional public profile columns exist on older databases.
 */
function ensurePublicProfileColumns(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'profile_bio' => "ALTER TABLE users ADD COLUMN profile_bio TEXT DEFAULT NULL AFTER profile_pic",
        'profile_prompt_key' => "ALTER TABLE users ADD COLUMN profile_prompt_key VARCHAR(50) DEFAULT NULL AFTER profile_bio",
        'profile_prompt_answer' => "ALTER TABLE users ADD COLUMN profile_prompt_answer TEXT DEFAULT NULL AFTER profile_prompt_key",
        'profile_visible' => "ALTER TABLE users ADD COLUMN profile_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_prompt_answer",
        'profile_banner_x' => "ALTER TABLE users ADD COLUMN profile_banner_x TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER profile_visible",
        'profile_banner_y' => "ALTER TABLE users ADD COLUMN profile_banner_y TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER profile_banner_x",
        'profile_banner_zoom' => "ALTER TABLE users ADD COLUMN profile_banner_zoom DECIMAL(4,2) NOT NULL DEFAULT 1.00 AFTER profile_banner_y",
        'profile_banner_text_color' => "ALTER TABLE users ADD COLUMN profile_banner_text_color VARCHAR(7) DEFAULT NULL AFTER profile_banner_zoom",
        'public_profile_slug' => "ALTER TABLE users ADD COLUMN public_profile_slug VARCHAR(48) DEFAULT NULL AFTER profile_banner_text_color",
        'last_active_at' => "ALTER TABLE users ADD COLUMN last_active_at DATETIME DEFAULT NULL AFTER public_profile_slug"
    ];

    foreach ($columns as $column => $sql) {
        if (userColumnExists($column)) {
            continue;
        }

        try {
            dbQuery($sql);
            $GLOBALS['__public_profile_column_' . $column] = true;
        } catch (Exception $e) {
            error_log("ensurePublicProfileColumns failed for $column: " . $e->getMessage());
        }
    }

    foreach ([
        'idx_public_profile_slug' => 'ALTER TABLE users ADD UNIQUE INDEX idx_public_profile_slug (public_profile_slug)',
        'idx_last_active_at' => 'ALTER TABLE users ADD INDEX idx_last_active_at (last_active_at)',
    ] as $indexName => $indexSql) {
        try {
            $index = dbFetchOne(
                "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = ?",
                [$indexName]
            );
            if ((int) ($index['c'] ?? 0) === 0) {
                dbQuery($indexSql);
            }
        } catch (Exception $e) {
            error_log("ensurePublicProfileColumns failed for index $indexName: " . $e->getMessage());
        }
    }
}

function hasPublicProfileColumn(string $column): bool {
    return !empty($GLOBALS['__public_profile_column_' . $column]) || userColumnExists($column);
}

function generatePublicProfileSlug(): string {
    do {
        $slug = bin2hex(random_bytes(12));
    } while (dbFetchOne("SELECT id FROM users WHERE public_profile_slug = ?", [$slug]));
    return $slug;
}

/**
 * Ensure the admin flag exists on older databases.
 */
function ensureAdminColumn(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (userColumnExists('is_admin')) {
        return;
    }

    try {
        dbQuery(
            "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER onboarding_completed"
        );
        $GLOBALS['__admin_column_ready'] = true;
    } catch (Exception $e) {
        error_log("ensureAdminColumn failed: " . $e->getMessage());
    }
}

function hasAdminColumn(): bool {
    if (!empty($GLOBALS['__admin_column_ready'])) {
        return true;
    }
    return userColumnExists('is_admin');
}

/**
 * Get current logged in user data.
 * Per-request cache (keyed on user id in $GLOBALS) so repeated calls on
 * the same page - header.php, requireOnboarding(), page-level logic -
 * don't each fire their own SELECT.
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    $uid = getCurrentUserId();
    if (isset($GLOBALS['__current_user_cache'][$uid])) {
        return $GLOBALS['__current_user_cache'][$uid];
    }

    // Self-heal newer optional columns so production installs keep up with
    // small app updates without a separate migration step.
    ensureWaterBottleColumn();
    ensurePublicProfileColumns();
    ensureAdminColumn();
    ensureAdminInfrastructure();
    if (function_exists('ensureRetentionColumns')) {
        ensureRetentionColumns();
    }
    if (file_exists(__DIR__ . '/xp_service.php')) {
        require_once __DIR__ . '/xp_service.php';
        ensureXpTablesAndColumns();
    }
    if (file_exists(__DIR__ . '/shop_service.php')) {
        require_once __DIR__ . '/shop_service.php';
        ensureShopTablesAndColumns();
    }
    if (file_exists(__DIR__ . '/avatar_service.php')) {
        require_once __DIR__ . '/avatar_service.php';
        ensureAvatarTablesAndColumns();
    }
    $hasBottle = hasWaterBottleColumn();
    $bottleColumn = $hasBottle ? 'water_bottle_oz' : '24 AS water_bottle_oz';
    $adminColumn = hasAdminColumn() ? 'is_admin' : '0 AS is_admin';
    $adminRoleColumn = userColumnExists('admin_role') || !empty($GLOBALS['__admin_infrastructure_ready'])
        ? 'admin_role, auth_version'
        : "'user' AS admin_role, 1 AS auth_version";
    $retentionColumns = hasRetentionColumns()
        ? 'daily_reminder_enabled, daily_reminder_time, streak_risk_enabled'
        : '1 AS daily_reminder_enabled, \'18:00:00\' AS daily_reminder_time, 1 AS streak_risk_enabled';
    $modeColumns = (function_exists('userColumnExists') && userColumnExists('challenge_mode'))
        ? 'challenge_mode, calm_points, total_calm_points'
        : "'intermediate' AS challenge_mode, 0 AS calm_points, 0 AS total_calm_points";
    $shopColumns = (function_exists('shopEquipSelectColumns'))
        ? shopEquipSelectColumns()
        : "NULL AS equipped_background, NULL AS equipped_banner_pattern, NULL AS equipped_frame, NULL AS equipped_stickers";
    $avatarColumns = (function_exists('avatarSelectColumns'))
        ? avatarSelectColumns()
        : "NULL AS equipped_avatar, 0 AS avatar_public_face";
    $profileColumns = implode(', ', [
        hasPublicProfileColumn('profile_bio') ? 'profile_bio' : 'NULL AS profile_bio',
        hasPublicProfileColumn('profile_prompt_key') ? 'profile_prompt_key' : 'NULL AS profile_prompt_key',
        hasPublicProfileColumn('profile_prompt_answer') ? 'profile_prompt_answer' : 'NULL AS profile_prompt_answer',
        hasPublicProfileColumn('profile_visible') ? 'profile_visible' : '0 AS profile_visible',
        hasPublicProfileColumn('profile_banner_x') ? 'profile_banner_x' : '50 AS profile_banner_x',
        hasPublicProfileColumn('profile_banner_y') ? 'profile_banner_y' : '50 AS profile_banner_y',
        hasPublicProfileColumn('profile_banner_zoom') ? 'profile_banner_zoom' : '1.00 AS profile_banner_zoom',
        hasPublicProfileColumn('profile_banner_text_color') ? 'profile_banner_text_color' : 'NULL AS profile_banner_text_color',
        hasPublicProfileColumn('public_profile_slug') ? 'public_profile_slug' : 'NULL AS public_profile_slug',
        hasPublicProfileColumn('last_active_at') ? 'last_active_at' : 'NULL AS last_active_at',
    ]);

    $row = dbFetchOne(
        "SELECT id, email, first_name, last_name, profile_pic,
                $profileColumns,
                timezone,
                weight_lbs, height_inches, age, bmi, daily_water_oz, $bottleColumn,
                journal_in_app, chat_bubble_color, $retentionColumns,
                streak_repairs, $modeColumns, $shopColumns, $avatarColumns, onboarding_completed, $adminColumn, $adminRoleColumn, created_at
         FROM users WHERE id = ?",
        [$uid]
    );
    if ($row && !isset($_SESSION['auth_version'])) {
        logoutUser();
        redirect('/kinto?session=security_update#signin');
    }
    if ($row && (int) $_SESSION['auth_version'] !== (int) ($row['auth_version'] ?? 1)) {
        logoutUser();
        redirect('/kinto?session=expired#signin');
    }
    if ($row && hasPublicProfileColumn('last_active_at')) {
        $lastActive = !empty($row['last_active_at']) ? strtotime($row['last_active_at'] . ' UTC') : 0;
        if ($lastActive === false || $lastActive < time() - 300) {
            dbQuery("UPDATE users SET last_active_at = UTC_TIMESTAMP() WHERE id = ?", [$uid]);
            $row['last_active_at'] = gmdate('Y-m-d H:i:s');
        }
    }
    $GLOBALS['__current_user_cache'][$uid] = $row;
    return $row;
}

/**
 * Invalidate the per-request user cache. Call after any write that
 * mutates the current user's row (profile update, password change, etc).
 */
function clearCurrentUserCache(): void {
    unset($GLOBALS['__current_user_cache']);
}

/**
 * Require user to be logged in
 * Redirects to login if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('/kinto?login=required#signin');
    }
}

/**
 * Require user to have completed onboarding
 */
function requireOnboarding(): void {
    requireLogin();
    $user = getCurrentUser();
    if (!$user['onboarding_completed']) {
        redirect('/challenge/onboarding/step1.php');
    }
}

/**
 * Register a new user
 * @param string $email
 * @param string $password
 * @return array [bool success, int|string userIdOrError]
 */
function registerUser(string $email, string $password): array {
    $email = strtolower(trim($email));
    
    // Check if email already exists
    $existing = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        return [false, 'An account with this email already exists'];
    }
    
    // Validate email
    if (!isValidEmail($email)) {
        return [false, 'Please enter a valid email address'];
    }
    
    // Validate password
    [$valid, $error] = validatePassword($password);
    if (!$valid) {
        return [false, $error];
    }
    
    // Create user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        
        // Insert user (3 streak repairs max per challenge)
        dbQuery(
            "INSERT INTO users (email, password_hash, timezone, streak_repairs, created_at) 
             VALUES (?, ?, ?, 3, NOW())",
            [$email, $passwordHash, DEFAULT_TIMEZONE]
        );
        
        $userId = (int) dbLastId();
        
        // Initialize streak record
        dbQuery(
            "INSERT INTO user_streaks (user_id, current_streak, longest_streak) 
             VALUES (?, 0, 0)",
            [$userId]
        );
        
        $pdo->commit();
        
        return [true, $userId];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        return [false, 'Registration failed. Please try again.'];
    }
}

/**
 * Authenticate user login
 * @param string $email
 * @param string $password
 * @return array [bool success, string message]
 */
function loginUser(string $email, string $password): array {
    $email = strtolower(trim($email));
    
    ensureAdminInfrastructure();
    $user = dbFetchOne(
        "SELECT id, password_hash, onboarding_completed, auth_version FROM users WHERE email = ?",
        [$email]
    );
    
    if (!$user) {
        return [false, 'Invalid email or password'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return [false, 'Invalid email or password'];
    }
    
    // Set session
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['auth_version'] = (int) ($user['auth_version'] ?? 1);
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    clearFlash();
    
    return [true, $user['onboarding_completed'] ? 'dashboard' : 'onboarding'];
}

function isCurrentUserAdmin(): bool {
    return isCurrentUserSuperAdmin();
}

function isCurrentUserSuperAdmin(): bool {
    $user = getCurrentUser();
    return ($user['admin_role'] ?? 'user') === 'super_admin' || !empty($user['is_admin']);
}

function requireAdmin(): void {
    requireSuperAdmin();
}

function requireSuperAdmin(): void {
    requireOnboarding();
    if (!isCurrentUserSuperAdmin()) {
        setFlash('error', 'You do not have access to that page.');
        redirect('/challenge/app/dashboard.php');
    }
}

/**
 * Log out current user
 */
function logoutUser(): void {
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

/**
 * Update user profile
 * @param int $userId
 * @param array $data
 * @return bool
 */
function updateUserProfile(int $userId, array $data): bool {
    $allowedFields = [
        'first_name', 'last_name', 'dob', 'profile_pic', 'timezone',
        'profile_bio', 'profile_prompt_key', 'profile_prompt_answer', 'profile_visible',
        'profile_banner_x', 'profile_banner_y', 'profile_banner_zoom', 'profile_banner_text_color', 'public_profile_slug',
        'weight_lbs', 'height_inches', 'age', 'bmi', 'daily_water_oz',
        'water_bottle_oz',
        'journal_in_app', 'chat_bubble_color', 'onboarding_completed',
        'challenge_mode',
        'daily_reminder_enabled', 'daily_reminder_time', 'streak_risk_enabled',
    ];
    
    $updates = [];
    $params = [];
    
    // Self-heal the water_bottle_oz column before we iterate so the
    // write below will actually persist on databases predating the
    // migration.
    ensureWaterBottleColumn();
    ensurePublicProfileColumns();
    if (file_exists(__DIR__ . '/xp_service.php')) {
        require_once __DIR__ . '/xp_service.php';
        ensureXpTablesAndColumns();
    }

    foreach ($data as $field => $value) {
        if (!in_array($field, $allowedFields)) {
            continue;
        }
        // Defensive: skip writes to columns that don't exist yet
        // (e.g. water_bottle_oz before migration is applied in prod).
        if ($field === 'water_bottle_oz' && !hasWaterBottleColumn()) {
            continue;
        }
        $updates[] = "$field = ?";
        $params[] = $value;
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        dbQuery($sql, $params);
        clearCurrentUserCache();
        return true;
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user password
 * @param int $userId
 * @param string $currentPassword
 * @param string $newPassword
 * @return array [bool success, string message]
 */
function updatePassword(int $userId, string $currentPassword, string $newPassword): array {
    $user = dbFetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
    
    if (!password_verify($currentPassword, $user['password_hash'])) {
        return [false, 'Current password is incorrect'];
    }
    
    [$valid, $error] = validatePassword($newPassword);
    if (!$valid) {
        return [false, $error];
    }
    
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    dbQuery("UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?", [$newHash, $userId]);
    $version = dbFetchOne("SELECT auth_version FROM users WHERE id = ?", [$userId]);
    $_SESSION['auth_version'] = (int) ($version['auth_version'] ?? 1);
    clearCurrentUserCache();
    
    return [true, 'Password updated successfully'];
}

/**
 * Permanently delete a user account and all related data covered by
 * database cascade rules.
 * @param int $userId
 * @return array [bool success, string message]
 */
function deleteUserAccount(int $userId): array {
    $user = dbFetchOne("SELECT email, profile_pic FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        return [false, 'Account not found'];
    }

    $profilePic = $user['profile_pic'] ?? null;

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        if (adminInfrastructureReady()) {
            dbQuery("DELETE FROM password_reset_requests WHERE email_hash = ?", [hash('sha256', strtolower((string) $user['email']))]);
            dbQuery("UPDATE admin_audit_log SET details_json = NULL WHERE target_user_id = ?", [$userId]);
        }
        dbQuery("DELETE FROM users WHERE id = ?", [$userId]);

        $pdo->commit();
        clearCurrentUserCache();

        if ($profilePic) {
            $profilePicPath = profilePicFilesystemPath($profilePic);
            $path = $profilePicPath ? realpath($profilePicPath) : false;
            $legacyUploadsPath = realpath(UPLOAD_PATH);
            $profileUploadsPath = realpath(PROFILE_PIC_UPLOAD_PATH);
            $isOwnedUpload = $path && (
                ($legacyUploadsPath && strpos($path, $legacyUploadsPath) === 0) ||
                ($profileUploadsPath && strpos($path, $profileUploadsPath) === 0)
            );

            if ($isOwnedUpload && is_file($path)) {
                @unlink($path);
            }
        }

        return [true, 'Account deleted successfully'];
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Account deletion error: " . $e->getMessage());
        return [false, 'Account deletion failed. Please try again.'];
    }
}

/**
 * Award streak repair to user
 * @param int $userId
 * @param int $amount
 */
function awardStreakRepair(int $userId, int $amount = 1): void {
    dbQuery(
        "UPDATE users SET streak_repairs = streak_repairs + ? WHERE id = ?",
        [$amount, $userId]
    );
}

/**
 * Use streak repair token
 * @param int $userId
 * @return bool true if token was used, false if none available
 */
function useStreakRepair(int $userId): bool {
    $result = dbQuery(
        "UPDATE users SET streak_repairs = streak_repairs - 1 
         WHERE id = ? AND streak_repairs > 0",
        [$userId]
    );
    
    return $result->rowCount() > 0;
}

/**
 * Get user's streak repair count
 * @param int $userId
 * @return int
 */
function getStreakRepairCount(int $userId): int {
    $user = dbFetchOne("SELECT streak_repairs FROM users WHERE id = ?", [$userId]);
    return (int) ($user['streak_repairs'] ?? 0);
}
