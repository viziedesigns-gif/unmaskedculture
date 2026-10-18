<?php
/** Login-only app view, rendered by the authentication controller. */
if (!defined('SESSION_NAME')) { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F5F0E8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Kinto">
    <title>Sign in | Kinto</title>
    <?php include __DIR__ . '/favicon.php'; ?>
    <link rel="preload" href="/challenge/assets/fonts/manrope-variable.ttf" as="font" type="font/ttf" crossorigin>
    <style>
        * { box-sizing: border-box; }
        html { background: #f5f0e8; color: #1a1714; }
        body { margin: 0; font: 16px/1.5 system-ui, sans-serif; }
        main { min-height: 100vh; min-height: 100svh; display: grid; align-items: center; padding: max(28px, env(safe-area-inset-top)) max(24px, env(safe-area-inset-right)) max(28px, env(safe-area-inset-bottom)) max(24px, env(safe-area-inset-left)); }
        .login { width: 100%; max-width: 380px; margin: auto; }
        .brand { text-align: center; margin-bottom: 36px; }
        .brand img { width: 80px; height: 80px; border-radius: 22px; }
        .wordmark { display: block; font: 48px/1.2 Georgia, serif; margin-top: 10px; }
        .tagline, .remember { color: #655d52; font-size: 14px; }
        h1 { font-size: 25px; font-weight: 600; margin: 0 0 24px; text-align: center; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input { width: 100%; min-height: 54px; padding: 14px 16px; border: 1px solid #bdb3a5; border-radius: 14px; background: #fffcf8; color: #1a1714; font: inherit; }
        input:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid #8a702f; outline-offset: 3px; }
        .field { margin-bottom: 20px; }
        .password { position: relative; }
        .password input { padding-right: 74px; }
        .toggle { position: absolute; right: 4px; top: 4px; min-height: 46px; padding: 0 12px; border: 0; border-radius: 10px; background: transparent; font: 600 14px system-ui, sans-serif; color: #65522c; cursor: pointer; }
        a { color: #665128; text-underline-offset: 3px; }
        .reset { display: block; width: fit-content; margin: -4px 0 24px auto; font-size: 14px; }
        .submit { width: 100%; min-height: 54px; border: 0; border-radius: 16px; background: #1a1714; color: #fffcf8; font: 600 17px system-ui, sans-serif; cursor: pointer; }
        .submit:disabled { opacity: .65; cursor: wait; }
        .remember, .signup { text-align: center; }
        .remember { margin: 14px 0 30px; }
        .signup { margin: 0; font-size: 15px; }
        .signup a { font-weight: 600; }
        .notice { padding: 12px 16px; border-radius: 12px; background: #e9e0ce; margin-bottom: 20px; }
        .error { background: #f9e5e0; color: #832d20; }
    </style>
    <link rel="stylesheet" href="<?= h(assetUrl('/challenge/assets/css/app-ui.css')) ?>">
    <style>
        body.kinto-app { font-family: var(--font-sans); }
        .login h1 { font-size: 26px; font-weight: 750; }
        .brand { margin-bottom: 30px; }
        .brand img { width: 64px; height: 64px; border-radius: 19px; }
        .wordmark { font-size: 42px; }
        .tagline { font-size: 12px; }
        .submit { background: var(--primary); font-family: var(--font-sans); }
        .toggle { font-family: var(--font-sans); }
        .remember { font-size: 12px; }
    </style>
    <script src="<?= h(assetUrl('/challenge/assets/js/reminders.js')) ?>" defer></script>
</head>
<body class="kinto-app" data-kinto-signed-in="0">
<?php include __DIR__ . '/reminder_context.php'; ?>
<main>
    <section class="login" aria-labelledby="signin-title">
        <div class="brand">
            <img src="/challenge/assets/kinto-favicon/kinto-app-icon-192.png" alt="" width="80" height="80">
            <span class="wordmark">Kinto</span>
            <span class="tagline">Heal. Grow. Become.</span>
        </div>
        <h1 id="signin-title">Welcome back</h1>
        <?php if ($loginError): ?><p class="notice error" role="alert"><?= h($loginError) ?></p><?php endif; ?>
        <?php if ($loginNotice): ?><p class="notice" role="status"><?= h($loginNotice) ?></p><?php endif; ?>
        <?php if ($requestInvite): ?><p class="notice">Sign in to join <?= h($_SESSION['pending_circle_name'] ?? 'your circle') ?>.</p><?php endif; ?>
        <form method="post" action="/challenge/app/" id="login-form">
            <?php if ($requestInviteCode !== ''): ?><input type="hidden" name="invite_code" value="<?= h($requestInviteCode) ?>"><?php endif; ?>
            <?php if ($requestedReturn !== ''): ?><input type="hidden" name="return" value="<?= h($requestedReturn) ?>"><?php endif; ?>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="toggle" type="button" aria-label="Show password" aria-controls="password" aria-pressed="false" id="password-toggle">Show</button>
                </div>
            </div>
            <a class="reset" href="/challenge/app/forgot_password.php">Forgot password?</a>
            <button class="submit" type="submit">Sign in</button>
            <p class="remember">Your sign-in is remembered on this device.</p>
        </form>
        <p class="signup">New to Kinto? <a href="<?= h($registerUrl) ?>">Create an account</a></p>
    </section>
</main>
<script>
    document.getElementById('password-toggle').addEventListener('click', function () {
        const input = document.getElementById('password');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.textContent = show ? 'Hide' : 'Show';
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        this.setAttribute('aria-pressed', String(show));
    });
    document.getElementById('login-form').addEventListener('submit', function () {
        const button = this.querySelector('[type="submit"]');
        button.disabled = true;
        button.textContent = 'Signing in…';
    });
    window.addEventListener('pageshow', function () {
        const button = document.querySelector('[type="submit"]');
        button.disabled = false;
        button.textContent = 'Sign in';
    });
</script>
</body>
</html>
