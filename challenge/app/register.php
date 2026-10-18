<?php
/**
 * Registration Page
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail_service.php';

$requestInviteCode = normalizeCircleInviteCode($_GET['invite'] ?? $_POST['invite_code'] ?? '');
$requestInvite = null;
$inviteError = '';
if ($requestInviteCode !== '') {
    $requestInvite = rememberCircleInvite($requestInviteCode);
    if (!$requestInvite) {
        $inviteError = 'This circle invite is invalid or has expired. Ask the circle owner for a new link.';
        $requestInviteCode = '';
    }
}

$requestedReturn = (string) ($_GET['return'] ?? '');
if (str_starts_with($requestedReturn, '/challenge/app/member_profile.php?')) {
    $_SESSION['post_auth_return'] = $requestedReturn;
}

// Redirect if already logged in
if (isLoggedIn()) {
    clearFlash();
    if ($requestInvite) {
        redirect('/challenge/app/join.php?code=' . rawurlencode($requestInviteCode));
    }
    redirect('/challenge/app/dashboard.php');
}

$error = $inviteError;
$success = '';

// Check for pending invite
$pendingCircleName = $_SESSION['pending_circle_name'] ?? null;

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        [$success, $result] = registerUser($email, $password);
        
        if ($success) {
            // Auto-login after registration
            $_SESSION['user_id'] = (int) $result;
            $_SESSION['auth_version'] = 1;
            $_SESSION['login_time'] = time();
            session_regenerate_id(true);
            rememberDeviceLogin((int) $result, 1);
            clearFlash();

            $welcomeDelivery = sendWelcomeEmail((int) $result);
            if (empty($welcomeDelivery['success'])) {
                error_log('Welcome email was not delivered for user ID ' . (int) $result);
            }
            
            redirect('/challenge/onboarding/step1.php' . ($requestInviteCode !== '' ? '?invite=' . rawurlencode($requestInviteCode) : ''));
        } else {
            $error = $result;
        }
    }
}

$pageTitle = 'Create Account';
$hideNav = true;
$bodyClass = 'auth-page';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card">
        <a class="app-auth-brand" href="/challenge/app/" aria-label="Kinto sign in"><img src="/challenge/assets/kinto-favicon/kinto-app-icon-192.png" alt="" width="44" height="44">Kinto</a>
        <div class="auth-header">
            <h1><?= $pendingCircleName ? 'Join your circle.' : 'Start with yourself.' ?></h1>
            <p class="auth-subtitle"><?= $pendingCircleName ? 'Create an account to join ' . h($pendingCircleName) . '.' : 'A small step toward a healthier daily rhythm.' ?></p>
        </div>
        <?php if ($error): ?><p class="alert alert-error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post" class="auth-form" id="registerForm">
            <?php if ($requestInviteCode !== ''): ?><input type="hidden" name="invite_code" value="<?= h($requestInviteCode) ?>"><?php endif; ?>
            <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required></div>
            <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required><p class="form-hint">At least 8 characters, with uppercase, lowercase, and a number.</p></div>
            <div class="form-group"><label for="confirm_password">Confirm password</label><input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
        <div class="auth-footer">Already have an account? <a href="/challenge/app/">Sign in</a></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
