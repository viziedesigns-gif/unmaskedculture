<?php
/**
 * Web Push helpers for the Kinto app.
 */

require_once __DIR__ . '/functions.php';

$pushConfig = __DIR__ . '/../config/push.php';
if (is_file($pushConfig)) {
    require_once $pushConfig;
}

if (!defined('PUSH_VAPID_SUBJECT')) define('PUSH_VAPID_SUBJECT', '');
if (!defined('PUSH_VAPID_PUBLIC_KEY')) define('PUSH_VAPID_PUBLIC_KEY', '');
if (!defined('PUSH_VAPID_PRIVATE_KEY')) define('PUSH_VAPID_PRIVATE_KEY', '');

function ensurePushTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        dbQuery(
            "CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                endpoint TEXT NOT NULL,
                endpoint_hash CHAR(64) NOT NULL,
                p256dh_key VARCHAR(255) NOT NULL,
                auth_key VARCHAR(255) NOT NULL,
                content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
                user_agent VARCHAR(255) DEFAULT NULL,
                daily_reminder_enabled TINYINT(1) NOT NULL DEFAULT 1,
                streak_risk_enabled TINYINT(1) NOT NULL DEFAULT 1,
                feed_enabled TINYINT(1) NOT NULL DEFAULT 1,
                last_used_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_endpoint_hash (endpoint_hash),
                INDEX idx_user_id (user_id),
                CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dbQuery(
            "CREATE TABLE IF NOT EXISTS push_notification_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                notification_type VARCHAR(50) NOT NULL,
                user_date DATE NOT NULL,
                sent_at_utc DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_type_day (user_id, notification_type, user_date),
                INDEX idx_sent_at (sent_at_utc),
                CONSTRAINT fk_push_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dbQuery(
            "CREATE TABLE IF NOT EXISTS push_broadcasts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                admin_user_id INT UNSIGNED NOT NULL,
                title VARCHAR(120) NOT NULL,
                body VARCHAR(500) NOT NULL,
                target_url VARCHAR(255) NOT NULL DEFAULT '/challenge/app/dashboard.php',
                audience VARCHAR(50) NOT NULL DEFAULT 'all_onboarded',
                target_devices INT NOT NULL DEFAULT 0,
                sent_count INT NOT NULL DEFAULT 0,
                failed_count INT NOT NULL DEFAULT 0,
                created_at_utc DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_created_at (created_at_utc),
                CONSTRAINT fk_push_broadcast_admin FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $GLOBALS['__push_tables_ready'] = true;
    } catch (Exception $e) {
        $GLOBALS['__push_tables_ready'] = false;
        error_log('ensurePushTables failed: ' . $e->getMessage());
    }
}

function shouldSendPushNotification(int $userId, string $type, string $userDate): bool {
    if (!pushTablesReady()) return false;

    $existing = dbFetchOne(
        "SELECT id FROM push_notification_log
         WHERE user_id = ? AND notification_type = ? AND user_date = ?",
        [$userId, $type, $userDate]
    );

    return !$existing;
}

function recordPushNotificationSent(int $userId, string $type, string $userDate): void {
    if (!pushTablesReady()) return;

    dbQuery(
        "INSERT IGNORE INTO push_notification_log
            (user_id, notification_type, user_date, sent_at_utc)
         VALUES (?, ?, ?, UTC_TIMESTAMP())",
        [$userId, $type, $userDate]
    );
}

function pushTablesReady(): bool {
    ensurePushTables();
    return !empty($GLOBALS['__push_tables_ready']);
}

function isPushConfigured(): bool {
    return PUSH_VAPID_SUBJECT !== ''
        && PUSH_VAPID_PUBLIC_KEY !== ''
        && PUSH_VAPID_PRIVATE_KEY !== ''
        && is_file(__DIR__ . '/../vendor/autoload.php');
}

function getPushConfigStatus(): array {
    $tablesReady = pushTablesReady();
    return [
        'configured' => isPushConfigured() && $tablesReady,
        'public_key' => PUSH_VAPID_PUBLIC_KEY,
        'has_vapid_keys' => PUSH_VAPID_PUBLIC_KEY !== '' && PUSH_VAPID_PRIVATE_KEY !== '',
        'has_web_push_library' => is_file(__DIR__ . '/../vendor/autoload.php'),
        'has_database_table' => $tablesReady,
    ];
}

function savePushSubscription(int $userId, array $subscription, ?string $userAgent = null): void {
    if (!pushTablesReady()) {
        throw new RuntimeException('Push subscription table is not available.');
    }

    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $keys = $subscription['keys'] ?? [];
    $p256dh = trim((string) ($keys['p256dh'] ?? ''));
    $auth = trim((string) ($keys['auth'] ?? ''));
    $encoding = trim((string) ($subscription['contentEncoding'] ?? 'aes128gcm'));

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        throw new InvalidArgumentException('Invalid push subscription.');
    }

    dbQuery(
        "INSERT INTO push_subscriptions
            (user_id, endpoint, endpoint_hash, p256dh_key, auth_key, content_encoding, user_agent, last_used_at)
         VALUES (?, ?, SHA2(?, 256), ?, ?, ?, ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            endpoint = VALUES(endpoint),
            p256dh_key = VALUES(p256dh_key),
            auth_key = VALUES(auth_key),
            content_encoding = VALUES(content_encoding),
            user_agent = VALUES(user_agent),
            last_used_at = UTC_TIMESTAMP()",
        [$userId, $endpoint, $endpoint, $p256dh, $auth, $encoding ?: 'aes128gcm', $userAgent]
    );
}

function deletePushSubscription(int $userId, string $endpoint): void {
    if (!pushTablesReady()) return;
    if (trim($endpoint) === '') return;

    dbQuery(
        "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = SHA2(?, 256)",
        [$userId, $endpoint]
    );
}

function getUserPushSubscriptionCount(int $userId): int {
    if (!pushTablesReady()) return 0;
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c FROM push_subscriptions WHERE user_id = ?",
        [$userId]
    );
    return (int) ($row['c'] ?? 0);
}

function sendPushToUser(int $userId, string $title, string $body, string $url = '/challenge/app/dashboard.php'): array {
    if (!pushTablesReady()) {
        return ['sent' => 0, 'failed' => 0, 'configured' => false, 'database_ready' => false];
    }

    if (!isPushConfigured()) {
        return ['sent' => 0, 'failed' => 0, 'configured' => false];
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $subscriptions = dbFetchAll(
        "SELECT * FROM push_subscriptions WHERE user_id = ?",
        [$userId]
    );

    if (empty($subscriptions)) {
        return ['sent' => 0, 'failed' => 0, 'configured' => true];
    }

    return sendPushToSubscriptionRows($subscriptions, $title, $body, $url);
}

function sendPushToSubscriptionRows(array $subscriptions, string $title, string $body, string $url = '/challenge/app/dashboard.php'): array {
    if (!pushTablesReady()) {
        return ['sent' => 0, 'failed' => 0, 'configured' => false, 'database_ready' => false];
    }

    if (!isPushConfigured()) {
        return ['sent' => 0, 'failed' => 0, 'configured' => false];
    }

    if (empty($subscriptions)) {
        return ['sent' => 0, 'failed' => 0, 'configured' => true];
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $auth = [
        'VAPID' => [
            'subject' => PUSH_VAPID_SUBJECT,
            'publicKey' => PUSH_VAPID_PUBLIC_KEY,
            'privateKey' => PUSH_VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new \Minishlink\WebPush\WebPush($auth);
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'icon' => faviconUrl('web-app-manifest-192x192.png'),
        'badge' => faviconUrl('web-app-manifest-192x192.png'),
    ]);

    foreach ($subscriptions as $sub) {
        $webPush->queueNotification(
            \Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh_key'],
                'authToken' => $sub['auth_key'],
                'contentEncoding' => $sub['content_encoding'] ?: 'aes128gcm',
            ]),
            $payload
        );
    }

    $sent = 0;
    $failed = 0;
    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            $sent++;
        } else {
            $failed++;
            if ($report->isSubscriptionExpired()) {
                dbQuery(
                    "DELETE FROM push_subscriptions WHERE endpoint_hash = SHA2(?, 256)",
                    [(string) $report->getRequest()->getUri()]
                );
            }
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'configured' => true];
}

function sendPushToUsers(array $userIds, string $title, string $body, string $url = '/challenge/app/dashboard.php'): array {
    $totals = ['sent' => 0, 'failed' => 0, 'configured' => isPushConfigured(), 'database_ready' => pushTablesReady()];
    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        if ($userId < 1) continue;
        $result = sendPushToUser($userId, $title, $body, $url);
        $totals['sent'] += (int) ($result['sent'] ?? 0);
        $totals['failed'] += (int) ($result['failed'] ?? 0);
        $totals['configured'] = $totals['configured'] && !empty($result['configured']);
    }
    return $totals;
}

function getBroadcastPushDeviceCount(string $audience = 'all_onboarded'): int {
    if (!pushTablesReady()) return 0;

    $where = "1 = 1";
    if ($audience === 'all_onboarded') {
        $where = "u.onboarding_completed = 1";
    }

    $row = dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM push_subscriptions ps
         JOIN users u ON u.id = ps.user_id
         WHERE $where"
    );

    return (int) ($row['c'] ?? 0);
}

function sendBroadcastPush(
    int $adminUserId,
    string $title,
    string $body,
    string $url = '/challenge/app/dashboard.php',
    string $audience = 'all_onboarded'
): array {
    if (!pushTablesReady()) {
        return ['sent' => 0, 'failed' => 0, 'target_devices' => 0, 'configured' => false, 'database_ready' => false];
    }

    if (!isPushConfigured()) {
        return ['sent' => 0, 'failed' => 0, 'target_devices' => 0, 'configured' => false];
    }

    $where = "1 = 1";
    if ($audience === 'all_onboarded') {
        $where = "u.onboarding_completed = 1";
    }

    $subscriptions = dbFetchAll(
        "SELECT ps.*
         FROM push_subscriptions ps
         JOIN users u ON u.id = ps.user_id
         WHERE $where"
    );

    $result = sendPushToSubscriptionRows($subscriptions, $title, $body, $url);
    $targetDevices = count($subscriptions);

    dbQuery(
        "INSERT INTO push_broadcasts
            (admin_user_id, title, body, target_url, audience, target_devices, sent_count, failed_count, created_at_utc)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
        [
            $adminUserId,
            $title,
            $body,
            $url,
            $audience,
            $targetDevices,
            (int) ($result['sent'] ?? 0),
            (int) ($result['failed'] ?? 0),
        ]
    );

    $result['target_devices'] = $targetDevices;
    $result['broadcast_id'] = (int) dbLastId();
    return $result;
}
