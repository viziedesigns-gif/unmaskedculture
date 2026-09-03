<?php
/** Feed reactions and mention helpers. */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/streak_service.php';

function ensureFeedReactionTable(): void {
    if (!empty($GLOBALS['__feed_reactions_ready'])) return;
    dbQuery(
        "CREATE TABLE IF NOT EXISTS circle_message_reactions (
            message_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            reaction VARCHAR(20) NOT NULL DEFAULT 'heart',
            created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id, user_id, reaction),
            INDEX idx_reaction_user (user_id),
            CONSTRAINT fk_reaction_message FOREIGN KEY (message_id) REFERENCES circle_messages (id) ON DELETE CASCADE,
            CONSTRAINT fk_reaction_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $GLOBALS['__feed_reactions_ready'] = true;
}

/** @return int[] Valid member IDs explicitly mentioned as @[Name](123). */
function extractCircleMentionIds(string $message, int $circleId, int $senderId): array {
    if (!preg_match_all('/@\[[^\]]+\]\((\d+)\)/', $message, $matches)) return [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $matches[1]), static fn($id) => $id > 0 && $id !== $senderId)));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = dbFetchAll(
        "SELECT user_id FROM inner_circle_members WHERE circle_id = ? AND user_id IN ($placeholders)",
        array_merge([$circleId], $ids)
    );
    return array_map(static fn($row) => (int) $row['user_id'], $rows);
}

/** @return int[] Circle-member IDs from an explicit client mention list. */
function validateCircleMentionIds(array $requestedIds, int $circleId, int $senderId): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $requestedIds), static fn($id) => $id > 0 && $id !== $senderId)));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = dbFetchAll(
        "SELECT user_id FROM inner_circle_members WHERE circle_id = ? AND user_id IN ($placeholders)",
        array_merge([$circleId], $ids)
    );
    return array_map(static fn($row) => (int) $row['user_id'], $rows);
}

/**
 * Return circle members ordered by today's checklist progress with shared
 * competition ranks (7,7,6 => 1,1,3). Only positive top-three scores rank.
 */
function getRankedCircleMembers(int $circleId, string $userDate): array {
    require_once __DIR__ . '/shop_service.php';
    require_once __DIR__ . '/avatar_service.php';
    $frameSelect = shopFrameSelectSql('u');
    $avatarSelect = avatarSelectSql('u');
    $members = dbFetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.profile_pic, u.chat_bubble_color $frameSelect $avatarSelect, icm.role
         FROM inner_circle_members icm
         JOIN users u ON icm.user_id = u.id
         WHERE icm.circle_id = ?
         ORDER BY u.first_name, u.last_name",
        [$circleId]
    );
    $memberIds = array_map(static fn($member) => (int) $member['id'], $members);
    $progress = getUsersChecklistProgressForDate($memberIds, $userDate);

    foreach ($members as &$member) {
        $member['id'] = (int) $member['id'];
        $member['name'] = trim((string) $member['first_name'] . ' ' . (string) $member['last_name']);
        $memberProgress = $progress[(int) $member['id']] ?? ['done' => 0, 'required' => 7, 'tone' => 'empty'];
        $member['done'] = (int) $memberProgress['done'];
        $member['required'] = (int) $memberProgress['required'];
        $member['tone'] = (string) $memberProgress['tone'];
        $member['profile_pic_url'] = profilePicUrl($member['profile_pic'] ?? null);
        $member['frame_css'] = resolveFrameCssFromId($member['equipped_frame'] ?? null);
    }
    unset($member);

    usort($members, static function (array $a, array $b): int {
        return ((int) $b['done'] <=> (int) $a['done'])
            ?: strcasecmp(trim($a['first_name'] . ' ' . $a['last_name']), trim($b['first_name'] . ' ' . $b['last_name']));
    });

    $previousScore = null;
    $activeRank = 0;
    foreach ($members as $index => &$member) {
        $score = (int) $member['done'];
        if ($score <= 0) {
            $member['rank'] = 0;
            continue;
        }
        if ($previousScore === null || $score < $previousScore) {
            $activeRank = $index + 1;
        }
        $member['rank'] = $activeRank <= 3 ? $activeRank : 0;
        $previousScore = $score;
    }
    unset($member);

    return $members;
}
