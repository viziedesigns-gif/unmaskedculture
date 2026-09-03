<?php
/**
 * Copy this file to push.php and fill in VAPID keys before enabling push.
 * Generate keys after installing dependencies:
 * php vendor/bin/web-push generate:vapid
 */

define('PUSH_VAPID_SUBJECT', 'mailto:you@example.com');
define('PUSH_VAPID_PUBLIC_KEY', '');
define('PUSH_VAPID_PRIVATE_KEY', '');
