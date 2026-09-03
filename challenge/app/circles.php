<?php
/**
 * Redirect legacy circles route to Feed manage view.
 */

require_once __DIR__ . '/../includes/auth.php';

requireOnboarding();
redirect('/challenge/app/feed.php?manage=1');
