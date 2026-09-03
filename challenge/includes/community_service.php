<?php
/**
 * Public profile and circle-request helpers.
 */

require_once __DIR__ . '/functions.php';

function ensureCommunityTables(): bool {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        dbQuery(
            "CREATE TABLE IF NOT EXISTS circle_join_requests (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                circle_id INT UNSIGNED NOT NULL,
                requester_id INT UNSIGNED NOT NULL,
                status ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending',
                pending_key CHAR(64) DEFAULT NULL,
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME DEFAULT NULL,
                resolved_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_pending_join_request (pending_key),
                INDEX idx_circle_status (circle_id, status),
                INDEX idx_requester_status (requester_id, status),
                CONSTRAINT fk_join_request_circle FOREIGN KEY (circle_id) REFERENCES inner_circles (id) ON DELETE CASCADE,
                CONSTRAINT fk_join_request_requester FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_join_request_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $table = dbFetchOne(
            "SELECT COUNT(DISTINCT COLUMN_NAME) AS c
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'circle_join_requests'
               AND COLUMN_NAME IN ('id', 'circle_id', 'requester_id', 'status', 'pending_key', 'requested_at', 'resolved_at', 'resolved_by')"
        );
        $ready = (int) ($table['c'] ?? 0) === 8;
    } catch (Exception $e) {
        error_log('ensureCommunityTables failed: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

function communityCsrfToken(): string {
    if (empty($_SESSION['community_csrf_token'])) {
        $_SESSION['community_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['community_csrf_token'];
}

function validCommunityCsrf(?string $token): bool {
    $expected = $_SESSION['community_csrf_token'] ?? '';
    return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

function pendingJoinKey(int $circleId, int $requesterId): string {
    return hash('sha256', $circleId . ':' . $requesterId);
}

function profileActivityLabel(?string $utcDate, string $timezone = DEFAULT_TIMEZONE): string {
    if (!$utcDate) {
        return 'Activity not available';
    }
    try {
        $zone = new DateTimeZone($timezone);
        $active = new DateTime($utcDate, new DateTimeZone('UTC'));
        $active->setTimezone($zone);
        $today = new DateTime('today', $zone);
        $activeDay = new DateTime($active->format('Y-m-d'), $zone);
        $days = (int) $activeDay->diff($today)->format('%r%a');
        if ($days === 0) {
            return 'Active today';
        }
        if ($days === 1) {
            return 'Active yesterday';
        }
        return 'Active ' . $active->format('M j, Y');
    } catch (Exception $e) {
        return 'Activity not available';
    }
}
