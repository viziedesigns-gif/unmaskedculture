<?php
/**
 * Avatar Studio actions: buy, equip, toggle public face.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/avatar_service.php';
require_once __DIR__ . '/../includes/avatar_render.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['ok' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!validAvatarCsrf(isset($input['csrf_token']) ? (string) $input['csrf_token'] : null)) {
    jsonResponse(['ok' => false, 'error' => 'Your session expired. Refresh and try again.'], 403);
}

$userId = (int) getCurrentUserId();
$action = trim((string) ($input['action'] ?? ''));

if ($action === 'buy') {
    $itemId = trim((string) ($input['item_id'] ?? ''));
    $result = purchaseAvatarItem($userId, $itemId);
    if (!$result['ok']) {
        jsonResponse($result, 400);
    }
    $item = getAvatarItem($itemId);
    jsonResponse(avatarActionPayload($result, 'Purchased ' . ($item['name'] ?? 'item') . '!'));
}

if ($action === 'equip') {
    $itemId = trim((string) ($input['item_id'] ?? ''));
    $result = equipAvatarItem($userId, $itemId);
    if (!$result['ok']) {
        jsonResponse($result, 400);
    }
    $item = getAvatarItem($itemId);
    jsonResponse(avatarActionPayload($result, 'Equipped ' . ($item['name'] ?? 'item') . '.'));
}

if ($action === 'buy_and_equip') {
    $itemId = trim((string) ($input['item_id'] ?? ''));
    $buy = purchaseAvatarItem($userId, $itemId);
    if (!$buy['ok']) {
        jsonResponse($buy, 400);
    }
    $result = equipAvatarItem($userId, $itemId);
    if (!$result['ok']) {
        jsonResponse($result, 400);
    }
    $item = getAvatarItem($itemId);
    jsonResponse(avatarActionPayload($result, 'Purchased and equipped ' . ($item['name'] ?? 'item') . '!'));
}

if ($action === 'set_public_face') {
    $enabled = !empty($input['enabled']);
    $result = setAvatarPublicFace($userId, $enabled);
    if (!$result['ok']) {
        jsonResponse($result, 400);
    }
    jsonResponse(avatarActionPayload(
        $result,
        $enabled ? 'Your avatar is now your public face.' : 'Your photo will show as your public face.'
    ));
}

jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);

/**
 * @param array{ok:bool,error:string,state?:array} $result
 */
function avatarActionPayload(array $result, string $message): array {
    $state = $result['state'] ?? getAvatarState((int) getCurrentUserId());
    return [
        'ok' => true,
        'error' => '',
        'message' => $message,
        'state' => $state,
        'preview_html' => renderKintoAvatar($state['config'], ['size' => 'xl', 'animate' => true]),
    ];
}
