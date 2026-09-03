<?php
/**
 * API: Log a Weight & BMI Insights update.
 * 77-Day Challenge App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$weightLbs = round((float) ($input['weight_lbs'] ?? 0), 1);

if ($weightLbs < 50 || $weightLbs > 700) {
    jsonResponse(['success' => false, 'error' => 'Please enter a valid weight between 50 and 700 lbs']);
}

$userId = getCurrentUserId();
$user = getCurrentUser();
$heightInches = (int) ($user['height_inches'] ?? 0);

if ($heightInches < 36 || $heightInches > 96) {
    jsonResponse([
        'success' => false,
        'error' => 'Add your height in Health & Water settings before logging weight'
    ]);
}

$userDate = computeUserDate($user['timezone'] ?? DEFAULT_TIMEZONE);

$saved = saveWeightEntry($userId, $userDate, $weightLbs, $heightInches);

jsonResponse([
    'success' => true,
    'weight_lbs' => $saved['weight_lbs'],
    'bmi' => $saved['bmi'],
    'daily_water_oz' => $saved['daily_water_oz'],
    'user_date' => $userDate,
]);
