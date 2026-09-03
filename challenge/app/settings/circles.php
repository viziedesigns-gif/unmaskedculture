<?php
/**
 * Circles / Feed settings — manage circles, members, invites.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/streak_service.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
require_once __DIR__ . '/../../includes/push_service.php';
require_once __DIR__ . '/../../includes/community_service.php';
require_once __DIR__ . '/../../includes/feed_service.php';
require_once __DIR__ . '/../../includes/avatar_service.php';
require_once __DIR__ . '/../../includes/avatar_render.php';

requireOnboarding();
$communityReady = ensureCommunityTables();

$user = getCurrentUser();
$userId = getCurrentUserId();
$errorLocal = '';
$successLocal = '';
$communityCsrf = communityCsrfToken();

$flash = getFlash();
if ($flash) {
    if ($flash['type'] === 'error') {
        $errorLocal = $flash['message'];
    } else {
        $successLocal = $flash['message'];
    }
}

$pageTitle = 'Circles & Feed';

$circlesDetailed = dbFetchAll(
    "SELECT ic.*,
            (SELECT COUNT(*) FROM inner_circle_members WHERE circle_id = ic.id) AS member_count,
            icm.role
     FROM inner_circles ic
     JOIN inner_circle_members icm ON ic.id = icm.circle_id
     WHERE icm.user_id = ?
     ORDER BY ic.created_at DESC",
    [$userId]
);

$requestedCircleId = (int) ($_GET['circle'] ?? 0);
$activeCircle = null;
foreach ($circlesDetailed as $c) {
    if ($requestedCircleId > 0 && (int) $c['id'] === $requestedCircleId) {
        $activeCircle = $c;
        break;
    }
}
if (!$activeCircle && !empty($circlesDetailed)) {
    $activeCircle = $circlesDetailed[0];
}

$members = [];
$pendingRequests = [];
if ($activeCircle) {
    $members = dbFetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.profile_pic, u.chat_bubble_color, icm.role,
                COALESCE(us.current_streak, 0) AS current_streak" . avatarSelectSql('u') . "
         FROM inner_circle_members icm
         JOIN users u ON icm.user_id = u.id
         LEFT JOIN user_streaks us ON us.user_id = u.id
         WHERE icm.circle_id = ?
         ORDER BY icm.role = 'owner' DESC, u.first_name",
        [(int) $activeCircle['id']]
    );
    if ($activeCircle['role'] === 'owner' && $communityReady) {
        $pendingRequests = dbFetchAll(
            "SELECT r.id, r.requested_at, r.requester_id, u.first_name, u.last_name, u.profile_pic
             FROM circle_join_requests r
             JOIN users u ON u.id = r.requester_id
             WHERE r.circle_id = ? AND r.status = 'pending'
             ORDER BY r.requested_at ASC",
            [(int) $activeCircle['id']]
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create_circle', 'join_circle'], true) && !validCommunityCsrf($_POST['csrf_token'] ?? null)) {
        $errorLocal = 'Your session expired. Please try again.';
    } elseif ($action === 'create_circle') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $errorLocal = 'Please enter a circle name';
        } elseif (strlen($name) > 100) {
            $errorLocal = 'Circle name must be 100 characters or less';
        } else {
            $inviteCode = generateInviteCode();
            while (dbFetchOne("SELECT id FROM inner_circles WHERE invite_code = ?", [$inviteCode])) {
                $inviteCode = generateInviteCode();
            }

            $pdo = getDbConnection();
            $pdo->beginTransaction();
            try {
                dbQuery(
                    "INSERT INTO inner_circles (name, description, created_by, invite_code, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [$name, $description, $userId, $inviteCode]
                );
                $newCircleId = dbLastId();
                dbQuery(
                    "INSERT INTO inner_circle_members (circle_id, user_id, role, joined_at)
                     VALUES (?, ?, 'owner', NOW())",
                    [$newCircleId, $userId]
                );
                $pdo->commit();
                setFlash('success', 'Circle created! Share your invite link with friends.');
                redirect('/challenge/app/settings/circles.php?circle=' . (int) $newCircleId);
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorLocal = 'Failed to create circle. Please try again.';
            }
        }
    } elseif ($action === 'join_circle') {
        $inviteCode = strtoupper(trim($_POST['invite_code'] ?? ''));
        if ($inviteCode === '') {
            $errorLocal = 'Please enter an invite code';
        } else {
            $targetCircle = dbFetchOne("SELECT * FROM inner_circles WHERE invite_code = ?", [$inviteCode]);
            if (!$targetCircle) {
                $errorLocal = 'Invalid invite code';
            } else {
                $existing = dbFetchOne(
                    "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
                    [$targetCircle['id'], $userId]
                );
                if ($existing) {
                    $errorLocal = 'You are already a member of this circle';
                } else {
                    $pdo = getDbConnection();
                    $pdo->beginTransaction();
                    try {
                        dbQuery(
                            "INSERT INTO inner_circle_members (circle_id, user_id, invited_by, role, joined_at)
                             VALUES (?, ?, ?, 'member', NOW())",
                            [$targetCircle['id'], $userId, $targetCircle['created_by']]
                        );
                        $inviteRewardInsert = dbQuery(
                            "INSERT IGNORE INTO invite_tracking
                                (inviter_id, invitee_id, circle_id, invite_code_used, reward_granted, created_at)
                             VALUES (?, ?, ?, ?, 1, NOW())",
                            [$targetCircle['created_by'], $userId, $targetCircle['id'], $inviteCode]
                        );
                        if ($inviteRewardInsert->rowCount() > 0) {
                            awardStreakRepair($targetCircle['created_by'], 1);
                        }
                        $pdo->commit();

                        $joiningName = trim((string) (($user['first_name'] ?? '') ?: 'Someone'));
                        try {
                            postSystemMessage(
                                $targetCircle['id'],
                                $userId,
                                $joiningName . ' has joined ' . $targetCircle['name'] . '!',
                                'system_join'
                            );
                        } catch (Exception $e) {
                            error_log('Welcome message failed: ' . $e->getMessage());
                        }
                        try {
                            notifyCircleMembersOfJoin((int) $targetCircle['id'], $userId, $joiningName, $targetCircle['name']);
                        } catch (Exception $e) {
                            error_log('Circle join push failed: ' . $e->getMessage());
                        }

                        setFlash('success', 'You joined "' . $targetCircle['name'] . '"!');
                        redirect('/challenge/app/settings/circles.php?circle=' . (int) $targetCircle['id']);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $errorLocal = 'Failed to join circle. Please try again.';
                    }
                }
            }
        }
    } elseif ($action === 'resolve_join_request') {
        if (!$communityReady) {
            $errorLocal = 'Circle requests are temporarily unavailable.';
        } elseif (!validCommunityCsrf($_POST['csrf_token'] ?? null)) {
            $errorLocal = 'Your session expired. Please try again.';
        } else {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $decision = ($_POST['decision'] ?? '') === 'approve' ? 'approved' : 'denied';
            $pdo = getDbConnection();
            $pdo->beginTransaction();
            try {
                $requestMeta = dbFetchOne(
                    "SELECT circle_id FROM circle_join_requests WHERE id = ?",
                    [$requestId]
                );
                $ownedCircleLock = $requestMeta ? dbFetchOne(
                    "SELECT id, name FROM inner_circles
                     WHERE id = ? AND created_by = ?
                     FOR UPDATE",
                    [(int) $requestMeta['circle_id'], $userId]
                ) : null;
                $request = dbFetchOne(
                    "SELECT r.*
                     FROM circle_join_requests r
                     WHERE r.id = ?
                     FOR UPDATE",
                    [$requestId]
                );
                if (!$ownedCircleLock || !$request || (int) $request['circle_id'] !== (int) $ownedCircleLock['id']
                    || $request['status'] !== 'pending') {
                    throw new RuntimeException('This request has already been resolved.');
                }
                $request['circle_name'] = $ownedCircleLock['name'];
                if ($decision === 'approved') {
                    dbQuery(
                        "INSERT IGNORE INTO inner_circle_members (circle_id, user_id, invited_by, role, joined_at)
                         VALUES (?, ?, ?, 'member', UTC_TIMESTAMP())",
                        [(int) $request['circle_id'], (int) $request['requester_id'], $userId]
                    );
                }
                dbQuery(
                    "UPDATE circle_join_requests
                     SET status = ?, pending_key = NULL, resolved_at = UTC_TIMESTAMP(), resolved_by = ?
                     WHERE id = ? AND status = 'pending'",
                    [$decision, $userId, $requestId]
                );
                $pdo->commit();

                if ($decision === 'approved') {
                    $requester = dbFetchOne("SELECT first_name FROM users WHERE id = ?", [(int) $request['requester_id']]);
                    $joiningName = trim((string) ($requester['first_name'] ?? 'Someone'));
                    try {
                        postSystemMessage((int) $request['circle_id'], (int) $request['requester_id'], $joiningName . ' has joined ' . $request['circle_name'] . '!', 'system_join');
                        notifyCircleMembersOfJoin((int) $request['circle_id'], (int) $request['requester_id'], $joiningName, $request['circle_name']);
                        if (function_exists('pushTablesReady') && function_exists('isPushConfigured') && function_exists('sendPushToUsers')
                            && pushTablesReady() && isPushConfigured()) {
                            sendPushToUsers(
                                [(int) $request['requester_id']],
                                'Circle request approved',
                                'You can now join the conversation in ' . $request['circle_name'] . '.',
                                '/challenge/app/feed.php?circle=' . (int) $request['circle_id']
                            );
                        }
                    } catch (Exception $e) {
                        error_log('Approved join notification failed: ' . $e->getMessage());
                    }
                }
                setFlash('success', $decision === 'approved' ? 'Join request approved.' : 'Join request denied.');
                redirect('/challenge/app/settings/circles.php?circle=' . (int) $request['circle_id']);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorLocal = $e instanceof RuntimeException ? $e->getMessage() : 'Could not resolve that request.';
            }
        }
    } elseif ($action === 'remove_member') {
        if (!validCommunityCsrf($_POST['csrf_token'] ?? null)) {
            $errorLocal = 'Your session expired. Please try again.';
        } else {
            $removeCircleId = (int) ($_POST['circle_id'] ?? 0);
            $removeMemberId = (int) ($_POST['member_id'] ?? 0);
            ensureFeedReactionTable();
            $pdo = getDbConnection();
            $pdo->beginTransaction();
            try {
                $ownedCircle = dbFetchOne(
                    "SELECT id, name FROM inner_circles WHERE id = ? AND created_by = ? FOR UPDATE",
                    [$removeCircleId, $userId]
                );
                if (!$ownedCircle) {
                    throw new RuntimeException('Only the circle owner can remove members.');
                }
                if ($removeMemberId === $userId) {
                    throw new RuntimeException('The circle owner cannot be removed.');
                }
                $target = dbFetchOne(
                    "SELECT u.first_name, u.last_name, icm.role
                     FROM inner_circle_members icm
                     JOIN users u ON u.id = icm.user_id
                     WHERE icm.circle_id = ? AND icm.user_id = ? FOR UPDATE",
                    [$removeCircleId, $removeMemberId]
                );
                if (!$target || $target['role'] === 'owner') {
                    throw new RuntimeException('That member is no longer available to remove.');
                }

                dbQuery(
                    "DELETE cmr FROM circle_message_reactions cmr
                     JOIN circle_messages cm ON cm.id = cmr.message_id
                     WHERE cm.circle_id = ? AND cmr.user_id = ?",
                    [$removeCircleId, $removeMemberId]
                );
                dbQuery("DELETE FROM circle_messages WHERE circle_id = ? AND user_id = ?", [$removeCircleId, $removeMemberId]);
                dbQuery("DELETE FROM inner_circle_members WHERE circle_id = ? AND user_id = ?", [$removeCircleId, $removeMemberId]);
                $pdo->commit();

                $removedName = trim((string) $target['first_name'] . ' ' . (string) $target['last_name']);
                setFlash('success', $removedName . ' was removed. Their messages in this circle were deleted.');
                redirect('/challenge/app/settings/circles.php?circle=' . $removeCircleId);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errorLocal = $e instanceof RuntimeException ? $e->getMessage() : 'Could not remove that member. Please try again.';
            }
        }
    } elseif ($action === 'delete_circle') {
        if (!validCommunityCsrf($_POST['csrf_token'] ?? null)) {
            $errorLocal = 'Your session expired. Please try again.';
        } else {
            $deleteCircleId = (int) ($_POST['circle_id'] ?? 0);
            $typedName = trim((string) ($_POST['confirm_circle_name'] ?? ''));
            $ownedCircle = dbFetchOne("SELECT id, name FROM inner_circles WHERE id = ? AND created_by = ?", [$deleteCircleId, $userId]);
            if (!$ownedCircle) {
                $errorLocal = 'Only the circle owner can delete this circle.';
            } elseif (!hash_equals((string) $ownedCircle['name'], $typedName)) {
                $errorLocal = 'Type the exact circle name to confirm deletion.';
            } else {
                $pdo = getDbConnection();
                $pdo->beginTransaction();
                try {
                    dbQuery("DELETE FROM inner_circles WHERE id = ? AND created_by = ?", [$deleteCircleId, $userId]);
                    $pdo->commit();
                    $fallback = dbFetchOne(
                        "SELECT ic.id FROM inner_circles ic
                         JOIN inner_circle_members icm ON icm.circle_id = ic.id
                         WHERE icm.user_id = ? ORDER BY ic.created_at DESC LIMIT 1",
                        [$userId]
                    );
                    setFlash('success', 'Circle deleted. Your personal challenge history was not changed.');
                    redirect('/challenge/app/settings/circles.php' . ($fallback ? '?circle=' . (int) $fallback['id'] : ''));
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errorLocal = 'Could not delete the circle. Please try again.';
                }
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Circles & Feed'); ?>
    <?php renderSettingsAlerts($errorLocal, $successLocal); ?>

    <div class="settings-card settings-detail-card">
        <div class="settings-card-header">
            <h3>Your circles</h3>
        </div>
        <p class="form-hint">Create, join, and manage Inner Circles. Chat stays on the Feed tab.</p>

        <div class="circles-settings-actions">
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('createCircleModal').classList.add('active')">
                <i data-lucide="plus"></i> Create
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('joinCircleModal').classList.add('active')">
                <i data-lucide="key"></i> Join
            </button>
        </div>

        <?php if (empty($circlesDetailed)): ?>
            <div class="empty-state compact">
                <p>No circles yet. Create one or join with an invite code.</p>
            </div>
        <?php else: ?>
            <div class="circle-settings-switcher">
                <?php foreach ($circlesDetailed as $c): ?>
                    <a href="/challenge/app/settings/circles.php?circle=<?= (int) $c['id'] ?>"
                       class="circle-switcher-item <?= $activeCircle && (int) $c['id'] === (int) $activeCircle['id'] ? 'active' : '' ?>">
                        <?= h($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($activeCircle): ?>
                <a href="/challenge/app/feed.php?circle=<?= (int) $activeCircle['id'] ?>" class="btn btn-primary btn-sm circle-feed-switch-btn">
                    <i data-lucide="repeat-2"></i> Switch to <?= h($activeCircle['name']) ?> Feed
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($activeCircle): ?>
    <div class="settings-card settings-detail-card">
        <div class="settings-card-header">
            <h3><?= h($activeCircle['name']) ?></h3>
            <?php if ($activeCircle['role'] === 'owner'): ?>
                <span class="badge badge-owner">Owner</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($activeCircle['description'])): ?>
            <p class="circle-description-text"><?= h($activeCircle['description']) ?></p>
        <?php endif; ?>
        <p class="form-hint"><?= (int) $activeCircle['member_count'] ?> members</p>

        <div class="members-section">
            <h4>Members</h4>
            <div class="members-grid">
                <?php foreach ($members as $member): ?>
                    <div class="member-card">
                        <a class="member-avatar" href="/challenge/app/member_profile.php?id=<?= (int) $member['id'] ?>" aria-label="View <?= h($member['first_name']) ?>'s profile">
                            <?= renderUserPublicFace($member, 'sm') ?>
                        </a>
                        <div class="member-details">
                            <span class="member-name"><?= h($member['first_name'] . ' ' . $member['last_name']) ?></span>
                            <span class="member-streak" title="Current streak">
                                <i data-lucide="flame"></i> <?= (int) $member['current_streak'] ?>
                            </span>
                            <?php if ($member['role'] === 'owner'): ?>
                                <span class="badge badge-owner">Owner</span>
                            <?php endif; ?>
                            <?php if ((int) $member['id'] === (int) $userId): ?>
                                <span class="badge badge-you">You</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($activeCircle['role'] === 'owner' && $member['role'] !== 'owner'): ?>
                            <button type="button" class="btn btn-danger btn-sm member-remove-btn"
                                    aria-label="Remove <?= h(trim($member['first_name'] . ' ' . $member['last_name'])) ?>"
                                    title="Remove <?= h(trim($member['first_name'] . ' ' . $member['last_name'])) ?>"
                                    onclick="openRemoveMemberModal(<?= (int) $member['id'] ?>, <?= h(json_encode(trim($member['first_name'] . ' ' . $member['last_name']))) ?>)">
                                <i data-lucide="trash-2"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($activeCircle['role'] === 'owner'): ?>
        <div class="pending-requests-section">
            <h4>Pending Requests</h4>
            <?php if (!$communityReady): ?>
                <p class="form-hint">Requests are temporarily unavailable while the community update is applied.</p>
            <?php elseif (empty($pendingRequests)): ?>
                <p class="form-hint">No one is waiting to join this circle.</p>
            <?php else: ?>
                <div class="pending-request-list">
                    <?php foreach ($pendingRequests as $pendingRequest): ?>
                    <div class="pending-request-row">
                        <div class="pending-request-person">
                            <span class="avatar-placeholder"><?= h(strtoupper(substr((string) ($pendingRequest['first_name'] ?: 'U'), 0, 1))) ?></span>
                            <span>
                                <strong><?= h(trim($pendingRequest['first_name'] . ' ' . $pendingRequest['last_name'])) ?></strong>
                                <small>Requested <?= h((new DateTime($pendingRequest['requested_at']))->format('M j')) ?></small>
                            </span>
                        </div>
                        <div class="pending-request-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="resolve_join_request">
                                <input type="hidden" name="csrf_token" value="<?= h($communityCsrf) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $pendingRequest['id'] ?>">
                                <input type="hidden" name="decision" value="deny">
                                <button type="submit" class="btn btn-secondary btn-sm">Deny</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="resolve_join_request">
                                <input type="hidden" name="csrf_token" value="<?= h($communityCsrf) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $pendingRequest['id'] ?>">
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p class="appearance-settings-hint">
            <i data-lucide="palette"></i>
            Change your chat bubble color in
            <a href="/challenge/app/settings/appearance.php">Appearance</a>.
        </p>

        <?php if ($activeCircle['role'] === 'owner'): ?>
        <div class="invite-section">
            <h4>Invite Friends</h4>
            <p class="invite-hint">Share this link — they join automatically. You earn a streak repair when someone joins.</p>
            <div class="invite-link-box">
                <input type="text" id="inviteLinkInput" readonly
                       value="https://unmaskedculture.org/challenge/app/join.php?code=<?= h($activeCircle['invite_code']) ?>">
                <button type="button" class="btn btn-primary btn-sm" onclick="copyInviteLink()">
                    <i data-lucide="copy"></i> Copy
                </button>
            </div>
        </div>
        <div class="circle-danger-zone">
            <h4>Delete circle</h4>
            <p>Only delete this circle if the community should be permanently removed.</p>
            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('deleteCircleModal').classList.add('active')">
                <i data-lucide="trash-2"></i> Delete <?= h($activeCircle['name']) ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($activeCircle && $activeCircle['role'] === 'owner'): ?>
<div id="removeMemberModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="removeMemberTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="removeMemberTitle">Remove member?</h3>
            <button type="button" class="modal-close" onclick="closeRemoveMemberModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="remove_member">
            <input type="hidden" name="csrf_token" value="<?= h($communityCsrf) ?>">
            <input type="hidden" name="circle_id" value="<?= (int) $activeCircle['id'] ?>">
            <input type="hidden" name="member_id" id="removeMemberId" value="">
            <div class="modal-body">
                <p><strong id="removeMemberName"></strong> will lose access to this circle.</p>
                <div class="alert alert-warning">
                    Their messages and reactions in this circle will be permanently deleted. Their personal challenge and journal history will not be changed.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRemoveMemberModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Remove member</button>
            </div>
        </form>
    </div>
</div>
<script>
function openRemoveMemberModal(memberId, memberName) {
    document.getElementById('removeMemberId').value = memberId;
    document.getElementById('removeMemberName').textContent = memberName;
    document.getElementById('removeMemberModal').classList.add('active');
}
function closeRemoveMemberModal() {
    document.getElementById('removeMemberModal').classList.remove('active');
}
</script>
<?php endif; ?>

<div id="createCircleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Inner Circle</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('createCircleModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_circle">
            <input type="hidden" name="csrf_token" value="<?= h($communityCsrf) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="circleName">Circle Name</label>
                    <input type="text" id="circleName" name="name" maxlength="100" required placeholder="e.g., Morning Warriors">
                </div>
                <div class="form-group">
                    <label for="circleDesc">Description (optional)</label>
                    <textarea id="circleDesc" name="description" rows="3" placeholder="What's this circle about?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createCircleModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Circle</button>
            </div>
        </form>
    </div>
</div>

<div id="joinCircleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Join Inner Circle</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('joinCircleModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="join_circle">
            <input type="hidden" name="csrf_token" value="<?= h($communityCsrf) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="inviteCode">Invite Code</label>
                    <input type="text" id="inviteCode" name="invite_code" maxlength="8" required
                           placeholder="ABC12DEF" style="text-transform: uppercase; letter-spacing: 2px; text-align: center;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('joinCircleModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary">Join Circle</button>
            </div>
        </form>
    </div>
</div>

<?php if ($activeCircle && $activeCircle['role'] === 'owner'): ?>
<div id="deleteCircleModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="deleteCircleTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="deleteCircleTitle">Permanently delete circle?</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('deleteCircleModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_circle">
            <input type="hidden" name="csrf_token" value="<?= h($communityCsrf) ?>">
            <input type="hidden" name="circle_id" value="<?= (int) $activeCircle['id'] ?>">
            <div class="modal-body">
                <div class="alert alert-danger">
                    This permanently removes the circle, all chat messages, memberships, invite links, pending invites, and join requests.
                    Members' personal challenge, mood, journal, and checklist history will not be deleted.
                </div>
                <div class="form-group">
                    <label for="confirmCircleName">Type <strong><?= h($activeCircle['name']) ?></strong> to confirm</label>
                    <input type="text" id="confirmCircleName" name="confirm_circle_name" autocomplete="off" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteCircleModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete circle permanently</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function copyInviteLink() {
    const input = document.getElementById('inviteLinkInput');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Invite link copied!');
    }).catch(() => {
        input.select();
        document.execCommand('copy');
        alert('Invite link copied!');
    });
}
document.querySelectorAll('#createCircleModal, #joinCircleModal, #deleteCircleModal').forEach((modal) => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });
});
if (typeof lucide !== 'undefined') lucide.createIcons();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
