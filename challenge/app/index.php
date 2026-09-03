<?php
/**
 * Kinto authentication controller.
 *
 * The public sign-in experience lives at /kinto. This endpoint remains the
 * POST target and legacy app entry so existing Android and web links continue
 * to work without exposing authentication logic in the marketing page.
 */

require_once __DIR__ . '/../includes/auth.php';

function kintoPublicLoginUrl(array $params = []): string {
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    return '/kinto' . ($params ? '?' . http_build_query($params) : '') . '#signin';
}

$requestInviteCode = normalizeCircleInviteCode($_GET['invite'] ?? $_POST['invite_code'] ?? '');
$requestInvite = null;
$inviteInvalid = false;
if ($requestInviteCode !== '') {
    $requestInvite = rememberCircleInvite($requestInviteCode);
    if (!$requestInvite) {
        $inviteInvalid = true;
        $requestInviteCode = '';
    }
}

$requestedReturn = (string) ($_GET['return'] ?? $_POST['return'] ?? '');
if (str_starts_with($requestedReturn, '/challenge/app/member_profile.php?')) {
    $_SESSION['post_auth_return'] = $requestedReturn;
} else {
    $requestedReturn = '';
}

if (isLoggedIn()) {
    clearFlash();
    if ($requestInvite) {
        redirect('/challenge/app/join.php?code=' . rawurlencode($requestInviteCode));
    }

    $user = getCurrentUser();
    if ($user && $user['onboarding_completed']) {
        if (!empty($_SESSION['post_auth_return'])) {
            $returnTo = (string) $_SESSION['post_auth_return'];
            unset($_SESSION['post_auth_return']);
            redirect($returnTo);
        }
        redirect('/challenge/app/dashboard.php');
    }
    redirect('/challenge/onboarding/step1.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        redirect(kintoPublicLoginUrl([
            'login' => 'missing',
            'invite' => $requestInviteCode,
            'return' => $requestedReturn,
        ]));
    }

    [$success, $result] = loginUser($email, $password);
    if (!$success) {
        redirect(kintoPublicLoginUrl([
            'login' => 'invalid',
            'invite' => $requestInviteCode,
            'return' => $requestedReturn,
        ]));
    }

    if (!empty($_SESSION['pending_invite_code']) && $result === 'dashboard') {
        redirect('/challenge/app/join.php?code=' . rawurlencode((string) $_SESSION['pending_invite_code']));
    }
    if (!empty($_SESSION['post_auth_return']) && $result === 'dashboard') {
        $returnTo = (string) $_SESSION['post_auth_return'];
        unset($_SESSION['post_auth_return']);
        redirect($returnTo);
    }
    if ($result === 'dashboard') {
        redirect('/challenge/app/dashboard.php');
    }
    redirect('/challenge/onboarding/step1.php');
}

$params = [
    'signin' => '1',
    'invite' => $requestInviteCode,
    'return' => $requestedReturn,
];

if ($inviteInvalid) {
    $params['notice'] = 'invite';
}

$sessionStatus = (string) ($_GET['session'] ?? '');
if (in_array($sessionStatus, ['expired', 'security_update'], true)) {
    $params['session'] = $sessionStatus;
}

redirect(kintoPublicLoginUrl($params));
