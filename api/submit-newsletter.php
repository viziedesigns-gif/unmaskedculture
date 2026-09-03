<?php

declare(strict_types=1);

const NEWSLETTER_FORM_ID = 'fr16mzbvkl5';

function newsletterRedirect(string $result): never
{
    header('Location: ../?newsletter=' . rawurlencode($result) . '#newsletter', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    newsletterRedirect('success');
}

$source = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''));
if ($source !== '') {
    $requestHost = parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $sourceHost = parse_url($source, PHP_URL_HOST);
    if (!is_string($requestHost) || !is_string($sourceHost) || !hash_equals(strtolower($requestHost), strtolower($sourceHost))) {
        newsletterRedirect('validation');
    }
}

function postedNewsletterText(string $name, int $maxLength): string
{
    $value = trim((string) ($_POST[$name] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

$firstName = postedNewsletterText('first_name', 100);
$lastName = postedNewsletterText('last_name', 100);
$email = strtolower(postedNewsletterText('email', 254));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    newsletterRedirect('validation');
}

$configPath = __DIR__ . '/../private/formcan-config.php';
$config = is_file($configPath) ? require $configPath : [];
$config = is_array($config) ? $config : [];
$existingNewsletterConfig = __DIR__ . '/../challenge/config/newsletter.php';
if (is_file($existingNewsletterConfig)) {
    require_once $existingNewsletterConfig;
}

$environmentToken = trim((string) (getenv('FORMCAN_API_TOKEN') ?: getenv('NEWSLETTER_FORMCAN_API_TOKEN')));
$constantToken = defined('FORMCAN_API_TOKEN')
    ? trim((string) constant('FORMCAN_API_TOKEN'))
    : (defined('NEWSLETTER_FORMCAN_API_TOKEN') ? trim((string) constant('NEWSLETTER_FORMCAN_API_TOKEN')) : '');
$apiToken = $environmentToken !== ''
    ? $environmentToken
    : ($constantToken !== '' ? $constantToken : trim((string) ($config['api_token'] ?? '')));

$placeholderTokens = ['PASTE_FORMCAN_TOKEN_HERE', 'YOUR_FORMCAN_API_TOKEN', 'FORMCAN_API_TOKEN'];
if ($apiToken === '' || in_array($apiToken, $placeholderTokens, true)) {
    error_log('Formcan newsletter submission blocked: API token is not configured.');
    newsletterRedirect('config');
}

$fieldMap = [
    'first_name' => ['id' => 'fid3', 'type' => 'text', 'label' => 'First Name', 'value' => $firstName],
    'last_name' => ['id' => 'fid4', 'type' => 'text', 'label' => 'Last Name', 'value' => $lastName],
    'email' => ['id' => 'fid2', 'type' => 'email', 'label' => 'Email', 'value' => $email],
];

$submitData = [];
foreach ($fieldMap as $field) {
    if ($field['value'] === '') {
        continue;
    }
    $submitData[] = $field;
}

$payload = json_encode(['submit_data' => $submitData], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($payload === false || !function_exists('curl_init')) {
    error_log('Formcan newsletter submission blocked: payload encoding or cURL is unavailable.');
    newsletterRedirect('connection');
}

$endpoint = 'https://api.formcan.com/v4/submit/form/' . rawurlencode(NEWSLETTER_FORM_ID) . '/';
$curl = curl_init($endpoint);
if ($curl === false) {
    error_log('Formcan newsletter submission blocked: unable to initialize cURL.');
    newsletterRedirect('connection');
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
    CURLOPT_TIMEOUT => 20,
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
    $responseExcerpt = preg_replace('/\s+/', ' ', substr((string) $response, 0, 750));
    $responseExcerpt = str_replace(
        array_filter([$firstName, $lastName, $email], static fn (string $value): bool => $value !== ''),
        '[redacted]',
        $responseExcerpt
    );
    error_log(
        'Formcan newsletter submission failed. Status: ' . $statusCode
        . ($curlError !== '' ? ' Error: ' . $curlError : '')
        . ($responseExcerpt !== '' ? ' Response: ' . $responseExcerpt : '')
    );

    if (in_array($statusCode, [401, 403], true)) newsletterRedirect('auth');
    if ($statusCode === 404) newsletterRedirect('form');
    if ($statusCode === 429) newsletterRedirect('rate');
    if (in_array($statusCode, [400, 422], true)) newsletterRedirect('payload');
    newsletterRedirect('connection');
}

newsletterRedirect('success');
