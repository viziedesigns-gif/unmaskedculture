<?php
/**
 * Onboarding Step 4 - Challenge mode selection
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xp_service.php';
requireLogin();

$user = getCurrentUser();
if ($user['onboarding_completed']) {
    redirect('/challenge/app/dashboard.php');
}

ensureXpTablesAndColumns();
$error = '';
$selectedMode = normalizeChallengeMode($user['challenge_mode'] ?? 'easy');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawMode = trim((string) ($_POST['challenge_mode'] ?? ''));
    if (!in_array($rawMode, ['easy', 'intermediate'], true)) {
        $error = 'Please choose Easy or Intermediate to continue.';
        $selectedMode = 'easy';
    } else {
        if (updateUserProfile(getCurrentUserId(), ['challenge_mode' => $rawMode])) {
            redirect('/challenge/onboarding/step5.php');
        }
        $error = 'We could not save your challenge mode. Please try again.';
        $selectedMode = $rawMode;
    }
}

$pageTitle = 'Choose Your Mode';
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
            <div class="progress-bar"><div class="progress-fill" style="width: 80%"></div></div>
            <span class="progress-text">Step 4 of 5</span>
        </div>
        <div class="onboarding-card">
            <div class="onboarding-header">
                <div class="step-icon mode-step-icon"><i data-lucide="sparkles"></i></div>
                <h1>Choose your challenge mode</h1>
                <p>You can change this when you restart. Pick the pace that supports your mental health.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="onboarding-form mode-select-form" id="modeSelectForm">
                <div class="mode-cards">
                    <label class="mode-card <?= $selectedMode === 'easy' ? 'selected' : '' ?>">
                        <input type="radio" name="challenge_mode" value="easy" <?= $selectedMode === 'easy' ? 'checked' : '' ?> required>
                        <span class="mode-card-badge">Easy</span>
                        <h2>Show up</h2>
                        <p>Check at least one item to advance. We still track everything you skip &mdash; honesty without pressure.</p>
                        <ul>
                            <li>Day counts with 1+ items</li>
                            <li>Full 77-day journey</li>
                            <li>Earn Calm Points as you go</li>
                        </ul>
                    </label>
                    <label class="mode-card <?= $selectedMode === 'intermediate' ? 'selected' : '' ?>">
                        <input type="radio" name="challenge_mode" value="intermediate" <?= $selectedMode === 'intermediate' ? 'checked' : '' ?>>
                        <span class="mode-card-badge mode-card-badge--mid">Intermediate</span>
                        <h2>Full protocol</h2>
                        <p>Complete all daily items to advance &mdash; the full challenge discipline.</p>
                        <ul>
                            <li>All required items to count</li>
                            <li>Same streak &amp; repairs</li>
                            <li>Higher full-day Calm Point bonuses</li>
                        </ul>
                    </label>
                </div>
                <div class="onboarding-actions center">
                    <a href="/challenge/onboarding/step3.php" class="btn btn-secondary"><span class="btn-arrow">&larr;</span> Back</a>
                    <button type="submit" class="btn btn-primary btn-large">Continue</button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if (window.lucide) lucide.createIcons();
        document.querySelectorAll('.mode-card').forEach((card) => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.mode-card').forEach((c) => c.classList.remove('selected'));
                card.classList.add('selected');
                card.querySelector('input').checked = true;
            });
        });
    </script>
</body>
</html>
