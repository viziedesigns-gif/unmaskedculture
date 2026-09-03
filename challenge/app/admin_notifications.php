<?php
/** Backward-compatible route for the moved super-admin push console. */
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
redirect('/challenge/app/admin/notifications.php');
