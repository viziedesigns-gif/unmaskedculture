<?php
/**
 * Favicon and PWA manifest head tags.
 */
?>
    <link rel="manifest" href="<?= h(assetUrl('/challenge/manifest.php')) ?>">
    <link rel="icon" href="<?= h(faviconUrl('kinto-favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= h(faviconUrl('kinto-favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="96x96" href="<?= h(faviconUrl('kinto-favicon-96.png')) ?>">
    <link rel="apple-touch-icon" href="<?= h(faviconUrl('kinto-apple-touch-icon.png')) ?>">
