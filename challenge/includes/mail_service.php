<?php
/** SMTP delivery and secure app-native password reset helpers. */

require_once __DIR__ . '/admin_service.php';

$mailConfig = __DIR__ . '/../config/mail.php';
if (is_file($mailConfig)) require_once $mailConfig;

foreach ([
    'APP_SMTP_HOST' => '',
    'APP_SMTP_PORT' => 587,
    'APP_SMTP_ENCRYPTION' => 'tls',
    'APP_SMTP_USERNAME' => 'unfiltered@unmaskedculture.org',
    'APP_SMTP_PASSWORD' => '',
    'APP_SMTP_FROM_EMAIL' => 'unfiltered@unmaskedculture.org',
    'APP_SMTP_FROM_NAME' => 'Kinto',
] as $constant => $default) {
    if (!defined($constant)) define($constant, $default);
}

function getMailConfigStatus(): array {
    $library = __DIR__ . '/../Email/phpmailer/src/PHPMailer.php';
    return [
        'configured' => APP_SMTP_HOST !== '' && APP_SMTP_PASSWORD !== '' && is_file($library),
        'host_ready' => APP_SMTP_HOST !== '',
        'credentials_ready' => APP_SMTP_USERNAME !== '' && APP_SMTP_PASSWORD !== '',
        'library_ready' => is_file($library),
        'from_email' => APP_SMTP_FROM_EMAIL,
    ];
}

function sendAppEmail(string $to, string $subject, string $html, string $plainText, array $cc = []): array {
    $status = getMailConfigStatus();
    if (!$status['configured']) return ['success' => false, 'error' => 'SMTP is not configured.'];

    require_once __DIR__ . '/../Email/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../Email/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../Email/phpmailer/src/SMTP.php';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = APP_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = APP_SMTP_USERNAME;
        $mail->Password = APP_SMTP_PASSWORD;
        $mail->Port = (int) APP_SMTP_PORT;
        $mail->SMTPSecure = APP_SMTP_ENCRYPTION;
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(APP_SMTP_FROM_EMAIL, APP_SMTP_FROM_NAME);
        $mail->addAddress($to);
        foreach ($cc as $ccAddress) {
            if (isValidEmail((string) $ccAddress)) $mail->addCC((string) $ccAddress);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plainText;
        $mail->send();
        return ['success' => true];
    } catch (Throwable $e) {
        error_log('App SMTP delivery failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Email delivery failed.'];
    }
}

function passwordResetEmailHtml(string $name, string $resetUrl): string {
    $safeName = h($name ?: 'there');
    $safeUrl = h($resetUrl);
    return '<!doctype html><html><body style="margin:0;background:#F5F0E8;font-family:Arial,sans-serif;color:#1A1714">'
        . '<div style="max-width:580px;margin:0 auto;padding:30px 16px"><div style="background:#fff;border-radius:16px;padding:30px;border:1px solid #E5D9C8">'
        . '<h1 style="margin:0 0 16px;font-size:24px">Reset your password</h1>'
        . '<p>Hi ' . $safeName . ',</p><p>A password reset was requested for your Kinto account.</p>'
        . '<p style="margin:26px 0"><a href="' . $safeUrl . '" style="background:#1A1714;color:#fff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:bold">Choose a new password</a></p>'
        . '<p style="font-size:13px;color:#64748b">This link expires in 60 minutes and can only be used once. If you did not request it, you can ignore this email.</p>'
        . '</div></div></body></html>';
}

function welcomeEmailHtml(string $installUrl): string {
    $safeInstallUrl = h($installUrl);
    return '<!doctype html><html><body style="margin:0;background:#F5F0E8;font-family:Arial,sans-serif;color:#1A1714">'
        . '<div style="max-width:600px;margin:0 auto;padding:30px 16px"><div style="background:#fff;border-radius:16px;padding:30px;border:1px solid #E5D9C8">'
        . '<p style="margin:0 0 8px;color:#C4A35A;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:.08em">Kinto</p>'
        . '<h1 style="margin:0 0 16px;font-size:26px">Welcome to your 77-day journey</h1>'
        . '<p>Your account is ready. Complete your setup, begin today\'s checklist, and connect with your circle when you are ready.</p>'
        . '<h2 style="margin:26px 0 8px;font-size:18px">Install on Android</h2>'
        . '<ol style="padding-left:20px;line-height:1.7"><li>Open the app in Chrome.</li><li>Open <a href="' . $safeInstallUrl . '">Download App</a> and tap <strong>Download App</strong>.</li><li>If no prompt appears, open the Chrome menu and choose <strong>Install app</strong> or <strong>Add to Home screen</strong>.</li></ol>'
        . '<h2 style="margin:26px 0 8px;font-size:18px">Install on iPhone or iPad</h2>'
        . '<ol style="padding-left:20px;line-height:1.7"><li>Open the app in Safari.</li><li>Tap the Share button.</li><li>Choose <strong>Add to Home Screen</strong>, then tap <strong>Add</strong>.</li></ol>'
        . '<p style="margin:26px 0"><a href="' . $safeInstallUrl . '" style="background:#1A1714;color:#fff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:bold">Open Download App</a></p>'
        . '<p style="font-size:13px;color:#64748b">You can also use the challenge directly in your browser at any time.</p>'
        . '</div></div></body></html>';
}

function sendWelcomeEmail(int $userId): array {
    $user = dbFetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
    if (!$user) return ['success' => false, 'error' => 'User not found.'];
    $installUrl = SITE_URL . '/challenge/app/settings/install.php';
    $plain = "Welcome to Kinto!\n\n"
        . "Android: open the app in Chrome, visit $installUrl, and tap Download App. If needed, use Chrome's Install app or Add to Home screen menu.\n\n"
        . "iPhone/iPad: open the app in Safari, tap Share, choose Add to Home Screen, then tap Add.";
    return sendAppEmail((string) $user['email'], 'Welcome to Kinto', welcomeEmailHtml($installUrl), $plain);
}

function weMissYouEmailHtml(string $name, string $returnUrl): string {
    $safeName = h($name ?: 'there');
    $safeUrl = h($returnUrl);
    return '<!doctype html><html><body style="margin:0;background:#F5F0E8;font-family:Arial,sans-serif;color:#1A1714">'
        . '<div style="max-width:600px;margin:0 auto;padding:32px 16px">'
        . '<div style="background:#0b1514;border-radius:18px 18px 0 0;padding:30px;color:#fff">'
        . '<p style="margin:0 0 8px;color:#C4A35A;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:.12em">Kinto</p>'
        . '<h1 style="margin:0;font-size:30px;line-height:1.15">We miss you.</h1></div>'
        . '<div style="background:#fff;border:1px solid #E5D9C8;border-top:0;border-radius:0 0 18px 18px;padding:30px">'
        . '<p style="font-size:17px">Hi ' . $safeName . ',</p>'
        . '<p style="line-height:1.7">It has been a while since your last check-in. There is no guilt and no need to catch up - your next healthy rhythm can begin with one small step today.</p>'
        . '<p style="line-height:1.7">Your checklist, journal, insights, and circle are ready whenever you are.</p>'
        . '<p style="margin:28px 0"><a href="' . $safeUrl . '" style="display:inline-block;background:#1A1714;color:#fff;text-decoration:none;padding:14px 22px;border-radius:10px;font-weight:bold">Return to the challenge</a></p>'
        . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6">You received this account email because you created a Kinto account.</p>'
        . '</div></div></body></html>';
}

function sendWeMissYouEmail(int $userId): array {
    $user = dbFetchOne("SELECT email,first_name FROM users WHERE id = ?", [$userId]);
    if (!$user) return ['success' => false, 'error' => 'User not found.'];
    $returnUrl = SITE_URL . '/kinto#signin';
    $plain = "Hi " . (($user['first_name'] ?? '') ?: 'there') . ",\n\n"
        . "We miss you. There is no guilt and no need to catch up - your next healthy rhythm can begin with one small step today.\n\n"
        . "Return to the challenge: $returnUrl";
    return sendAppEmail(
        (string) $user['email'],
        'We miss you at Kinto',
        weMissYouEmailHtml((string) ($user['first_name'] ?? ''), $returnUrl),
        $plain,
        [APP_SMTP_FROM_EMAIL]
    );
}

function createAndSendPasswordReset(int $userId, ?int $adminUserId = null): array {
    ensureAdminInfrastructure();
    $user = dbFetchOne("SELECT id, email, first_name FROM users WHERE id = ?", [$userId]);
    if (!$user) return ['success' => false, 'error' => 'User not found.'];

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    try {
        dbQuery("UPDATE password_reset_tokens SET used_at_utc = UTC_TIMESTAMP() WHERE user_id = ? AND used_at_utc IS NULL", [$userId]);
        dbQuery(
            "INSERT INTO password_reset_tokens
                (user_id, token_hash, created_by_admin_id, requested_ip_hash, expires_at_utc, created_at_utc)
             VALUES (?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 60 MINUTE), UTC_TIMESTAMP())",
            [$userId, $tokenHash, $adminUserId, requestIpHash()]
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Password reset token creation failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Unable to create a password reset.'];
    }

    $resetUrl = SITE_URL . '/challenge/app/reset_password.php?token=' . rawurlencode($rawToken);
    $plain = "Reset your Kinto password: $resetUrl\n\nThis link expires in 60 minutes and can only be used once.";
    $delivery = sendAppEmail((string) $user['email'], 'Reset your Kinto password', passwordResetEmailHtml((string) $user['first_name'], $resetUrl), $plain);

    if ($adminUserId) {
        auditAdminAction($adminUserId, 'password_reset.sent', $userId, ['delivered' => !empty($delivery['success'])]);
    }
    return ['success' => !empty($delivery['success']), 'error' => $delivery['error'] ?? '', 'reset_url' => $resetUrl];
}

function publicResetRateLimited(string $email): bool {
    ensureAdminInfrastructure();
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c FROM password_reset_requests
         WHERE (email_hash = ? OR ip_hash = ?)
           AND created_at_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)",
        [hash('sha256', strtolower(trim($email))), requestIpHash()]
    );
    return (int) ($row['c'] ?? 0) >= 5;
}

function recordPublicResetRequest(string $email): void {
    ensureAdminInfrastructure();
    dbQuery(
        "INSERT INTO password_reset_requests (email_hash, ip_hash, created_at_utc) VALUES (?, ?, UTC_TIMESTAMP())",
        [hash('sha256', strtolower(trim($email))), requestIpHash()]
    );
}

function consumePasswordResetToken(string $rawToken, string $newPassword): array {
    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) return [false, 'This reset link is invalid or expired.'];
    [$valid, $error] = validatePassword($newPassword);
    if (!$valid) return [false, $error];

    $tokenHash = hash('sha256', $rawToken);
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    try {
        $token = dbFetchOne(
            "SELECT id, user_id FROM password_reset_tokens
             WHERE token_hash = ? AND used_at_utc IS NULL AND expires_at_utc > UTC_TIMESTAMP()
             FOR UPDATE",
            [$tokenHash]
        );
        if (!$token) {
            $pdo->rollBack();
            return [false, 'This reset link is invalid or expired.'];
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        dbQuery("UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?", [$newHash, (int) $token['user_id']]);
        dbQuery("UPDATE password_reset_tokens SET used_at_utc = UTC_TIMESTAMP() WHERE user_id = ? AND used_at_utc IS NULL", [(int) $token['user_id']]);
        $pdo->commit();
        return [true, 'Password updated. You can sign in now.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Password reset consumption failed: ' . $e->getMessage());
        return [false, 'Unable to reset the password right now.'];
    }
}
