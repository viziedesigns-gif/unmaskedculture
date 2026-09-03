<?php
/**
 * API: Create Inner Circle
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');

if (empty($name)) {
    jsonResponse(['success' => false, 'error' => 'Circle name is required']);
}

if (strlen($name) > 100) {
    jsonResponse(['success' => false, 'error' => 'Circle name must be 100 characters or less']);
}

$userId = getCurrentUserId();
$inviteCode = generateInviteCode();

// Ensure unique invite code
while (dbFetchOne("SELECT id FROM inner_circles WHERE invite_code = ?", [$inviteCode])) {
    $inviteCode = generateInviteCode();
}

$pdo = getDbConnection();
$pdo->beginTransaction();

try {
    // Create circle
    dbQuery(
        "INSERT INTO inner_circles (name, description, created_by, invite_code, created_at) 
         VALUES (?, ?, ?, ?, NOW())",
        [$name, $description, $userId, $inviteCode]
    );
    
    $circleId = dbLastId();
    
    // Add creator as owner
    dbQuery(
        "INSERT INTO inner_circle_members (circle_id, user_id, role, joined_at) 
         VALUES (?, ?, 'owner', NOW())",
        [$circleId, $userId]
    );
    
    $pdo->commit();
    
    jsonResponse([
        'success' => true,
        'circle_id' => $circleId,
        'invite_code' => $inviteCode
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Circle creation error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Failed to create circle'], 500);
}
