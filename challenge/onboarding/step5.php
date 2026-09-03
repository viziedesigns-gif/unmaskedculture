<?php
/**
 * Onboarding Step 5 - Daily checklist overview & complete
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_service.php';
require_once __DIR__ . '/../includes/xp_service.php';
requireLogin();

$user = getCurrentUser();
if ($user['onboarding_completed']) {
    redirect('/challenge/app/dashboard.php');
}

ensureXpTablesAndColumns();
$mode = normalizeChallengeMode($user['challenge_mode'] ?? 'intermediate');
$onboardingCsrfToken = $_SESSION['onboarding_finish_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['onboarding_finish_csrf'] = $onboardingCsrfToken;

$checklistItems = dbFetchAll(
    "SELECT * FROM daily_checklist_items WHERE active = 1 ORDER BY sort_order"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if ($postedToken === '' || !hash_equals($onboardingCsrfToken, $postedToken)) {
        setFlash('error', 'Your onboarding form expired. Please try again.');
        redirect('/challenge/onboarding/step5.php');
    }

    $pdo = getDbConnection();
    $pdo->beginTransaction();

    try {
        if (!updateUserProfile(getCurrentUserId(), ['onboarding_completed' => 1])) {
            throw new RuntimeException('Could not complete onboarding.');
        }

        if (isset($_SESSION['pending_invite_code'])) {
            $inviteCode = $_SESSION['pending_invite_code'];
            $circle = dbFetchOne("SELECT * FROM inner_circles WHERE invite_code = ?", [$inviteCode]);

            if ($circle) {
                $existing = dbFetchOne(
                    "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
                    [$circle['id'], getCurrentUserId()]
                );

                if (!$existing) {
                    dbQuery(
                        "INSERT INTO inner_circle_members (circle_id, user_id, invited_by, role, joined_at)
                         VALUES (?, ?, ?, 'member', NOW())",
                        [$circle['id'], getCurrentUserId(), $circle['created_by']]
                    );
                    $inviteRewardInsert = dbQuery(
                        "INSERT IGNORE INTO invite_tracking
                            (inviter_id, invitee_id, circle_id, invite_code_used, reward_granted, created_at)
                         VALUES (?, ?, ?, ?, 1, NOW())",
                        [$circle['created_by'], getCurrentUserId(), $circle['id'], $inviteCode]
                    );
                    if ($inviteRewardInsert->rowCount() > 0) {
                        awardStreakRepair($circle['created_by'], 1);
                    }
                    $joinedCircle = $circle;
                }
            }

            unset($_SESSION['pending_invite_code'], $_SESSION['pending_circle_name']);
        } elseif (isset($_SESSION['pending_circle_join'])) {
            $pendingJoin = $_SESSION['pending_circle_join'];
            dbQuery(
                "INSERT INTO inner_circle_members (circle_id, user_id, invited_by, role, joined_at)
                 VALUES (?, ?, ?, 'member', NOW())",
                [$pendingJoin['circle_id'], getCurrentUserId(), $pendingJoin['inviter_id']]
            );
            $inviteRewardInsert = dbQuery(
                "INSERT IGNORE INTO invite_tracking
                    (inviter_id, invitee_id, circle_id, invite_code_used, reward_granted, created_at)
                 VALUES (?, ?, ?, ?, 1, NOW())",
                [$pendingJoin['inviter_id'], getCurrentUserId(), $pendingJoin['circle_id'], $pendingJoin['invite_code']]
            );
            if ($inviteRewardInsert->rowCount() > 0) {
                awardStreakRepair($pendingJoin['inviter_id'], 1);
            }
            $joinedCircle = dbFetchOne("SELECT * FROM inner_circles WHERE id = ?", [$pendingJoin['circle_id']]);
            unset($_SESSION['pending_circle_join']);
        }

        $pdo->commit();

        if (isset($joinedCircle)) {
            $joiningName = trim((string) (($user['first_name'] ?? '') ?: 'Someone'));
            try {
                postSystemMessage(
                    $joinedCircle['id'],
                    getCurrentUserId(),
                    $joiningName . ' has joined ' . $joinedCircle['name'] . '!',
                    'system_join'
                );
            } catch (Exception $e) {
                error_log("Welcome message failed: " . $e->getMessage());
            }
            try {
                notifyCircleMembersOfJoin((int) $joinedCircle['id'], getCurrentUserId(), $joiningName, $joinedCircle['name']);
            } catch (Exception $e) {
                error_log("Circle join push failed: " . $e->getMessage());
            }
        }

        $modeLabel = $mode === 'easy' ? 'Easy' : 'Intermediate';
        unset($_SESSION['onboarding_finish_csrf']);
        setFlash('success', "Welcome to the 77-Day Challenge ($modeLabel mode)! Your journey begins now.");
        if (!empty($_SESSION['post_auth_return'])) {
            $returnTo = $_SESSION['post_auth_return'];
            unset($_SESSION['post_auth_return']);
            redirect($returnTo);
        }
        redirect('/challenge/app/dashboard.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Onboarding completion error: " . $e->getMessage());
        setFlash('error', 'Something went wrong. Please try again.');
    }
}

$pageTitle = 'Ready to Start';
$hideNav = true;
$bodyClass = 'onboarding-page';
$itemIcons = [
    'water' => '&#128167;',
    'book' => '&#128214;',
    'fitness' => '&#128170;',
    'journal' => '&#128221;',
    'heart' => '&#10084;&#65039;',
    'no-food' => '&#128683;',
    'no-drink' => '&#128683;',
    'scale' => '&#9878;&#65039;',
];
$modeBlurb = $mode === 'easy'
    ? 'On Easy, checking at least one item before 1:00 AM (your timezone) advances your streak. We still track what you skip.'
    : 'On Intermediate, complete all required items before midnight (your timezone) to build your streak.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-NHSZ9V9D');</script>
    <!-- End Google Tag Manager -->
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-BBEQYLCZDX"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-BBEQYLCZDX');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Kinto</title>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <link rel="stylesheet" href="<?= h(assetUrl('/challenge/assets/css/style.css')) ?>">
    <script src="<?= h(assetUrl('/challenge/assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= h($bodyClass) ?>">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NHSZ9V9D"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <div class="onboarding-container onboarding-wide">
        <div class="onboarding-progress">
            <div class="progress-bar"><div class="progress-fill" style="width: 100%"></div></div>
            <span class="progress-text">Step 5 of 5</span>
        </div>
        <div class="onboarding-card">
            <div class="onboarding-header">
                <div class="step-icon onboarding-svg-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 22c4 0 7-2.7 7-6.5 0-2.5-1.3-4.8-3.8-7.2.1 2-1 3.4-2.1 4.1.2-3.4-1.6-6.7-5.1-9.4.3 3.3-1.8 5.1-3.2 7.1C3.7 11.7 3 13.4 3 15.5 3 19.3 6.7 22 12 22Z"></path>
                        <path d="M9.5 18.5c0-1.7 1-3 2.5-4.5 1.5 1.5 2.5 2.8 2.5 4.5"></path>
                    </svg>
                </div>
                <h1>Your Daily Challenge</h1>
                <p class="mode-summary-pill"><?= $mode === 'easy' ? 'Easy mode' : 'Intermediate mode' ?></p>
                <p><?= h($modeBlurb) ?></p>
            </div>
            <div class="checklist-explainer">
                <?php foreach ($checklistItems as $item): ?>
                    <div class="checklist-item-card">
                        <div class="item-icon"><?= $itemIcons[$item['icon']] ?? '&#10003;' ?></div>
                        <div class="item-content">
                            <h3><?= h($item['name']) ?><?= !(int) $item['is_required'] ? ' <span class="optional-tag">optional</span>' : '' ?></h3>
                            <?php if ((int) $item['id'] === 1 && $user['daily_water_oz']): ?>
                                <p class="item-personal"><strong>Your water goal: <?= (int) $user['daily_water_oz'] ?> oz</strong></p>
                            <?php endif; ?>
                            <p><?= h($item['description']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" class="onboarding-form">
                <input type="hidden" name="csrf_token" value="<?= h($onboardingCsrfToken) ?>">
                <div class="onboarding-actions center">
                    <a href="/challenge/onboarding/step4.php" class="btn btn-secondary"><span class="btn-arrow">&larr;</span> Back</a>
                    <button type="submit" class="btn btn-primary btn-large">Start My Challenge!</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
