<?php
/**
 * Onboarding Step 2 - Inner Circle
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
if ($user['onboarding_completed']) {
    redirect('/challenge/app/dashboard.php');
}

$error = '';
$pendingInviteCode = strtoupper(trim($_SESSION['pending_invite_code'] ?? ''));
$pendingCircle = null;

if ($pendingInviteCode !== '') {
    $pendingCircle = dbFetchOne(
        "SELECT ic.*, u.first_name, u.last_name
         FROM inner_circles ic
         JOIN users u ON ic.created_by = u.id
         WHERE ic.invite_code = ?",
        [$pendingInviteCode]
    );

    if (!$pendingCircle) {
        unset($_SESSION['pending_invite_code'], $_SESSION['pending_circle_name']);
        $pendingInviteCode = '';
    } else {
        $existingMember = dbFetchOne(
            "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
            [$pendingCircle['id'], getCurrentUserId()]
        );

        if ($existingMember) {
            unset($_SESSION['pending_invite_code'], $_SESSION['pending_circle_name']);
            redirect('/challenge/onboarding/step3.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'skip') {
        unset($_SESSION['pending_invite_code'], $_SESSION['pending_circle_name']);
        redirect('/challenge/onboarding/step3.php');
    } elseif ($action === 'continue_invite') {
        if ($pendingCircle) {
            redirect('/challenge/onboarding/step3.php');
        }
        $error = 'We could not find that invitation. Enter the invite code again or skip and ask the circle owner for a new link.';
    } elseif ($action === 'join') {
        $inviteCode = normalizeCircleInviteCode($_POST['invite_code'] ?? '');

        if (empty($inviteCode)) {
            $error = 'Please enter an invite code';
        } else {
            $circle = dbFetchOne(
                "SELECT ic.*, u.first_name, u.last_name
                 FROM inner_circles ic
                 JOIN users u ON ic.created_by = u.id
                 WHERE ic.invite_code = ?",
                [$inviteCode]
            );

            if (!$circle) {
                $error = 'Invalid invite code. Please check and try again.';
            } else {
                $existing = dbFetchOne(
                    "SELECT id FROM inner_circle_members WHERE circle_id = ? AND user_id = ?",
                    [$circle['id'], getCurrentUserId()]
                );

                if ($existing) {
                    $error = 'You are already a member of this circle';
                } else {
                    $_SESSION['pending_circle_join'] = [
                        'circle_id' => $circle['id'],
                        'inviter_id' => $circle['created_by'],
                        'invite_code' => $inviteCode,
                    ];
                    redirect('/challenge/onboarding/step3.php');
                }
            }
        }
    }
}

$pageTitle = 'Join a Circle';
$hideNav = true;
$bodyClass = 'onboarding-page';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(assetUrl('/challenge/assets/css/style.css')) ?>">
    <script src="<?= h(assetUrl('/challenge/assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= h($bodyClass) ?>">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NHSZ9V9D"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <div class="onboarding-container">
        <div class="onboarding-progress">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 40%"></div>
            </div>
            <span class="progress-text">Step 2 of 5</span>
        </div>

        <div class="onboarding-card">
            <div class="onboarding-header">
                <div class="step-icon onboarding-svg-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <?php if ($pendingCircle): ?>
                    <h1>You're Invited!</h1>
                    <p>You were invited to join <strong><?= h($pendingCircle['name']) ?></strong>. You'll be added automatically when you finish setup.</p>
                <?php else: ?>
                    <h1>Join Your Inner Circle</h1>
                    <p>Have an invite code from a friend? Enter it now, or skip and join later from Feed.</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if ($pendingCircle): ?>
                <div class="invite-benefits">
                    <h4>You'll join:</h4>
                    <ul>
                        <li><strong><?= h($pendingCircle['name']) ?></strong></li>
                        <li>Invited by <?= h(trim(($pendingCircle['first_name'] ?? '') . ' ' . ($pendingCircle['last_name'] ?? ''))) ?></li>
                        <li>Your invite link code is already applied</li>
                    </ul>
                </div>
                <form method="POST" class="onboarding-form">
                    <div class="onboarding-actions">
                        <a href="/challenge/onboarding/step1.php" class="btn btn-secondary"><span class="btn-arrow">&larr;</span> Back</a>
                        <div class="action-buttons">
                            <button type="submit" name="action" value="skip" class="btn btn-outline">Skip for Now</button>
                            <button type="submit" name="action" value="continue_invite" class="btn btn-primary">Continue <span class="btn-arrow">&rarr;</span></button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
            <form method="POST" class="onboarding-form" id="inviteForm">
                <div class="form-group">
                    <label for="invite_code">Invite Code</label>
                    <input type="text" id="invite_code" name="invite_code" value="<?= h($_POST['invite_code'] ?? '') ?>"
                           placeholder="Enter 8-character code" maxlength="8"
                           style="text-transform: uppercase; letter-spacing: 2px; text-align: center; font-size: 1.25rem;">
                </div>
                <div class="onboarding-actions">
                    <a href="/challenge/onboarding/step1.php" class="btn btn-secondary"><span class="btn-arrow">&larr;</span> Back</a>
                    <div class="action-buttons">
                        <button type="submit" name="action" value="skip" class="btn btn-outline">Skip for Now</button>
                        <button type="submit" name="action" value="join" class="btn btn-primary">Continue <span class="btn-arrow">&rarr;</span></button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const inviteCodeInput = document.getElementById('invite_code');
        if (inviteCodeInput) {
            inviteCodeInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.toUpperCase();
            });
        }
    </script>
</body>
</html>
