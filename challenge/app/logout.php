<?php
/**
 * Logout Handler
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';

logoutUser();
redirect('/challenge/app/?status=logged-out');
