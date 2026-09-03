<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/jar_service.php';

header('Content-Type: application/json');
if (!isLoggedIn()) jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!validJarCsrf($input['csrf_token'] ?? null)) {
    jsonResponse(['success' => false, 'error' => 'Your session expired. Refresh and try again.'], 403);
}
$entryId = (int) ($input['entry_id'] ?? 0);
if ($entryId < 1) jsonResponse(['success' => false, 'error' => 'Invalid Jar entry.'], 422);

try {
    if (!deleteJarEntry($entryId, (int) getCurrentUserId())) {
        jsonResponse(['success' => false, 'error' => 'Jar entry not found.'], 404);
    }
    jsonResponse(['success' => true]);
} catch (Throwable $e) {
    error_log('Jar delete failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Unable to remove this entry right now.'], 500);
}
