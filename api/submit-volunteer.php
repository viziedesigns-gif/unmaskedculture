<?php

declare(strict_types=1);

$formCanFormId = 'frcmaenkcz1';
$formCanEndpoint = "https://api.formcan.com/v4/submit/form/{$formCanFormId}/";
$thankYouUrl = '../volunteer-thank-you';
$validationErrorUrl = '../volunteer?submit_error=validation';
$configErrorUrl = '../volunteer?submit_error=config';
$formCanErrorUrl = '../volunteer?submit_error=formcan';

function redirectTo(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

function postedText(string $name, int $maxLength): string
{
    $value = trim((string) ($_POST[$name] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirectTo($thankYouUrl);
}

$configPath = __DIR__ . '/../private/formcan-config.php';
$config = is_file($configPath) ? require $configPath : [];
$config = is_array($config) ? $config : [];
$environmentToken = getenv('FORMCAN_API_TOKEN');
$apiToken = trim((string) ($environmentToken !== false && $environmentToken !== '' ? $environmentToken : ($config['api_token'] ?? '')));

if (!$apiToken || $apiToken === 'PASTE_FORMCAN_TOKEN_HERE') {
    error_log('Formcan volunteer submission blocked: API token is not configured.');
    redirectTo($configErrorUrl);
}

$firstName = postedText('first_name', 100);
$lastName = postedText('last_name', 100);
$email = postedText('email', 254);
$phone = postedText('phone', 40);
$socialMedia = postedText('social_media', 250);
$streetAddress = postedText('street_address', 250);
$city = postedText('city', 120);
$state = postedText('state', 80);
$zip = postedText('zip', 10);
$bestTime = postedText('best_time', 20);
$whyVolunteer = postedText('why_volunteer', 5000);
$skills = postedText('skills', 5000);
$signatureDateInput = postedText('signature_date', 10);
$signatureData = trim((string) ($_POST['signature_data'] ?? ''));
$agreement = isset($_POST['agreement']) && (string) $_POST['agreement'] === '1';

$bestTimeOptions = ['Morning' => '1', 'Afternoon' => '2', 'Evening' => '3'];
$interestOptions = [
    'Social Media' => '1',
    'Fundraising' => '4',
    'Public Relations' => '6',
    'Tech' => '2',
    'Administration' => '3',
    'Marketing' => '8',
    'Grant Writing' => '12',
    'Accounting' => '14',
    'Content Creation' => '10',
    'Podcasts' => '5',
    'Community Engagement' => '9',
    'Advocacy' => '7',
    'Videographer' => '15',
    'Script Writer' => '16',
    'A/V Manager' => '17',
    'Actor' => '19',
    'Other' => '13',
];

$submittedInterests = $_POST['interests'] ?? [];
$submittedInterests = is_array($submittedInterests) ? $submittedInterests : [];
$interests = [];
foreach ($submittedInterests as $interest) {
    $interest = trim((string) $interest);
    if (isset($interestOptions[$interest]) && !in_array($interest, $interests, true)) {
        $interests[] = $interest;
    }
}

$signatureDate = DateTime::createFromFormat('!Y-m-d', $signatureDateInput);
$validDate = $signatureDate instanceof DateTime && $signatureDate->format('Y-m-d') === $signatureDateInput;
$phoneDigits = preg_replace('/\D+/', '', $phone);
$validPhone = is_string($phoneDigits) && preg_match('/^\d{10}$/', $phoneDigits) === 1;
$formattedPhone = $validPhone
    ? substr($phoneDigits, 0, 3) . '-' . substr($phoneDigits, 3, 3) . '-' . substr($phoneDigits, 6, 4)
    : '';
$validZip = preg_match('/^\d{5}$/', $zip) === 1;
$signaturePrefix = 'data:image/png;base64,';
$validSignature = substr($signatureData, 0, strlen($signaturePrefix)) === $signaturePrefix && strlen($signatureData) <= 2000000;
if ($validSignature) {
    $decodedSignature = base64_decode(substr($signatureData, strlen($signaturePrefix)), true);
    $validSignature = $decodedSignature !== false && substr($decodedSignature, 0, 8) === "\x89PNG\r\n\x1a\n";
}

if (
    $firstName === '' ||
    $lastName === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !$validPhone ||
    $socialMedia === '' ||
    $streetAddress === '' ||
    $city === '' ||
    $state === '' ||
    !$validZip ||
    !isset($bestTimeOptions[$bestTime]) ||
    $whyVolunteer === '' ||
    $interests === [] ||
    !$agreement ||
    !$validDate ||
    !$validSignature
) {
    redirectTo($validationErrorUrl);
}

$submitData = [
    ['id' => 'fid4', 'type' => 'text', 'label' => 'First Name', 'value' => $firstName],
    ['id' => 'fid5', 'type' => 'text', 'label' => 'Last Name', 'value' => $lastName],
    ['id' => 'fid8', 'type' => 'email', 'label' => 'Email', 'value' => $email],
    [
        'id' => 'fid32',
        'type' => 'text',
        'label' => 'Phone',
        'value' => $formattedPhone,
        'raw' => [
            ['id' => '0', 'value' => substr($phoneDigits, 0, 3), 'url' => null],
            ['id' => '1', 'value' => '-', 'url' => null],
            ['id' => '2', 'value' => substr($phoneDigits, 3, 3), 'url' => null],
            ['id' => '3', 'value' => '-', 'url' => null],
            ['id' => '4', 'value' => substr($phoneDigits, 6, 4), 'url' => null],
        ],
    ],
    ['id' => 'fid21', 'type' => 'text', 'label' => 'Social Media Handles:', 'value' => $socialMedia],
    ['id' => 'fid9', 'type' => 'text', 'label' => 'Street Address', 'value' => $streetAddress],
    ['id' => 'fid17', 'type' => 'text', 'label' => 'City', 'value' => $city],
    ['id' => 'fid18', 'type' => 'text', 'label' => 'State', 'value' => $state],
    [
        'id' => 'fid19',
        'type' => 'text',
        'label' => 'Zip',
        'value' => $zip,
        'raw' => [[
            'id' => '0',
            'value' => $zip,
            'url' => null,
        ]],
    ],
    [
        'id' => 'fid37',
        'type' => 'Dropdown',
        'label' => '',
        'value' => $bestTime,
        'raw' => [[
            'id' => $bestTimeOptions[$bestTime],
            'value' => $bestTime,
            'url' => null,
        ]],
    ],
    ['id' => 'fid23', 'type' => 'mtext', 'label' => 'Why are you passionate about mental health and why do you want to work with Unmasked Culture?', 'value' => $whyVolunteer],
];

if ($skills !== '') {
    $submitData[] = ['id' => 'fid24', 'type' => 'mtext', 'label' => 'Any special talents or skills you have that you feel would benefit Unmasked Culture?', 'value' => $skills];
}

$interestRaw = [];
foreach ($interests as $interest) {
    $interestRaw[] = [
        'id' => $interestOptions[$interest],
        'value' => $interest,
        'url' => null,
    ];
}
$submitData[] = [
    'id' => 'fid35',
    'type' => 'Choice',
    'label' => 'I am Interested in volunteering in the following areas:',
    'value' => implode(', ', $interests),
    'raw' => $interestRaw,
];
$submitData[] = ['id' => 'fid29', 'type' => 'Signature', 'label' => 'Signature', 'value' => $signatureData];
$submitData[] = [
    'id' => 'fid30',
    'type' => 'date',
    'label' => 'Date',
    'value' => $signatureDate->format('m/d/Y'),
    'raw' => [
        ['id' => '0', 'value' => $signatureDate->format('m'), 'url' => null],
        ['id' => '1', 'value' => '/', 'url' => null],
        ['id' => '2', 'value' => $signatureDate->format('d'), 'url' => null],
        ['id' => '3', 'value' => '/', 'url' => null],
        ['id' => '4', 'value' => $signatureDate->format('Y'), 'url' => null],
    ],
];

$payload = json_encode(['submit_data' => $submitData], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($payload === false) {
    error_log('Formcan volunteer submission failed: JSON encoding error.');
    redirectTo($formCanErrorUrl);
}

$ch = curl_init($formCanEndpoint);
if ($ch === false) {
    error_log('Formcan volunteer submission failed: cURL initialization error.');
    redirectTo('../volunteer?submit_error=connection');
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
    CURLOPT_TIMEOUT => 45,
]);

$response = curl_exec($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError !== '' || $statusCode < 200 || $statusCode >= 300) {
    $responseExcerpt = preg_replace('/\s+/', ' ', substr((string) $response, 0, 1000));
    error_log('Formcan volunteer submission failed. Status: ' . $statusCode . ' Error: ' . $curlError . ' Response: ' . $responseExcerpt);

    if ($statusCode === 401 || $statusCode === 403) {
        redirectTo('../volunteer?submit_error=auth');
    }
    if ($statusCode === 400 || $statusCode === 422) {
        $knownFieldIds = ['fid4', 'fid5', 'fid8', 'fid32', 'fid21', 'fid9', 'fid17', 'fid18', 'fid19', 'fid37', 'fid23', 'fid24', 'fid35', 'fid29', 'fid30'];
        foreach ($knownFieldIds as $fieldId) {
            if (stripos((string) $response, $fieldId) !== false) {
                redirectTo('../volunteer?submit_error=payload&field=' . rawurlencode($fieldId));
            }
        }
        redirectTo('../volunteer?submit_error=payload');
    }
    if ($statusCode === 404) {
        redirectTo('../volunteer?submit_error=form');
    }
    if ($statusCode === 429) {
        redirectTo('../volunteer?submit_error=rate');
    }
    if ($statusCode === 0 || $curlError !== '') {
        redirectTo('../volunteer?submit_error=connection');
    }
    redirectTo($formCanErrorUrl);
}

redirectTo($thankYouUrl);
