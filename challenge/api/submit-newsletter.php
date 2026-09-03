<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

const NEWSLETTER_REDIRECT = '/challenge/app/settings/resources.php';
const NEWSLETTER_DEFAULT_FORM_ID = 'fr16mzbvkl5';
const NEWSLETTER_DEFAULT_EMAIL_FIELD_ID = 'fid2';

$newsletterConfigPath = __DIR__ . '/../config/newsletter.php';
if (is_file($newsletterConfigPath)) {
    require_once $newsletterConfigPath;
}

function newsletterRedirect(string $type, string $message): never {
    setFlash($type, $message);
    header('Location: ' . NEWSLETTER_REDIRECT, true, 303);
    exit;
}

function newsletterRequestIsSameSite(): bool {
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

function newsletterConfigValue(string $envName, string $constantName, string $fallback = ''): string {
    $environmentValue = trim((string) getenv($envName));
    if ($environmentValue !== '') {
        return $environmentValue;
    }

    if (defined($constantName)) {
        return trim((string) constant($constantName));
    }

    return $fallback;
}

function newsletterApiToken(): string {
    $token = newsletterConfigValue('FORMCAN_API_TOKEN', 'FORMCAN_API_TOKEN');
    if ($token === '') {
        $token = newsletterConfigValue('NEWSLETTER_FORMCAN_API_TOKEN', 'NEWSLETTER_FORMCAN_API_TOKEN');
    }

    $placeholderTokens = [
        'PASTE_FORMCAN_TOKEN_HERE',
        'YOUR_FORMCAN_API_TOKEN',
        'FORMCAN_API_TOKEN',
    ];

    return in_array($token, $placeholderTokens, true) ? '' : $token;
}

function newsletterFormId(): string {
    return newsletterConfigValue('NEWSLETTER_FORMCAN_FORM_ID', 'NEWSLETTER_FORMCAN_FORM_ID', NEWSLETTER_DEFAULT_FORM_ID);
}

function newsletterEmailFieldId(): string {
    return newsletterConfigValue('NEWSLETTER_FORMCAN_EMAIL_FIELD_ID', 'NEWSLETTER_FORMCAN_EMAIL_FIELD_ID', NEWSLETTER_DEFAULT_EMAIL_FIELD_ID);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

requireOnboarding();

$submittedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
$sessionToken = is_string($_SESSION['newsletter_csrf_token'] ?? null)
    ? $_SESSION['newsletter_csrf_token']
    : '';

if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    newsletterRedirect('error', 'Your newsletter form expired. Please try again.');
}

if (!newsletterRequestIsSameSite()) {
    newsletterRedirect('error', 'We could not verify that newsletter request. Please try again.');
}

$email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    newsletterRedirect('error', 'Please enter a valid email address.');
}

$apiToken = newsletterApiToken();
if ($apiToken === '') {
    error_log('Newsletter submission failed: Formcan API token is not configured.');
    newsletterRedirect('error', 'Newsletter signup is temporarily unavailable. Please try again later.');
}

if (!function_exists('curl_init')) {
    error_log('Newsletter submission failed: PHP cURL is unavailable.');
    newsletterRedirect('error', 'Newsletter signup is temporarily unavailable. Please try again later.');
}

$payload = json_encode([
    'submit_data' => [
        [
            'id' => newsletterEmailFieldId(),
            'type' => 'email',
            'label' => 'Email Address',
            'value' => $email,
        ],
    ],
], JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    error_log('Newsletter submission failed: unable to encode Formcan payload.');
    newsletterRedirect('error', 'Newsletter signup is temporarily unavailable. Please try again later.');
}

$endpoint = 'https://api.formcan.com/v4/submit/form/' . rawurlencode(newsletterFormId()) . '/';
$curl = curl_init($endpoint);
if ($curl === false) {
    error_log('Newsletter submission failed: unable to initialize cURL.');
    newsletterRedirect('error', 'Newsletter signup is temporarily unavailable. Please try again later.');
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
        'Newsletter Formcan submission failed. HTTP status: ' . $statusCode
        . ($curlError !== '' ? ' cURL error: ' . $curlError : '')
        . ($responseText !== '' ? ' response: ' . $responseText : '')
    );
    newsletterRedirect('error', 'We could not complete your signup. Please try again.');
}

unset($_SESSION['newsletter_csrf_token']);
newsletterRedirect('success', 'You are subscribed to the Kinto newsletter.');
