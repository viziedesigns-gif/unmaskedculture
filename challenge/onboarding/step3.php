<?php
/**
 * Onboarding Step 3 - Health & journal preferences
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
if ($user['onboarding_completed']) {
    redirect('/challenge/app/dashboard.php');
}

$error = '';
$heightFeet = floor(($user['height_inches'] ?? 66) / 12);
$heightInches = ($user['height_inches'] ?? 66) % 12;
$weightValue = (string) ($user['weight_lbs'] ?? '');
$journalPreference = !empty($user['journal_in_app']) ? 'in_app' : 'external';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'skip_health') {
        $journalInApp = isset($_POST['journal_preference']) && $_POST['journal_preference'] === 'in_app' ? 1 : 0;
        $journalPreference = $journalInApp ? 'in_app' : 'external';
        if (updateUserProfile(getCurrentUserId(), ['journal_in_app' => $journalInApp])) {
            redirect('/challenge/onboarding/step4.php');
        }
        $error = 'We could not save your journal preference. Please try again.';
    }

    $weightLbs = floatval($_POST['weight_lbs'] ?? 0);
    $heightFeetInput = intval($_POST['height_feet'] ?? 0);
    $heightInchesInput = intval($_POST['height_inches'] ?? 0);
    $totalInches = ($heightFeetInput * 12) + $heightInchesInput;
    $journalInApp = isset($_POST['journal_preference']) && $_POST['journal_preference'] === 'in_app' ? 1 : 0;
    $weightValue = (string) ($_POST['weight_lbs'] ?? '');
    $heightFeet = $heightFeetInput;
    $heightInches = $heightInchesInput;
    $journalPreference = $journalInApp ? 'in_app' : 'external';

    if ($action === 'skip_health') {
        // The failed preference save above already supplied the appropriate message.
    } elseif ($weightLbs < 50 || $weightLbs > 700) {
        $error = 'Please enter a valid weight between 50 and 700 lbs';
    } elseif ($totalInches < 36 || $totalInches > 96) {
        $error = 'Please enter a valid height';
    } else {
        $bmi = calculateBMI($weightLbs, $totalInches);
        $dailyWater = calculateDailyWater($weightLbs);
        $saved = updateUserProfile(getCurrentUserId(), [
            'weight_lbs' => $weightLbs,
            'height_inches' => $totalInches,
            'bmi' => $bmi,
            'daily_water_oz' => $dailyWater,
            'water_bottle_oz' => (int) ($user['water_bottle_oz'] ?? 24),
            'journal_in_app' => $journalInApp,
        ]);
        if ($saved) {
            redirect('/challenge/onboarding/step4.php');
        }
        $error = 'We could not save these preferences. Your entries are still here—please try again.';
    }
}

$pageTitle = 'Goals & Journal';
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
            <div class="progress-bar"><div class="progress-fill" style="width: 60%"></div></div>
            <span class="progress-text">Step 3 of 5</span>
        </div>
        <div class="onboarding-card">
            <div class="onboarding-header">
                <div class="step-icon onboarding-svg-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3 3v18h18"></path>
                        <path d="M7 16v-4M12 16V8M17 16V5"></path>
                    </svg>
                </div>
                <h1>Personalize Your Goals</h1>
                <p>We use your weight to calculate a daily water goal. You can update this anytime in Settings.</p>
            </div>
            <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
            <form method="POST" class="onboarding-form">
                <div class="form-group">
                    <label for="weight_lbs">Current Weight (lbs)</label>
                    <input type="number" id="weight_lbs" name="weight_lbs" value="<?= h($weightValue) ?>" min="50" max="700" step="0.1">
                </div>
                <div class="form-group">
                    <label>Height</label>
                    <div class="height-inputs">
                        <div class="input-with-unit"><input type="number" name="height_feet" value="<?= $heightFeet ?>" min="3" max="8"><span class="input-unit">ft</span></div>
                        <div class="input-with-unit"><input type="number" name="height_inches" value="<?= $heightInches ?>" min="0" max="11"><span class="input-unit">in</span></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Journal preference</label>
                    <div class="radio-options">
                        <label class="radio-option">
                            <input type="radio" name="journal_preference" value="in_app" <?= $journalPreference === 'in_app' ? 'checked' : '' ?>>
                            <span class="radio-label">Inside the app</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="journal_preference" value="external" <?= $journalPreference === 'external' ? 'checked' : '' ?>>
                            <span class="radio-label">Outside the app</span>
                        </label>
                    </div>
                </div>
                <div class="onboarding-actions">
                    <a href="/challenge/onboarding/step2.php" class="btn btn-secondary"><span class="btn-arrow">&larr;</span> Back</a>
                    <div class="action-buttons">
                        <button type="submit" name="action" value="skip_health" class="btn btn-outline">Skip Health Stats</button>
                        <button type="submit" name="action" value="save" class="btn btn-primary">Continue <span class="btn-arrow">&rarr;</span></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
