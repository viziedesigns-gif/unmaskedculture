<?php
/** Super-admin infrastructure, authorization support, and audit helpers. */

require_once __DIR__ . '/functions.php';

function ensureAdminInfrastructure(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $columns = dbFetchAll(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME IN ('is_admin', 'admin_role', 'auth_version')"
        );
        $present = array_column($columns, 'COLUMN_NAME');
        if (!in_array('is_admin', $present, true)) {
            dbQuery("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER onboarding_completed");
        }
        if (!in_array('admin_role', $present, true)) {
            dbQuery("ALTER TABLE users ADD COLUMN admin_role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER is_admin");
        }
        if (!in_array('auth_version', $present, true)) {
            dbQuery("ALTER TABLE users ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER admin_role");
        }

        // Preserve current administrator access during the dedicated-account transition.
        dbQuery("UPDATE users SET admin_role = 'super_admin' WHERE is_admin = 1 AND admin_role = 'user'");

        dbQuery(
            "CREATE TABLE IF NOT EXISTS admin_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                admin_user_id INT UNSIGNED NOT NULL,
                action VARCHAR(80) NOT NULL,
                target_user_id INT UNSIGNED DEFAULT NULL,
                details_json TEXT DEFAULT NULL,
                ip_hash CHAR(64) DEFAULT NULL,
                created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_admin_created (admin_user_id, created_at_utc),
                INDEX idx_target_created (target_user_id, created_at_utc),
                CONSTRAINT fk_audit_admin FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_audit_target FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dbQuery(
            "CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                created_by_admin_id INT UNSIGNED DEFAULT NULL,
                requested_ip_hash CHAR(64) DEFAULT NULL,
                expires_at_utc DATETIME NOT NULL,
                used_at_utc DATETIME DEFAULT NULL,
                created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_reset_token_hash (token_hash),
                INDEX idx_reset_user_created (user_id, created_at_utc),
                INDEX idx_reset_expiry (expires_at_utc),
                CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_reset_admin FOREIGN KEY (created_by_admin_id) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dbQuery(
            "CREATE TABLE IF NOT EXISTS password_reset_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email_hash CHAR(64) NOT NULL,
                ip_hash CHAR(64) NOT NULL,
                created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_reset_request_email (email_hash, created_at_utc),
                INDEX idx_reset_request_ip (ip_hash, created_at_utc)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dbQuery(
            "CREATE TABLE IF NOT EXISTS user_notification_status (
                user_id INT UNSIGNED NOT NULL,
                supported TINYINT(1) NOT NULL DEFAULT 0,
                permission_state VARCHAR(20) NOT NULL DEFAULT 'unknown',
                last_reported_at_utc DATETIME NOT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (user_id),
                INDEX idx_permission (permission_state),
                CONSTRAINT fk_notification_status_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dbQuery(
            "CREATE TABLE IF NOT EXISTS admin_email_campaign_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                campaign_key VARCHAR(80) NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                error_message VARCHAR(255) DEFAULT NULL,
                created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_at_utc DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_campaign_user (campaign_key, user_id),
                INDEX idx_campaign_status (campaign_key, status),
                CONSTRAINT fk_email_campaign_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $GLOBALS['__admin_infrastructure_ready'] = true;
    } catch (Exception $e) {
        $GLOBALS['__admin_infrastructure_ready'] = false;
        error_log('ensureAdminInfrastructure failed: ' . $e->getMessage());
    }
}

function adminInfrastructureReady(): bool {
    ensureAdminInfrastructure();
    return !empty($GLOBALS['__admin_infrastructure_ready']);
}

function requestIpHash(): string {
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return hash('sha256', $ip . '|' . SESSION_NAME);
}

function auditAdminAction(int $adminUserId, string $action, ?int $targetUserId = null, array $details = []): void {
    if (!adminInfrastructureReady()) return;
    dbQuery(
        "INSERT INTO admin_audit_log (admin_user_id, action, target_user_id, details_json, ip_hash, created_at_utc)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())",
        [$adminUserId, substr($action, 0, 80), $targetUserId, $details ? json_encode($details) : null, requestIpHash()]
    );
}

function getSuperAdminCount(): int {
    if (!adminInfrastructureReady()) return 0;
    $row = dbFetchOne("SELECT COUNT(*) AS c FROM users WHERE admin_role = 'super_admin'");
    return (int) ($row['c'] ?? 0);
}

function setUserAdminRole(int $actingAdminId, int $targetUserId, string $role): array {
    if (!in_array($role, ['user', 'super_admin'], true)) return [false, 'Invalid role.'];
    $target = dbFetchOne("SELECT id, email, admin_role FROM users WHERE id = ?", [$targetUserId]);
    if (!$target) return [false, 'User not found.'];
    if ($target['admin_role'] === $role) return [true, 'Role is already up to date.'];
    if ($target['admin_role'] === 'super_admin' && $role === 'user' && getSuperAdminCount() <= 1) {
        return [false, 'Promote another super admin before removing the final super admin.'];
    }

    dbQuery("UPDATE users SET admin_role = ?, is_admin = ? WHERE id = ?", [$role, $role === 'super_admin' ? 1 : 0, $targetUserId]);
    auditAdminAction($actingAdminId, $role === 'super_admin' ? 'user.promoted' : 'user.demoted', $targetUserId, ['email' => $target['email']]);
    return [true, $role === 'super_admin' ? 'User promoted to super admin.' : 'Super-admin access removed.'];
}

function adminCsrfToken(): string {
    if (empty($_SESSION['super_admin_csrf'])) $_SESSION['super_admin_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['super_admin_csrf'];
}

function validAdminCsrf(?string $token): bool {
    return is_string($token) && $token !== '' && hash_equals(adminCsrfToken(), $token);
}

function confirmAdminPassword(int $adminUserId, string $password): bool {
    $row = dbFetchOne("SELECT password_hash FROM users WHERE id = ?", [$adminUserId]);
    if (!$row || !password_verify($password, (string) $row['password_hash'])) return false;
    $_SESSION['super_admin_confirmed_at'] = time();
    return true;
}

function hasRecentAdminConfirmation(): bool {
    return !empty($_SESSION['super_admin_confirmed_at'])
        && (int) $_SESSION['super_admin_confirmed_at'] >= time() - 900;
}
