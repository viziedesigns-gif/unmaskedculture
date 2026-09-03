<?php

declare(strict_types=1);

$formCanFormId = 'frh0s66c94u';
$formCanEndpoint = "https://api.formcan.com/v4/submit/form/{$formCanFormId}/";
$thankYouUrl = '../contact-thank-you';
$validationErrorUrl = '../contact?submit_error=validation';
$formCanErrorUrl = '../contact?submit_error=formcan';
$configErrorUrl = '../contact?submit_error=config';

function redirectTo(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

// A filled honeypot is treated as a successful submission so automated senders
// receive no useful signal and nothing is forwarded to Formcan.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirectTo($thankYouUrl);
}

$configPath = __DIR__ . '/../private/formcan-config.php';
$config = is_file($configPath) ? require $configPath : [];
$config = is_array($config) ? $config : [];
$environmentToken = getenv('FORMCAN_API_TOKEN');
$apiToken = trim((string) ($environmentToken !== false && $environmentToken !== '' ? $environmentToken : ($config['api_token'] ?? '')));

if (!$apiToken || $apiToken === 'PASTE_FORMCAN_TOKEN_HERE') {
    error_log('Formcan contact submission blocked: API token is not configured.');
    redirectTo($configErrorUrl);
}

function postedText(string $name, int $maxLength): string
{
    $value = trim((string) ($_POST[$name] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

$firstName = postedText('first_name', 100);
$lastName = postedText('last_name', 100);
$email = postedText('email', 254);
$phone = postedText('phone', 40);
$preferredContact = postedText('preferred_contact', 20);
$bestTime = postedText('best_time', 150);
$message = postedText('message', 5000);
$consent = isset($_POST['consent']) && (string) $_POST['consent'] === '1';

$preferredContactOptions = ['Phone' => '1', 'Email' => '2'];
$bestTimeOptions = ['Morning' => '1', 'Afternoon' => '2', 'Evening' => '3'];

if (
    $firstName === '' ||
    $lastName === '' ||
    $message === '' ||
    !$consent ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !isset($preferredContactOptions[$preferredContact]) ||
    !isset($bestTimeOptions[$bestTime])
) {
    redirectTo($validationErrorUrl);
}

// The published phone widget is a structured control rather than a plain
// fid19 string. Preserve the optional number within the confirmed message field.
$contactDetails = [];
if ($phone !== '') {
    $contactDetails[] = 'Phone: ' . $phone;
}
if ($contactDetails !== []) {
    $contactDetailsText = "\n\n" . implode("\n", $contactDetails);
    $messageLimit = max(0, 5000 - strlen($contactDetailsText));
    $message = (function_exists('mb_substr') ? mb_substr($message, 0, $messageLimit) : substr($message, 0, $messageLimit));
    $message .= $contactDetailsText;
}

$fieldMap = [
    ['id' => 'fid4', 'value' => $firstName],
    ['id' => 'fid5', 'value' => $lastName],
    ['id' => 'fid18', 'value' => $email],
    ['id' => 'fid10', 'value' => $message],
];

$submitData = [];
foreach ($fieldMap as $field) {
    if ($field['value'] === '') {
        continue;
    }
    $submitData[] = $field;
}

$submitData[] = [
    'id' => 'fid20',
    'type' => 'Dropdown',
    'label' => 'Preferred Communication Method:',
    'value' => $preferredContact,
    'raw' => [[
        'id' => $preferredContactOptions[$preferredContact],
        'value' => $preferredContact,
        'url' => null,
    ]],
];

$submitData[] = [
    'id' => 'fid9',
    'type' => 'Dropdown',
    'label' => 'Best time to contact you',
    'value' => $bestTime,
    'raw' => [[
        'id' => $bestTimeOptions[$bestTime],
        'value' => $bestTime,
        'url' => null,
    ]],
];

// Formcan stores this exact option text and option ID. The public label corrects
// the source form's spelling while this payload preserves its schema.
$consentValue = 'By completing this form, you agree to recieve communications from Unmasked Culture Foundation.';
$submitData[] = [
    'id' => 'fid14',
    'type' => 'Choice',
    'label' => '',
    'value' => $consentValue,
    'raw' => [[
        'id' => '1',
        'value' => $consentValue,
        'url' => null,
    ]],
];

$payload = json_encode(['submit_data' => $submitData], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($payload === false) {
    error_log('Formcan contact submission failed: JSON encoding error.');
    redirectTo($formCanErrorUrl);
}

$ch = curl_init($formCanEndpoint);
if ($ch === false) {
    error_log('Formcan contact submission failed: cURL initialization error.');
    redirectTo($formCanErrorUrl);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: token ' . $apiToken,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError !== '' || $statusCode < 200 || $statusCode >= 300) {
    $responseExcerpt = preg_replace('/\s+/', ' ', substr((string) $response, 0, 1000));
    error_log(
        'Formcan contact submission failed. Status: ' . $statusCode .
        ' Error: ' . $curlError .
        ' Response: ' . $responseExcerpt
    );

    if ($statusCode === 401 || $statusCode === 403) {
        redirectTo('../contact?submit_error=auth');
    }
    if ($statusCode === 400 || $statusCode === 422) {
        $knownFieldIds = ['fid4', 'fid5', 'fid18', 'fid9', 'fid10', 'fid14', 'fid20'];
        foreach ($knownFieldIds as $fieldId) {
            if (stripos((string) $response, $fieldId) !== false) {
                redirectTo('../contact?submit_error=payload&field=' . rawurlencode($fieldId));
            }
        }

        // Temporarily return Formcan's redacted validation detail so an
        // otherwise generic 400/422 can be diagnosed without exposing entries.
        $diagnostic = (string) $response;
        $submittedValues = array_filter([$message, $email, $firstName, $lastName, $phone, $preferredContact, $bestTime]);
        usort($submittedValues, static function (string $left, string $right): int {
            return strlen($right) <=> strlen($left);
        });
        foreach ($submittedValues as $submittedValue) {
            $diagnostic = str_ireplace($submittedValue, '[redacted]', $diagnostic);
            $diagnostic = str_ireplace(json_encode($submittedValue), '"[redacted]"', $diagnostic);
        }
        $diagnostic = preg_replace('/[\r\n\t]+/', ' ', strip_tags($diagnostic));
        $diagnostic = preg_replace('/\s{2,}/', ' ', (string) $diagnostic);
        $diagnostic = substr(trim((string) $diagnostic), 0, 300);
        redirectTo('../contact?submit_error=payload&detail=' . rawurlencode($diagnostic));
    }
    if ($statusCode === 404) {
        redirectTo('../contact?submit_error=form');
    }
    if ($statusCode === 429) {
        redirectTo('../contact?submit_error=rate');
    }
    if ($statusCode === 0 || $curlError !== '') {
        redirectTo('../contact?submit_error=connection');
    }
    redirectTo($formCanErrorUrl);
}

redirectTo($thankYouUrl);
