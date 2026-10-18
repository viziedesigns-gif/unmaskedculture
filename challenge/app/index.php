<?php
/**
 * Kinto authentication controller.
 *
 * App entry and sign-in. Saved device credentials open the user's dashboard;
 * new devices see only the login form, without the public website shell.
 */

require_once __DIR__ . '/../includes/auth.php';

function kintoAppLoginUrl(array $params = []): string {
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    return '/challenge/app/' . ($params ? '?' . http_build_query($params) : '');
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
        redirect(kintoAppLoginUrl([
            'login' => 'missing',
            'invite' => $requestInviteCode,
            'return' => $requestedReturn,
        ]));
    }

    [$success, $result] = loginUser($email, $password);
    if (!$success) {
        redirect(kintoAppLoginUrl([
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

header('Cache-Control: no-cache, no-store, must-revalidate');
$loginError = match ((string) ($_GET['login'] ?? '')) {
    'missing' => 'Enter your email and password to sign in.',
    'invalid' => 'Invalid email or password. Please try again.',
    default => '',
};
$loginNotice = '';
if ($inviteInvalid || ($_GET['notice'] ?? '') === 'invite') {
    $loginNotice = 'This circle invite is invalid or has expired. Ask the circle owner for a new link.';
} elseif (in_array($_GET['session'] ?? '', ['expired', 'security_update'], true)) {
    $loginNotice = 'Please sign in again to keep your account secure.';
} elseif (($_GET['status'] ?? '') === 'logged-out') {
    $loginNotice = 'You have signed out of this device.';
} elseif (($_GET['status'] ?? '') === 'account-deleted') {
    $loginNotice = 'Your account has been deleted.';
}
$registerUrl = '/challenge/app/register.php' . ($requestInviteCode !== '' ? '?invite=' . rawurlencode($requestInviteCode) : '');
require __DIR__ . '/../includes/login_screen.php';
