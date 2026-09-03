<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

const FEEDBACK_REDIRECT = '/challenge/app/settings/suggestion_support.php';
const FEEDBACK_DEFAULT_FORM_ID = 'frwvr6zzxnl';
const FEEDBACK_TYPE_LABEL = 'App Suggestion';
const FEEDBACK_TYPE_OPTION_ID = '1';

$feedbackConfigPath = __DIR__ . '/../config/newsletter.php';
if (is_file($feedbackConfigPath)) {
    require_once $feedbackConfigPath;
}

function feedbackRedirect(string $type, string $message): never {
    setFlash($type, $message);
    header('Location: ' . FEEDBACK_REDIRECT, true, 303);
    exit;
}

function feedbackRequestIsSameSite(): bool {
    $source = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''));
    if ($source === '') {
        return true;
    }

    $requestHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $sourceHost = parse_url($source, PHP_URL_HOST);
    $sourceScheme = parse_url($source, PHP_URL_SCHEME);

    return is_string($requestHost)
        && $requestHost !== ''
        && is_string($sourceHost)
        && hash_equals(strtolower($requestHost), strtolower($sourceHost))
        && in_array(strtolower((string) $sourceScheme), ['http', 'https'], true);
}

function feedbackConfigValue(string $envName, string $constantName, string $fallback = ''): string {
    $environmentValue = trim((string) getenv($envName));
    if ($environmentValue !== '') {
        return $environmentValue;
    }

    if (defined($constantName)) {
        return trim((string) constant($constantName));
    }

    return $fallback;
}

function feedbackApiToken(): string {
    $token = feedbackConfigValue('FORMCAN_API_TOKEN', 'FORMCAN_API_TOKEN');
    if ($token === '') {
        $token = feedbackConfigValue('FEEDBACK_FORMCAN_API_TOKEN', 'FEEDBACK_FORMCAN_API_TOKEN');
    }
    if ($token === '') {
        $token = feedbackConfigValue('NEWSLETTER_FORMCAN_API_TOKEN', 'NEWSLETTER_FORMCAN_API_TOKEN');
    }

    $placeholderTokens = [
        'PASTE_FORMCAN_TOKEN_HERE',
        'YOUR_FORMCAN_API_TOKEN',
        'FORMCAN_API_TOKEN',
    ];

    return in_array($token, $placeholderTokens, true) ? '' : $token;
}

function feedbackFormId(): string {
    return feedbackConfigValue('FEEDBACK_FORMCAN_FORM_ID', 'FEEDBACK_FORMCAN_FORM_ID', FEEDBACK_DEFAULT_FORM_ID);
}

function feedbackPostString(string $key, int $maxLength): string {
    $value = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    if ($value === '') {
        return '';
    }

    return substr($value, 0, $maxLength);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

requireOnboarding();

$submittedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
$sessionToken = is_string($_SESSION['feedback_csrf_token'] ?? null)
    ? $_SESSION['feedback_csrf_token']
    : '';

if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    feedbackRedirect('error', 'Your feedback form expired. Please try again.');
}

if (!feedbackRequestIsSameSite()) {
    feedbackRedirect('error', 'We could not verify that feedback request. Please try again.');
}

$firstName = feedbackPostString('first_name', 100);
$lastName = feedbackPostString('last_name', 100);
$email = strtolower(feedbackPostString('email', 254));
$feedback = feedbackPostString('feedback', 3000);

if ($firstName === '' || $lastName === '' || $feedback === '') {
    feedbackRedirect('error', 'Please complete every field before sending.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    feedbackRedirect('error', 'Please enter a valid email address.');
}

$apiToken = feedbackApiToken();
if ($apiToken === '') {
    error_log('Feedback submission failed: Formcan API token is not configured.');
    feedbackRedirect('error', 'Feedback is temporarily unavailable. Please try again later.');
}

if (!function_exists('curl_init')) {
    error_log('Feedback submission failed: PHP cURL is unavailable.');
    feedbackRedirect('error', 'Feedback is temporarily unavailable. Please try again later.');
}

$payload = json_encode([
    'submit_data' => [
        [
            'id' => 'fid3',
            'type' => 'text',
            'label' => 'First Name',
            'value' => $firstName,
        ],
        [
            'id' => 'fid4',
            'type' => 'text',
            'label' => 'Last Name',
            'value' => $lastName,
        ],
        [
            'id' => 'fid5',
            'type' => 'email',
            'label' => 'Email Address',
            'value' => $email,
        ],
        [
            'id' => 'fid6',
            'type' => 'Choice',
            'label' => 'Feedback Type',
            'value' => FEEDBACK_TYPE_LABEL,
            'raw' => [
                [
                    'id' => FEEDBACK_TYPE_OPTION_ID,
                    'value' => FEEDBACK_TYPE_LABEL,
                    'url' => null,
                ],
            ],
        ],
        [
            'id' => 'fid7',
            'type' => 'mtext',
            'label' => 'Your Feedback',
            'value' => $feedback,
        ],
    ],
], JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    error_log('Feedback submission failed: unable to encode Formcan payload.');
    feedbackRedirect('error', 'Feedback is temporarily unavailable. Please try again later.');
}

$endpoint = 'https://api.formcan.com/v4/submit/form/' . rawurlencode(feedbackFormId()) . '/';
$curl = curl_init($endpoint);
if ($curl === false) {
    error_log('Feedback submission failed: unable to initialize cURL.');
    feedbackRedirect('error', 'Feedback is temporarily unavailable. Please try again later.');
}

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: token ' . $apiToken,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($curl);
$statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($response === false || $curlError !== '' || $statusCode < 200 || $statusCode >= 300) {
    $responseText = is_string($response) ? trim(substr($response, 0, 500)) : '';
    error_log(
        'Feedback Formcan submission failed. HTTP status: ' . $statusCode
        . ($curlError !== '' ? ' cURL error: ' . $curlError : '')
        . ($responseText !== '' ? ' response: ' . $responseText : '')
    );
    feedbackRedirect('error', 'We could not send your feedback. Please try again.');
}

unset($_SESSION['feedback_csrf_token']);
feedbackRedirect('success', 'Thanks. Your feedback was sent to the Kinto team.');
