<?php
/** Public image delivery from durable and legacy upload locations. */
require_once __DIR__ . '/includes/functions.php';
$file = $_GET['file'] ?? '';
if (!is_string($file) || $file !== basename($file)) {
    http_response_code(404);
    exit;
}
$path = profilePicFilesystemPath($file);
if (!$path) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($path));
readfile($path);
