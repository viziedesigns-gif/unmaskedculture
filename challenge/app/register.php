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
$bodyClass = 'auth-page auth-landing-page';
?>
<!DOCTYPE html>
<html lang="en" class="auth-landing-page">
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
    <meta name="application-name" content="Kinto">
    <meta name="theme-color" content="#F5F0E8">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Kinto">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(assetUrl('/challenge/assets/css/style.css')) ?>">
    <script src="<?= h(assetUrl('/challenge/assets/js/app.js')) ?>" defer></script>
</head>
<body class="auth-page auth-landing-page">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NHSZ9V9D"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="/kinto" class="navbar-logo">
                <img class="kinto-header-icon" src="<?= h(faviconUrl('kinto-app-icon-192.png')) ?>" alt="" aria-hidden="true">
                <span class="logo-kinto">Kinto</span>
            </a>

            <button class="menu-toggle" id="mobile-menu" aria-label="Toggle navigation menu" type="button">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>

            <ul class="nav-menu" id="nav-menu">
                <li class="nav-item"><a href="/" class="nav-link">Home</a></li>
                <li class="nav-item"><a href="/about" class="nav-link">About</a></li>
                <li class="nav-item"><a href="/team" class="nav-link">Team</a></li>
                <li class="nav-item dropdown">
                    <a href="javascript:void(0);" class="nav-link dropdown-toggle">Get Involved <i class="dropdown-icon"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="/volunteer">Volunteer</a></li>
                        <li><a href="/help">Help</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a href="javascript:void(0);" class="nav-link dropdown-toggle">Media <i class="dropdown-icon"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="/podcast">Podcast</a></li>
                        <li><a href="/cinema">Cinema</a></li>
                        </ul>
                </li>
                <li class="nav-item dropdown">
                    <a href="javascript:void(0);" class="nav-link dropdown-toggle active">Programs <i class="dropdown-icon"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="/visioncenter">Vision Center</a></li>
                        <li><a href="/kinto" class="active">Kinto</a></li>
                        </ul>
                </li>
                <li class="nav-item"><a href="/contact" class="nav-link">Contact</a></li>
                <li class="nav-item"><a href="/donate" class="donate-btn">Donate</a></li>
            </ul>
        </div>
    </nav>

        <main class="auth-hero">
        <div class="auth-hero-grid">
            <section class="auth-hero-copy" aria-labelledby="register-title">
                <p class="auth-eyebrow">Start the rhythm</p>
                <h1 id="register-title">77 days. One daily rhythm. <span>No perfection required.</span></h1>
                <p>Create your Kinto account to choose your pace and begin a daily wellness rhythm created by Unmasked Culture.</p>

                <div class="auth-hero-stats" aria-label="Challenge highlights">
                    <div><strong>77</strong><span>days</span></div>
                    <div><strong>4</strong><span>focus areas</span></div>
                    <div><strong>1</strong><span>daily check-in</span></div>
                </div>
            </section>

            <div class="auth-container">

        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <span class="logo-flame">77</span>
                </div>
                <?php if ($pendingCircleName): ?>
                    <h2>Join "<?= h($pendingCircleName) ?>"</h2>
                    <p class="auth-subtitle">Create an account to join the circle.</p>
                <?php else: ?>
                    <h2>Create account.</h2>
                    <p class="auth-subtitle">Create your account and start your daily rhythm.</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" id="registerForm">
                <?php if ($requestInviteCode !== ''): ?><input type="hidden" name="invite_code" value="<?= h($requestInviteCode) ?>"><?php endif; ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= h($_POST['email'] ?? '') ?>"
                        placeholder="your@email.com"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password"
                            placeholder="Create a strong password"
                            required
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <span class="eye-icon">Show</span>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <small>At least 8 characters, including uppercase, lowercase, and a number</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password"
                            placeholder="Confirm your password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <span class="eye-icon">Show</span>
                        </button>
                    </div>
                    <div id="passwordMatch" class="password-match-indicator"></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Create Account
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="/kinto#signin">Sign in</a></p>
            </div>
        </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-top">
                <div>
                    <a href="/kinto" class="footer-logo">
                        <span class="logo-kinto">Kinto</span>
                    </a>
                    <p class="footer-tagline">Kinto is a wellness app created by Unmasked Culture Foundation.</p>
                </div>

                <div class="footer-nav">
                    <div>
                        <h3>About</h3>
                        <ul>
                            <li><a href="/">Home</a></li>
                            <li><a href="/about">About</a></li>
                            <li><a href="/team">Team</a></li>
                            <li><a href="/contact">Contact</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3>Community</h3>
                        <ul>
                            <li><a href="/volunteer">Volunteer</a></li>
                            <li><a href="/help">Help</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3>Resources</h3>
                        <ul>
                            <li><a href="/podcast">Podcast</a></li>
                            <li><a href="/cinema">Cinema</a></li>
                            </ul>
                    </div>

                    <div>
                        <h3>Programs</h3>
                        <ul>
                            <li><a href="/visioncenter">Vision Center</a></li>
                            <li><a href="/kinto">Kinto</a></li>
                            </ul>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2026 Unmasked Culture Foundation. Kinto is an Unmasked Culture app.</p>
                <div class="footer-links">
                    <a href="/privacy">Privacy Policy</a>
                    <a href="/terms">Terms of Service</a>
                    <a href="/accessibility">Accessibility</a>
                </div>
            </div>
        </div>
    </footer>

        <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
        }

        // Password match indicator
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const matchIndicator = document.getElementById('passwordMatch');

        function checkPasswordMatch() {
            if (confirmPassword.value === '') {
                matchIndicator.textContent = '';
                matchIndicator.className = 'password-match-indicator';
            } else if (password.value === confirmPassword.value) {
                matchIndicator.textContent = 'Passwords match';
                matchIndicator.className = 'password-match-indicator match';
            } else {
                matchIndicator.textContent = 'Passwords do not match';
                matchIndicator.className = 'password-match-indicator no-match';
            }
        }

        password.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
    </script>
    <script src="/challenge/assets/js/site-shell.js?v=77.3"></script>
</body>
</html>
