<?php
/**
 * Compatibility redirect for old circle chat links.
 * Feed is the single maintained chat surface.
 */

require_once __DIR__ . '/../includes/auth.php';

requireOnboarding();

$circleId = (int) ($_GET['circle'] ?? 0);
redirect('/challenge/app/feed.php' . ($circleId > 0 ? '?circle=' . $circleId : ''));
