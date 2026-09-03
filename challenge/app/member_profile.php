<?php
/**
 * Shared member profile.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/community_service.php';
require_once __DIR__ . '/../includes/shop_service.php';
require_once __DIR__ . '/../includes/shop_catalog.php';
require_once __DIR__ . '/../includes/avatar_service.php';
require_once __DIR__ . '/../includes/avatar_render.php';
require_once __DIR__ . '/../includes/jar_service.php';

ensurePublicProfileColumns();
ensureShopTablesAndColumns();
$communityReady = ensureCommunityTables();

$viewer = isLoggedIn() ? getCurrentUser() : null;
$viewerId = (int) (getCurrentUserId() ?? 0);
$memberProfileColumns = implode(', ', [
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
$shopColumns = shopEquipSelectColumns();
$avatarColumns = avatarSelectColumns();
$profileSlug = trim((string) ($_GET['u'] ?? ''));
$memberId = (int) ($_GET['id'] ?? 0);

if ($profileSlug === '' && $viewerId < 1) {
    setFlash('error', 'Use a shared profile link to view this profile.');
    redirect('/challenge/app/');
}

if ($profileSlug !== '' && hasPublicProfileColumn('public_profile_slug')) {
    $member = dbFetchOne(
        "SELECT id, first_name, last_name, profile_pic, challenge_mode, $memberProfileColumns, $shopColumns, $avatarColumns, timezone, created_at
         FROM users WHERE public_profile_slug = ?",
        [$profileSlug]
    );
} else {
    $member = $memberId > 0 ? dbFetchOne(
        "SELECT id, first_name, last_name, profile_pic, challenge_mode, $memberProfileColumns, $shopColumns, $avatarColumns, timezone, created_at
         FROM users WHERE id = ?",
        [$memberId]
    ) : null;
}

if (!$member) {
    setFlash('error', 'This profile is unavailable.');
    redirect($viewerId > 0 ? '/challenge/app/feed.php' : '/challenge/app/');
}
$memberId = (int) $member['id'];

$profileVisible = !empty($member['profile_visible']) && !empty($member['public_profile_slug']);
$publicPath = $profileVisible
    ? '/challenge/app/member_profile.php?u=' . rawurlencode($member['public_profile_slug'])
    : '/challenge/app/member_profile.php?id=' . $memberId;
$publicUrl = $profileVisible ? 'https://unmaskedculture.org' . $publicPath : '';
$requestedPublicCircle = (int) ($_GET['circle'] ?? 0);

$sharedCircle = null;
if ($viewerId > 0 && $memberId !== $viewerId) {
    $sharedCircle = dbFetchOne(
        "SELECT ic.id, ic.name
         FROM inner_circle_members mine
         JOIN inner_circle_members theirs ON mine.circle_id = theirs.circle_id
         JOIN inner_circles ic ON ic.id = mine.circle_id
         WHERE mine.user_id = ? AND theirs.user_id = ?
         ORDER BY ic.created_at DESC
         LIMIT 1",
        [$viewerId, $memberId]
    );

}
if (!$profileVisible && $memberId !== $viewerId && !$sharedCircle) {
    setFlash('error', 'This profile is unavailable.');
    redirect($viewerId > 0 ? '/challenge/app/feed.php' : '/challenge/app/');
}

if ($memberId === $viewerId) {
    $sharedCircle = dbFetchOne(
        "SELECT ic.id, ic.name
         FROM inner_circle_members icm
         JOIN inner_circles ic ON ic.id = icm.circle_id
         WHERE icm.user_id = ?
         ORDER BY ic.created_at DESC
         LIMIT 1",
        [$viewerId]
    );
}
$canContributeToJar = $viewerId > 0 && $memberId !== $viewerId && $sharedCircle !== null;
$jarCsrf = $canContributeToJar ? jarCsrfToken() : '';
$jarTypes = $canContributeToJar ? jarEntryTypes() : [];

$ownedCircles = dbFetchAll(
    "SELECT id, name, description
     FROM inner_circles
     WHERE created_by = ?
     ORDER BY created_at DESC",
    [$memberId]
);
$requestStatusByCircle = [];
if ($viewerId > 0 && $communityReady) {
    $requestRows = dbFetchAll(
        "SELECT circle_id, status FROM circle_join_requests
         WHERE requester_id = ? ORDER BY requested_at DESC",
        [$viewerId]
    );
    foreach ($requestRows as $requestRow) {
        $cid = (int) $requestRow['circle_id'];
        if (!isset($requestStatusByCircle[$cid])) {
            $requestStatusByCircle[$cid] = $requestRow['status'];
        }
    }
} else {
    $_SESSION['post_auth_return'] = $publicPath . ($requestedPublicCircle > 0 ? '&circle=' . $requestedPublicCircle : '');
}

$streakStatus = getStreakStatus($memberId, false);
$currentStreak = (int) ($streakStatus['current_streak'] ?? 0);
$journeyDay = min(77, max(0, $currentStreak));
$journeyProgress = round(($journeyDay / 77) * 100, 2);
$challengeMode = normalizeChallengeMode($streakStatus['challenge_mode'] ?? ($member['challenge_mode'] ?? 'intermediate'));
$challengeModeLabel = $challengeMode === 'easy' ? 'Easy Challenge' : 'Intermediate Challenge';
$startDate = new DateTime($member['created_at']);

if ($currentStreak > 0 && !empty($streakStatus['last_completed_date'])) {
    $anchor = new DateTime($streakStatus['last_completed_date']);
    $startDate = (clone $anchor)->modify('-' . max(0, $currentStreak - 1) . ' days');
    $goalDate = (clone $anchor)->modify('+' . max(0, 77 - $currentStreak) . ' days');
} else {
    $goalDate = (clone $startDate)->modify('+77 days');
}

$displayName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = 'Challenge Member';
}
$shareUrl = $profileVisible ? $publicUrl : 'https://unmaskedculture.org/kinto';
$shareTitle = $profileVisible
    ? $displayName . ' on Kinto'
    : 'Start Kinto';
$shareText = $profileVisible
    ? $displayName . ' is building a daily wellness rhythm with Kinto. Join the challenge or connect with them.'
    : 'Join me in Kinto and start building a daily wellness rhythm.';
$promptQuestion = getProfilePromptQuestion($member['profile_prompt_key'] ?? null);
$activityLabel = profileActivityLabel($member['last_active_at'] ?? null, $member['timezone'] ?? DEFAULT_TIMEZONE);
$communityCsrf = $viewerId > 0 ? communityCsrfToken() : '';
$cosmetics = resolveEquippedCosmetics($member);

$pageTitle = $displayName;
$bodyClass = $cosmetics['background_css'] !== ''
    ? 'member-profile-immersive ' . $cosmetics['background_css']
    : '';
include __DIR__ . '/../includes/header.php';
?>

<div class="<?= h(trim('member-profile-page' . ($cosmetics['background_css'] !== '' ? ' has-shop-bg ' . $cosmetics['background_css'] : ''))) ?>">
    <div class="member-profile-card">
        <?php
        $heroStyle = '--profile-x:' . (int) ($member['profile_banner_x'] ?? 50) . '%;'
            . '--profile-y:' . (int) ($member['profile_banner_y'] ?? 50) . '%;'
            . '--profile-zoom:' . (float) ($member['profile_banner_zoom'] ?? 1) . ';';
        $usesAvatarFace = userUsesAvatarFace($member);
        $heroClasses = 'member-profile-hero';
        if ($usesAvatarFace) {
            $heroClasses .= ' has-avatar-face';
        } elseif ($member['profile_pic']) {
            $heroClasses .= ' has-photo';
        }
        if ($cosmetics['frame_css'] !== '') {
            $heroClasses .= ' ' . $cosmetics['frame_css'];
        }
        $statsClasses = 'member-profile-stats';
        if ($cosmetics['banner_css'] !== '') {
            $statsClasses .= ' ' . $cosmetics['banner_css'];
        }
        $bannerTextColor = strtolower(trim((string) ($member['profile_banner_text_color'] ?? '')));
        $statsStyle = '';
        if (preg_match('/^#[0-9a-f]{6}$/', $bannerTextColor)) {
            $statsClasses .= ' has-custom-banner-text';
            $statsStyle = '--profile-banner-text:' . $bannerTextColor . ';';
        }
        ?>
        <div class="<?= h($heroClasses) ?>" style="<?= h($heroStyle) ?>">
            <?php if ($usesAvatarFace): ?>
                <?= renderKintoAvatar(resolveEquippedAvatar($member), ['size' => 'lg', 'animate' => true]) ?>
            <?php elseif ($member['profile_pic']): ?>
                <img class="member-profile-cover" src="<?= h(profilePicUrl($member['profile_pic'])) ?>" alt="">
            <?php endif; ?>
            <?php if (!$usesAvatarFace): ?>
                <div class="member-profile-hero-overlay" aria-hidden="true"></div>
            <?php endif; ?>
            <?php if ($cosmetics['frame_css'] !== ''): ?>
                <div class="shop-frame-overlay" aria-hidden="true"></div>
            <?php endif; ?>
            <?php if (!$usesAvatarFace && !$member['profile_pic']): ?>
                <div class="member-profile-placeholder avatar-placeholder" aria-hidden="true">
                    <?= h(strtoupper(substr((string) ($member['first_name'] ?: 'U'), 0, 1))) ?>
                </div>
            <?php endif; ?>
        </div>

        <section class="member-profile-identity" aria-labelledby="memberProfileName">
            <div class="member-profile-identity-copy">
                <h1 id="memberProfileName"><?= h($displayName) ?></h1>
                <p class="member-profile-activity"><i data-lucide="clock-3"></i> <?= h($activityLabel) ?></p>
            </div>
            <div class="member-profile-actions">
                <?php if ($memberId === $viewerId): ?>
                    <a href="/challenge/app/settings/profile.php" class="btn btn-secondary btn-sm">
                        <i data-lucide="pencil"></i><span>Edit</span>
                    </a>
                    <a href="/challenge/app/avatar.php" class="btn btn-secondary btn-sm">
                        <i data-lucide="smile"></i><span>Avatar</span>
                    </a>
                    <a href="/challenge/app/settings/shop.php" class="btn btn-secondary btn-sm">
                        <i data-lucide="palette"></i><span>Customize</span>
                    </a>
                <?php elseif ($sharedCircle): ?>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="showModal('profileJarModal')">
                        <i data-lucide="archive"></i><span>Add to Jar</span>
                    </button>
                    <a href="/challenge/app/feed.php?circle=<?= (int) $sharedCircle['id'] ?>" class="btn btn-primary btn-sm">
                        <i data-lucide="message-circle"></i><span>Message</span>
                    </a>
                <?php endif; ?>
                <button
                    type="button"
                    class="btn btn-secondary btn-sm"
                    id="shareProfileButton"
                    data-share-url="<?= h($shareUrl) ?>"
                    data-share-title="<?= h($shareTitle) ?>"
                    data-share-text="<?= h($shareText) ?>"
                >
                    <i data-lucide="share-2"></i><span><?= $profileVisible ? 'Share' : 'Invite' ?></span>
                </button>
            </div>
        </section>

        <div class="<?= h($statsClasses) ?>"<?= $statsStyle !== '' ? ' style="' . h($statsStyle) . '"' : '' ?> aria-label="Challenge progress">
            <div class="member-profile-progress-heading">
                <div>
                    <span>Journey progress</span>
                    <strong>Day <?= $journeyDay ?> of 77</strong>
                </div>
                <span class="member-profile-mode"><?= h($challengeModeLabel) ?></span>
            </div>
            <div class="member-profile-progress-track" role="progressbar" aria-label="77-day journey progress" aria-valuemin="0" aria-valuemax="77" aria-valuenow="<?= $journeyDay ?>">
                <span style="width:<?= h((string) $journeyProgress) ?>%"></span>
            </div>
            <div class="member-profile-stat-grid">
                <div class="member-profile-stat"><span>Current streak</span><strong><?= $currentStreak ?> <small>days</small></strong></div>
                <div class="member-profile-stat"><span>Journey started</span><strong><?= h($startDate->format('M j')) ?></strong></div>
                <div class="member-profile-stat"><span>Target finish</span><strong><?= h($goalDate->format('M j')) ?></strong></div>
            </div>
            <?php if (!empty($cosmetics['stickers']) || $memberId === $viewerId): ?>
            <div class="member-profile-stickers" aria-label="Equipped stickers">
                <p class="member-profile-stickers-label">Equipped</p>
                <div class="member-profile-stickers-row">
                    <?php if (empty($cosmetics['stickers'])): ?>
                        <a class="member-profile-stickers-empty" href="/challenge/app/settings/shop.php?tab=sticker">Add stickers</a>
                    <?php else: ?>
                        <?php foreach ($cosmetics['stickers'] as $sticker): ?>
                            <span class="shop-sticker <?= h($sticker['css']) ?>" title="<?= h(getShopItem($sticker['id'])['name'] ?? 'Sticker') ?>" aria-hidden="true"></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="member-profile-details">
        <?php if (!empty($member['profile_bio'])): ?>
            <section class="member-profile-section">
                <h2>Bio</h2>
                <p><?= nl2br(h($member['profile_bio'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if (!empty($member['profile_prompt_answer'])): ?>
            <section class="member-profile-section prompt">
                <h2><?= h($promptQuestion) ?></h2>
                <p><?= nl2br(h($member['profile_prompt_answer'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if (empty($member['profile_bio']) && empty($member['profile_prompt_answer'])): ?>
            <section class="member-profile-section empty">
                <h2>Profile</h2>
                <p>No bio or prompt yet.</p>
            </section>
        <?php endif; ?>
        </div>

        <?php if ($profileVisible && $memberId !== $viewerId): ?>
        <section class="member-profile-section member-profile-circles">
            <h2>Join a circle</h2>
            <?php if (!$communityReady): ?>
                <p>Circle requests are temporarily unavailable. Please try again later.</p>
            <?php elseif (empty($ownedCircles)): ?>
                <p><?= h($member['first_name']) ?> does not have a public circle available right now.</p>
            <?php elseif ($viewerId < 1): ?>
                <p>Join Kinto to request access to one of <?= h($member['first_name']) ?>'s circles.</p>
                <div class="public-profile-circle-list">
                    <?php foreach ($ownedCircles as $ownedCircle): ?>
                        <a class="public-profile-circle" href="/challenge/app/register.php?return=<?= rawurlencode($publicPath . '&circle=' . (int) $ownedCircle['id']) ?>">
                            <strong><?= h($ownedCircle['name']) ?></strong>
                            <span>Join the Challenge today</span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <p class="public-profile-signin">Already a member? <a href="/kinto?return=<?= rawurlencode($publicPath . ($requestedPublicCircle > 0 ? '&circle=' . $requestedPublicCircle : '')) ?>#signin">Sign in to request access</a>.</p>
            <?php else: ?>
                <div class="public-profile-circle-list">
                    <?php foreach ($ownedCircles as $ownedCircle): ?>
                        <?php
                        $ownedCircleId = (int) $ownedCircle['id'];
                        $alreadyMember = dbFetchOne(
                            "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
                            [$ownedCircleId, $viewerId]
                        );
                        $requestStatus = $requestStatusByCircle[$ownedCircleId] ?? null;
                        ?>
                        <div class="public-profile-circle<?= $requestedPublicCircle === $ownedCircleId ? ' is-intended' : '' ?>">
                            <div>
                                <strong><?= h($ownedCircle['name']) ?></strong>
                                <?php if (!empty($ownedCircle['description'])): ?><p><?= h($ownedCircle['description']) ?></p><?php endif; ?>
                            </div>
                            <?php if ($alreadyMember): ?>
                                <a class="btn btn-secondary btn-sm" href="/challenge/app/feed.php?circle=<?= $ownedCircleId ?>">Open circle</a>
                            <?php elseif ($requestStatus === 'pending'): ?>
                                <span class="badge">Request pending</span>
                            <?php else: ?>
                                <form method="POST" action="/challenge/api/circle_join_request.php">
                                    <input type="hidden" name="csrf_token" value="<?= h($communityCsrf) ?>">
                                    <input type="hidden" name="circle_id" value="<?= $ownedCircleId ?>">
                                    <input type="hidden" name="return_to" value="<?= h($publicPath) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Request to join circle</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </div>
</div>

<?php if ($canContributeToJar): ?>
<div id="profileJarModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="profileJarTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="profileJarTitle">Add to <?= h((string) $member['first_name']) ?>'s Jar</h3>
            <button type="button" class="modal-close" onclick="closeModal('profileJarModal')" aria-label="Close">&times;</button>
        </div>
        <form id="profileJarForm" class="profile-jar-form">
            <div class="modal-body">
                <p class="profile-jar-intro">Share an encouragement, memory, happy moment, or something meaningful they can pull from their Jar later.</p>
                <div class="form-group">
                    <label for="profileJarType">Type <span class="form-hint">(optional)</span></label>
                    <select id="profileJarType" class="form-select">
                        <?php foreach ($jarTypes as $value => $label): ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="profileJarMessage">Your note</label>
                    <textarea id="profileJarMessage" class="form-textarea" rows="6" maxlength="600" required placeholder="Write something worth keeping..."></textarea>
                    <div class="jar-input-meta"><span id="profileJarStatus" role="status" aria-live="polite"></span><span><strong id="profileJarCount">0</strong>/600</span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('profileJarModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="archive"></i> Add to Jar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('shareProfileButton')?.addEventListener('click', async function () {
    const url = this.dataset.shareUrl;
    const title = this.dataset.shareTitle || <?= json_encode($displayName . ' on Kinto') ?>;
    const text = this.dataset.shareText || 'Join me in Kinto.';
    try {
        if (navigator.share) {
            await navigator.share({ title, text, url });
        } else {
            await navigator.clipboard.writeText(url);
            alert('Invite link copied!');
        }
    } catch (error) {
        if (error?.name !== 'AbortError') {
            window.prompt('Copy this profile link:', url);
        }
    }
});

<?php if ($canContributeToJar): ?>
(function () {
    const form = document.getElementById('profileJarForm');
    const message = document.getElementById('profileJarMessage');
    const count = document.getElementById('profileJarCount');
    const status = document.getElementById('profileJarStatus');
    message?.addEventListener('input', () => { if (count) count.textContent = String(message.value.length); });
    form?.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        if (status) status.textContent = 'Adding to the Jar...';
        try {
            const response = await fetch('/challenge/api/jar_add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    csrf_token: <?= json_encode($jarCsrf) ?>,
                    target_user_id: <?= $memberId ?>,
                    entry_type: document.getElementById('profileJarType')?.value || 'general',
                    message: message?.value || ''
                })
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) throw new Error(data.error || 'Unable to add to the Jar.');
            if (status) status.textContent = 'Added! They will find it in their Jar.';
            if (message) message.value = '';
            if (count) count.textContent = '0';
            window.setTimeout(() => closeModal('profileJarModal'), 1100);
        } catch (error) {
            if (status) status.textContent = error.message;
        } finally {
            if (submit) submit.disabled = false;
        }
    });
})();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
