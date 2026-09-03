<?php
/**
 * Header Template
 * Kinto App
 */

require_once __DIR__ . '/auth.php';

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$pageTitle = $pageTitle ?? 'Kinto';
$bodyClass = $bodyClass ?? '';
$hideNav = $hideNav ?? false;
$showBottomNav = !$hideNav && isLoggedIn() && $currentUser && ($currentUser['onboarding_completed'] ?? false);
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="application-name" content="Kinto">
    <meta name="description" content="Kinto — Heal. Grow. Become. Daily check-ins, journaling, insights, and community support.">
    <meta name="theme-color" content="#F5F0E8">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Kinto">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?= h($pageTitle) ?> | Kinto</title>
    <?php include __DIR__ . '/favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(assetUrl('/challenge/assets/css/style.css')) ?>">
    <script>
        try {
            if (!sessionStorage.getItem('kintoSplashSeen')) {
                document.documentElement.classList.add('splash-pending');
            }
        } catch (error) {
            document.documentElement.classList.add('splash-pending');
        }
        window.setTimeout(function () {
            document.documentElement.classList.remove('splash-pending');
            document.getElementById('app-splash')?.remove();
        }, 4500);
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= h(assetUrl('/challenge/assets/js/app.js')) ?>" defer></script>
    <script src="<?= h(assetUrl('/challenge/assets/js/podcast-player.js')) ?>" defer></script>
</head>
<body class="<?= h($bodyClass) ?> <?= $showBottomNav ? 'has-bottom-nav' : '' ?>">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NHSZ9V9D"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <div class="app-splash" id="app-splash" role="status" aria-label="Loading Kinto">
        <canvas class="app-splash__water" id="app-splash-water" aria-hidden="true"></canvas>
        <div class="app-splash__brand">
            <img class="app-splash__logo" src="<?= h(faviconUrl('kinto-app-icon-512.png')) ?>" alt="" aria-hidden="true">
            <span class="app-splash__wordmark">Kinto</span>
            <span class="app-splash__tagline">Heal. Grow. Become.</span>
        </div>
    </div>
    <?php if ($showBottomNav): ?>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <a href="/challenge/app/dashboard.php" class="mobile-brand" aria-label="Kinto home">
            <img class="mobile-brand__icon" src="<?= h(faviconUrl('kinto-app-icon-192.png')) ?>" alt="" aria-hidden="true">
            <span class="mobile-brand__text">
                <span class="mobile-brand__name">Kinto</span>
                <span class="mobile-brand__tagline">Heal. Grow. Become.</span>
            </span>
        </a>
        <a href="/challenge/app/profile.php" class="mobile-header__settings" aria-label="Settings">
            <i data-lucide="settings"></i>
        </a>
    </header>
    <?php endif; ?>

    <?php
    // Display flash messages
    $flash = getFlash();
    if ($flash):
    ?>
    <div class="flash-message flash-<?= h($flash['type']) ?>">
        <span class="flash-text"><?= h($flash['message']) ?></span>
        <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <main class="main-content">
