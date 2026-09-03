<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail_service.php';

if (isLoggedIn()) redirect('/challenge/app/dashboard.php');
$pageTitle = 'Forgot Password';
$hideNav = true;
$error = '';
$sent = false;
if (empty($_SESSION['password_reset_csrf'])) $_SESSION['password_reset_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['password_reset_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (!isValidEmail($email)) {
        $error = 'Enter a valid email address.';
    } else {
        if (!publicResetRateLimited($email)) {
            recordPublicResetRequest($email);
            $user = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($user) createAndSendPasswordReset((int) $user['id']);
        }
        $sent = true;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-standalone-container">
    <div class="auth-card">
        <div class="auth-header"><h1>Reset your password</h1><p>We’ll email a secure reset link if the account exists.</p></div>
        <?php if ($sent): ?>
            <div class="alert alert-success">If an account matches that email, a reset link has been sent.</div>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" required autocomplete="email"></div>
                <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
            </form>
        <?php endif; ?>
        <div class="auth-footer"><a href="/kinto#signin">Back to sign in</a></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
