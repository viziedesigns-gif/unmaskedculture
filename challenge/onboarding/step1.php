<?php
/**
 * Onboarding Step 1 - Profile basics
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
if ($user['onboarding_completed']) {
    redirect('/challenge/app/dashboard.php');
}

$error = '';
$inviteWarning = '';
$timezones = getTimezoneList();
$profilePrompts = getProfilePromptOptions();
$inviteCodeFromUrl = normalizeCircleInviteCode($_GET['invite'] ?? '');
if ($inviteCodeFromUrl !== '' && !rememberCircleInvite($inviteCodeFromUrl)) {
    $inviteWarning = 'This circle invite is invalid or has expired. You can still finish setup and ask the circle owner for a new link.';
}

$draft = is_array($_SESSION['onboarding_profile_draft'] ?? null) ? $_SESSION['onboarding_profile_draft'] : [];
$formValues = array_merge([
    'first_name' => (string) ($user['first_name'] ?? ''),
    'last_name' => (string) ($user['last_name'] ?? ''),
    'dob' => (string) ($user['dob'] ?? ''),
    'timezone' => (string) ($user['timezone'] ?? DEFAULT_TIMEZONE),
    'profile_bio' => (string) ($user['profile_bio'] ?? ''),
    'profile_prompt_key' => (string) ($user['profile_prompt_key'] ?? 'motivation'),
    'profile_prompt_answer' => (string) ($user['profile_prompt_answer'] ?? ''),
    'profile_visible' => (int) ($user['profile_visible'] ?? 1),
], $draft);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = 'That upload was too large for the server. Choose a profile photo under 5 MB and try again.';
    } else {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $dob = trim((string) ($_POST['dob'] ?? ''));
        $timezone = (string) ($_POST['timezone'] ?? DEFAULT_TIMEZONE);
        $profileBio = trim((string) ($_POST['profile_bio'] ?? ''));
        $promptKey = (string) ($_POST['profile_prompt_key'] ?? 'motivation');
        $promptAnswer = trim((string) ($_POST['profile_prompt_answer'] ?? ''));
        $profileVisible = isset($_POST['profile_visible']) ? 1 : 0;

        $formValues = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'dob' => $dob,
            'timezone' => $timezone,
            'profile_bio' => $profileBio,
            'profile_prompt_key' => $promptKey,
            'profile_prompt_answer' => $promptAnswer,
            'profile_visible' => $profileVisible,
        ];
        $_SESSION['onboarding_profile_draft'] = $formValues;

        try {
            new DateTimeZone($timezone);
        } catch (Exception $e) {
            $error = 'Please select a valid timezone.';
        }

        if ($error === '' && ($firstName === '' || $lastName === '')) {
            $error = 'Please enter your first and last name.';
        } elseif ($error === '' && $dob === '') {
            $error = 'Please enter your date of birth.';
        } elseif ($error === '' && !array_key_exists($promptKey, $profilePrompts)) {
            $error = 'Please choose a valid profile prompt.';
        } elseif ($error === '' && strlen($profileBio) > 500) {
            $error = 'Bio must be 500 characters or less.';
        } elseif ($error === '' && strlen($promptAnswer) > 500) {
            $error = 'Prompt answer must be 500 characters or less.';
        } elseif ($error === '') {
            $dobDate = DateTime::createFromFormat('!Y-m-d', $dob);
            $dobErrors = DateTime::getLastErrors();
            if (!$dobDate || (is_array($dobErrors) && ($dobErrors['warning_count'] > 0 || $dobErrors['error_count'] > 0))) {
                $error = 'Please enter a valid date of birth.';
            } else {
                $today = new DateTime('today');
                $age = $today->diff($dobDate)->y;
                if ($dobDate > $today || $age > 120) {
                    $error = 'Please enter a valid date of birth.';
                } elseif ($age < 13) {
                    $error = 'You must be at least 13 years old to use this app.';
                }
            }
        }

        if ($error === '') {
            $updateData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'dob' => $dob,
                'age' => $age,
                'timezone' => $timezone,
                'profile_bio' => $profileBio,
                'profile_prompt_key' => $promptKey,
                'profile_prompt_answer' => $promptAnswer,
                'profile_visible' => $profileVisible,
            ];

            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$uploadSuccess, $result] = handleProfilePicUpload($_FILES['profile_pic'], getCurrentUserId());
                if ($uploadSuccess) {
                    $updateData['profile_pic'] = $result;
                } else {
                    $error = $result . ' Your other answers have been kept.';
                }
            }

            if ($error === '') {
                if (updateUserProfile(getCurrentUserId(), $updateData)) {
                    unset($_SESSION['onboarding_profile_draft']);
                    redirect('/challenge/onboarding/step2.php');
                }
                $error = 'We could not save your profile. Your answers are still here—please try again.';
            }
        }
    }
}

$pageTitle = 'About You';
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
            <div class="progress-bar"><div class="progress-fill" style="width: 20%"></div></div>
            <span class="progress-text">Step 1 of 5</span>
        </div>
        <div class="onboarding-card">
            <div class="onboarding-header">
                <div class="step-icon onboarding-svg-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="img" focusable="false">
                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <h1>Tell Us About You</h1>
                <p>Set up your profile and timezone so daily check-ins reset at midnight your time.</p>
            </div>
            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
            <?php if ($inviteWarning): ?><div class="alert alert-warning" role="status"><?= h($inviteWarning) ?></div><?php endif; ?>
            <?php if (!empty($_SESSION['pending_circle_name'])): ?><div class="alert alert-info">Your invite to <strong><?= h($_SESSION['pending_circle_name']) ?></strong> is saved. You will join after setup.</div><?php endif; ?>
            <form method="POST" class="onboarding-form" enctype="multipart/form-data" id="profileOnboardingForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?= h($formValues['first_name']) ?>" autocomplete="given-name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= h($formValues['last_name']) ?>" autocomplete="family-name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" value="<?= h($formValues['dob']) ?>" max="<?= date('Y-m-d', strtotime('-13 years')) ?>" autocomplete="bday" required>
                </div>
                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select" required>
                        <?php foreach ($timezones as $tz => $label): ?>
                            <option value="<?= h($tz) ?>" <?= $formValues['timezone'] === $tz ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="profile_pic">Profile Picture (optional)</label>
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small class="form-hint" id="profilePicHint">JPG, PNG, GIF, or WebP up to 5 MB.</small>
                </div>
                <details class="onboarding-details" <?= ($error !== '' || $formValues['profile_bio'] !== '' || $formValues['profile_prompt_answer'] !== '') ? 'open' : '' ?>>
                    <summary>Optional bio & prompt for your circle</summary>
                    <div class="form-group">
                        <label for="profile_bio">Bio</label>
                        <textarea id="profile_bio" name="profile_bio" rows="2" maxlength="500" class="form-textarea"><?= h($formValues['profile_bio']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="profile_prompt_key">Profile Prompt</label>
                        <select id="profile_prompt_key" name="profile_prompt_key" class="form-select">
                            <?php foreach ($profilePrompts as $key => $question): ?>
                                <option value="<?= h($key) ?>" <?= $formValues['profile_prompt_key'] === $key ? 'selected' : '' ?>><?= h($question) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="profile_prompt_answer">Your Answer</label>
                        <textarea id="profile_prompt_answer" name="profile_prompt_answer" rows="2" maxlength="500" class="form-textarea"><?= h($formValues['profile_prompt_answer']) ?></textarea>
                    </div>
                    <label class="checkbox-large profile-visible-choice">
                        <input type="checkbox" name="profile_visible" value="1" <?= $formValues['profile_visible'] ? 'checked' : '' ?>>
                        <span class="checkbox-label">Show my bio and prompt on my profile</span>
                    </label>
                </details>
                <div class="onboarding-actions">
                    <button type="submit" class="btn btn-primary btn-block" id="profileContinueButton">Save &amp; Continue <span class="btn-arrow">&rarr;</span></button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (() => {
            const form = document.getElementById('profileOnboardingForm');
            const file = document.getElementById('profile_pic');
            const hint = document.getElementById('profilePicHint');
            const button = document.getElementById('profileContinueButton');
            if (file) file.addEventListener('change', () => {
                const selected = file.files && file.files[0];
                if (selected && selected.size > 5 * 1024 * 1024) {
                    file.value = '';
                    hint.textContent = 'That photo is over 5 MB. Choose a smaller photo; your other answers are unchanged.';
                    hint.style.color = 'var(--danger, #b91c1c)';
                    file.focus();
                } else {
                    hint.textContent = 'JPG, PNG, GIF, or WebP up to 5 MB.';
                    hint.style.color = '';
                }
            });
            if (form) form.addEventListener('submit', () => {
                button.disabled = true;
                button.textContent = 'Saving…';
            });
        })();
    </script>
</body>
</html>
