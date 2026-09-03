<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail_service.php';

$pageTitle = 'Reset Password';
$hideNav = true;
$token = strtolower(trim((string) ($_GET['token'] ?? $_POST['token'] ?? '')));
$error = '';
$success = '';
if (empty($_SESSION['password_reset_csrf'])) $_SESSION['password_reset_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['password_reset_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        [$ok, $message] = consumePasswordResetToken($token, $password);
        if ($ok) $success = $message; else $error = $message;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-standalone-container">
    <div class="auth-card">
        <div class="auth-header"><h1>Choose a new password</h1><p>Use at least eight characters with uppercase, lowercase, and a number.</p></div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
            <a class="btn btn-primary btn-block" href="/kinto#signin">Sign in</a>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <div class="form-group"><label for="password">New password</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
                <div class="form-group"><label for="confirm_password">Confirm password</label><input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password"></div>
                <button type="submit" class="btn btn-primary btn-block">Update password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
