<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/jar_service.php';
require_once __DIR__ . '/../includes/push_service.php';

header('Content-Type: application/json');
if (!isLoggedIn()) jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!validJarCsrf($input['csrf_token'] ?? null)) {
    jsonResponse(['success' => false, 'error' => 'Your session expired. Refresh and try again.'], 403);
}

$authorId = (int) getCurrentUserId();
$ownerId = (int) ($input['target_user_id'] ?? $authorId);
if ($ownerId < 1) $ownerId = $authorId;
$message = (string) ($input['message'] ?? '');
$type = (string) ($input['entry_type'] ?? 'general');

try {
    $entry = addJarEntry($ownerId, $authorId, $message, $type);
    $owner = dbFetchOne("SELECT timezone FROM users WHERE id = ?", [$ownerId]) ?: [];

    if ($authorId !== $ownerId) {
        try {
            $author = getCurrentUser();
            $authorName = trim((string) ($author['first_name'] ?? '') . ' ' . (string) ($author['last_name'] ?? '')) ?: 'Someone';
            sendPushToUser(
                $ownerId,
                $authorName . ' added something to your Jar',
                'A new note is waiting for you.',
                '/challenge/app/jar.php'
            );
        } catch (Throwable $pushError) {
            error_log('Jar push notification failed: ' . $pushError->getMessage());
        }
    }

    jsonResponse([
        'success' => true,
        'entry' => jarEntryPayload($entry, $ownerId, (string) ($owner['timezone'] ?? DEFAULT_TIMEZONE)),
        'entry_count' => getJarEntryCount($ownerId),
    ]);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    $status = $e->getCode() === 403 ? 403 : 400;
    jsonResponse(['success' => false, 'error' => $e->getMessage()], $status);
} catch (Throwable $e) {
    error_log('Jar add failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Unable to add to the Jar right now.'], 500);
}
