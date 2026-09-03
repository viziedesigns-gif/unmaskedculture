<?php
/**
 * Private Jar storage, permissions, and presentation helpers.
 */

require_once __DIR__ . '/functions.php';

function ensureJarTable(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;

    try {
        dbQuery(
            "CREATE TABLE IF NOT EXISTS jar_entries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                owner_user_id INT UNSIGNED NOT NULL,
                author_user_id INT UNSIGNED DEFAULT NULL,
                source_circle_id INT UNSIGNED DEFAULT NULL,
                entry_type VARCHAR(24) NOT NULL DEFAULT 'general',
                message VARCHAR(600) NOT NULL,
                pull_count INT UNSIGNED NOT NULL DEFAULT 0,
                last_pulled_at_utc DATETIME DEFAULT NULL,
                owner_seen_at_utc DATETIME DEFAULT NULL,
                created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_jar_owner_created (owner_user_id, created_at_utc),
                INDEX idx_jar_owner_pull (owner_user_id, pull_count),
                INDEX idx_jar_author (author_user_id),
                CONSTRAINT fk_jar_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_jar_author FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT fk_jar_circle FOREIGN KEY (source_circle_id) REFERENCES inner_circles (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dbQuery(
            "CREATE TABLE IF NOT EXISTS user_feature_announcements (
                user_id INT UNSIGNED NOT NULL,
                announcement_key VARCHAR(64) NOT NULL,
                seen_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, announcement_key),
                CONSTRAINT fk_feature_announcement_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ready = true;
    } catch (Exception $e) {
        error_log('ensureJarTable failed: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

/**
 * Claim the one-time Jar introduction for an account that predates the feature.
 * Inserting when rendered prevents the explainer from returning on later pages.
 */
function consumeJarFeatureIntro(int $userId, ?string $accountCreatedAt): bool {
    if ($userId < 1 || !ensureJarTable()) return false;
    $releaseUtc = strtotime('2026-08-19 00:00:00 UTC');
    $created = $accountCreatedAt ? strtotime($accountCreatedAt . ' UTC') : false;
    if ($created !== false && $releaseUtc !== false && $created >= $releaseUtc) return false;

    $result = dbQuery(
        "INSERT IGNORE INTO user_feature_announcements (user_id, announcement_key, seen_at_utc)
         VALUES (?, 'jar_v1', UTC_TIMESTAMP())",
        [$userId]
    );
    return $result->rowCount() > 0;
}

function jarCsrfToken(): string {
    if (empty($_SESSION['jar_csrf_token'])) {
        $_SESSION['jar_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['jar_csrf_token'];
}

function validJarCsrf(?string $token): bool {
    $expected = (string) ($_SESSION['jar_csrf_token'] ?? '');
    return is_string($token) && $expected !== '' && hash_equals($expected, $token);
}

/** @return array<string,string> */
function jarEntryTypes(): array {
    return [
        'general' => 'General',
        'encouragement' => 'Encouragement',
        'memory' => 'Memory',
        'happy_moment' => 'Happy Moment',
        'gratitude' => 'Gratitude',
    ];
}

function normalizeJarEntryType(?string $type): string {
    $type = strtolower(trim((string) $type));
    return array_key_exists($type, jarEntryTypes()) ? $type : 'general';
}

/** Return one circle currently shared by two distinct users. */
function findSharedJarCircle(int $viewerId, int $ownerId): ?int {
    if ($viewerId < 1 || $ownerId < 1 || $viewerId === $ownerId) return null;
    $row = dbFetchOne(
        "SELECT mine.circle_id
         FROM inner_circle_members mine
         JOIN inner_circle_members theirs ON theirs.circle_id = mine.circle_id
         WHERE mine.user_id = ? AND theirs.user_id = ?
         ORDER BY mine.joined_at ASC
         LIMIT 1",
        [$viewerId, $ownerId]
    );
    return $row ? (int) $row['circle_id'] : null;
}

function canAddToJar(int $authorId, int $ownerId): bool {
    return $authorId > 0 && $ownerId > 0
        && ($authorId === $ownerId || findSharedJarCircle($authorId, $ownerId) !== null);
}

/** @return array<string,mixed> */
function addJarEntry(int $ownerId, int $authorId, string $message, ?string $type = null): array {
    if (!ensureJarTable()) throw new RuntimeException('The Jar is temporarily unavailable.');
    $message = trim($message);
    $length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
    if ($message === '') throw new InvalidArgumentException('Write something to place in the Jar.');
    if ($length > 600) throw new InvalidArgumentException('Jar entries must be 600 characters or fewer.');

    $owner = dbFetchOne("SELECT id FROM users WHERE id = ?", [$ownerId]);
    if (!$owner || !canAddToJar($authorId, $ownerId)) {
        throw new RuntimeException('You must share a circle with this person to add to their Jar.', 403);
    }

    $circleId = $authorId === $ownerId ? null : findSharedJarCircle($authorId, $ownerId);
    $entryType = normalizeJarEntryType($type);
    dbQuery(
        "INSERT INTO jar_entries
            (owner_user_id, author_user_id, source_circle_id, entry_type, message, owner_seen_at_utc, created_at_utc)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
        [$ownerId, $authorId, $circleId, $entryType, $message, $authorId === $ownerId ? utcNow() : null]
    );
    return getJarEntry((int) dbLastId(), $ownerId) ?: [];
}

/** @return array<string,mixed>|null */
function getJarEntry(int $entryId, int $ownerId): ?array {
    if (!ensureJarTable()) return null;
    return dbFetchOne(
        "SELECT je.*, au.first_name AS author_first_name, au.last_name AS author_last_name
         FROM jar_entries je
         LEFT JOIN users au ON au.id = je.author_user_id
         WHERE je.id = ? AND je.owner_user_id = ?",
        [$entryId, $ownerId]
    );
}

function getJarEntryCount(int $ownerId): int {
    if (!ensureJarTable()) return 0;
    $row = dbFetchOne("SELECT COUNT(*) AS c FROM jar_entries WHERE owner_user_id = ?", [$ownerId]);
    return (int) ($row['c'] ?? 0);
}

/** @return array<int,string> */
function getJarVisualTypes(int $ownerId, int $limit = 60): array {
    if (!ensureJarTable()) return [];
    $limit = max(1, min(60, $limit));
    $rows = dbFetchAll(
        "SELECT entry_type FROM jar_entries WHERE owner_user_id = ? ORDER BY created_at_utc DESC, id DESC LIMIT $limit",
        [$ownerId]
    );
    return array_map(static fn(array $row): string => normalizeJarEntryType($row['entry_type'] ?? null), $rows);
}

function getUnreadJarCount(int $ownerId): int {
    if (!ensureJarTable()) return 0;
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c FROM jar_entries WHERE owner_user_id = ? AND owner_seen_at_utc IS NULL",
        [$ownerId]
    );
    return (int) ($row['c'] ?? 0);
}

function markJarSeen(int $ownerId): void {
    if (!ensureJarTable()) return;
    dbQuery(
        "UPDATE jar_entries SET owner_seen_at_utc = UTC_TIMESTAMP()
         WHERE owner_user_id = ? AND owner_seen_at_utc IS NULL",
        [$ownerId]
    );
}

/** @return array<int,array<string,mixed>> */
function getJarHistory(int $ownerId, int $limit = 20, int $offset = 0): array {
    if (!ensureJarTable()) return [];
    $limit = max(1, min(50, $limit));
    $offset = max(0, $offset);
    return dbFetchAll(
        "SELECT je.*, au.first_name AS author_first_name, au.last_name AS author_last_name
         FROM jar_entries je
         LEFT JOIN users au ON au.id = je.author_user_id
         WHERE je.owner_user_id = ?
         ORDER BY je.created_at_utc DESC, je.id DESC
         LIMIT $limit OFFSET $offset",
        [$ownerId]
    );
}

/** Pull randomly from the least-pulled tier, guaranteeing a complete cycle before repeats. */
function pullRandomJarEntry(int $ownerId): ?array {
    if (!ensureJarTable()) return null;
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    try {
        // Serialize pulls for this owner so two rapid requests cannot draw the same tier concurrently.
        dbFetchAll("SELECT id FROM jar_entries WHERE owner_user_id = ? FOR UPDATE", [$ownerId]);
        $row = dbFetchOne(
            "SELECT je.*, au.first_name AS author_first_name, au.last_name AS author_last_name
             FROM jar_entries je
             LEFT JOIN users au ON au.id = je.author_user_id
             WHERE je.owner_user_id = ?
               AND je.pull_count = (SELECT MIN(j2.pull_count) FROM jar_entries j2 WHERE j2.owner_user_id = ?)
             ORDER BY RAND()
             LIMIT 1",
            [$ownerId, $ownerId]
        );
        if (!$row) {
            $pdo->commit();
            return null;
        }
        dbQuery(
            "UPDATE jar_entries
             SET pull_count = pull_count + 1, last_pulled_at_utc = UTC_TIMESTAMP(), owner_seen_at_utc = COALESCE(owner_seen_at_utc, UTC_TIMESTAMP())
             WHERE id = ? AND owner_user_id = ?",
            [(int) $row['id'], $ownerId]
        );
        $row['pull_count'] = (int) $row['pull_count'] + 1;
        $row['last_pulled_at_utc'] = utcNow();
        $pdo->commit();
        return $row;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function deleteJarEntry(int $entryId, int $ownerId): bool {
    if (!ensureJarTable()) return false;
    $result = dbQuery("DELETE FROM jar_entries WHERE id = ? AND owner_user_id = ?", [$entryId, $ownerId]);
    return $result->rowCount() > 0;
}

function jarAuthorName(array $entry, int $ownerId): string {
    if ((int) ($entry['author_user_id'] ?? 0) === $ownerId) return 'You';
    $name = trim((string) ($entry['author_first_name'] ?? '') . ' ' . (string) ($entry['author_last_name'] ?? ''));
    if ($name !== '') return $name;
    return !empty($entry['author_user_id']) ? 'A circle member' : 'A former circle member';
}

function formatJarTimestamp(?string $utc, string $timezone): string {
    if (!$utc) return '';
    try {
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone($timezone))->format('M j, Y \a\t g:i A');
    } catch (Exception $e) {
        return (string) $utc;
    }
}

/** @return array<string,mixed> */
function jarEntryPayload(array $entry, int $ownerId, string $timezone): array {
    $type = normalizeJarEntryType($entry['entry_type'] ?? null);
    return [
        'id' => (int) ($entry['id'] ?? 0),
        'message' => (string) ($entry['message'] ?? ''),
        'entry_type' => $type,
        'entry_type_label' => jarEntryTypes()[$type],
        'author_name' => jarAuthorName($entry, $ownerId),
        'created_at_utc' => (string) ($entry['created_at_utc'] ?? ''),
        'created_at_label' => formatJarTimestamp($entry['created_at_utc'] ?? null, $timezone),
        'pull_count' => (int) ($entry['pull_count'] ?? 0),
    ];
}
