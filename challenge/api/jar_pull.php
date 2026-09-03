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

$ownerId = (int) getCurrentUserId();
try {
    $entry = pullRandomJarEntry($ownerId);
    if (!$entry) jsonResponse(['success' => false, 'empty' => true, 'error' => 'Your Jar is waiting for its first note.'], 404);
    $user = getCurrentUser();
    jsonResponse([
        'success' => true,
        'entry' => jarEntryPayload($entry, $ownerId, (string) ($user['timezone'] ?? DEFAULT_TIMEZONE)),
    ]);
} catch (Throwable $e) {
    error_log('Jar pull failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Unable to pull from the Jar right now.'], 500);
}
