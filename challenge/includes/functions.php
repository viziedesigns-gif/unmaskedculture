<?php
/**
 * Helper Functions
 * Kinto App
 */

require_once __DIR__ . '/../config/database.php';

define('FAVICON_BASE', '/challenge/assets/kinto-favicon');

/** Return a content-based version for an app-owned asset. */
function assetContentVersion(string $path): string {
    static $versions = [];
    if (isset($versions[$path])) {
        return $versions[$path];
    }

    $urlPath = (string) (parse_url($path, PHP_URL_PATH) ?? '');
    if (str_starts_with($urlPath, '/challenge/')) {
        $relativePath = rawurldecode(substr($urlPath, strlen('/challenge/')));
        $appRoot = realpath(dirname(__DIR__));
        $assetPath = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if ($appRoot !== false && $assetPath !== false
            && str_starts_with($assetPath, $appRoot . DIRECTORY_SEPARATOR)
            && is_file($assetPath)) {
            $fingerprint = hash_file('sha256', $assetPath);
            if (is_string($fingerprint) && $fingerprint !== '') {
                return $versions[$path] = APP_VERSION . '-' . substr($fingerprint, 0, 12);
            }
        }
    }

    return $versions[$path] = APP_VERSION;
}

/**
 * Build a cache-busted asset URL. The URL changes automatically when the
 * uploaded file contents change, so refreshes never reuse an older release.
 */
function assetUrl(string $path): string {
    $separator = str_contains($path, '?') ? '&' : '?';
    return $path . $separator . 'v=' . rawurlencode(assetContentVersion($path));
}

/**
 * Service worker cache bucket name for the current app version.
 * @return string
 */
function shellCacheName(): string {
    $shellAssets = [
        '/challenge/assets/css/style.css',
        '/challenge/assets/js/app.js',
        '/challenge/assets/js/podcast-player.js',
        '/challenge/assets/js/site-shell.js',
        '/challenge/assets/js/water-scene.js',
        '/challenge/assets/js/jar-scene.js',
        '/challenge/assets/js/jar-page.js',
        '/challenge/assets/js/kinto-avatar.js',
        '/challenge/manifest.php',
    ];
    $versions = array_map('assetContentVersion', $shellAssets);
    $fingerprint = substr(hash('sha256', implode('|', $versions)), 0, 12);
    return 'challenge-shell-' . str_replace('.', '-', APP_VERSION) . '-' . $fingerprint;
}

/**
 * Build a versioned favicon asset URL.
 * @param string $filename
 * @return string
 */
function faviconUrl(string $filename): string {
    return assetUrl(FAVICON_BASE . '/' . $filename);
}

/**
 * Sanitize output for HTML
 * @param string|null $string
 * @return string
 */
function h(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Resolve a stored profile picture path to a public URL.
 *
 * New profile pictures live outside the challenge app in /uploads/profile-pictures
 * so app deployments do not remove user uploads. Legacy challenge/uploads values
 * are still supported for existing users.
 * @param string|null $profilePic
 * @return string
 */
function profilePicUrl(?string $profilePic): string {
    $profilePic = trim($profilePic ?? '');
    if ($profilePic === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $profilePic)) {
        return $profilePic;
    }

    if (str_starts_with($profilePic, '/')) {
        return $profilePic;
    }

    $profilePic = ltrim($profilePic, '/');

    if (str_starts_with($profilePic, 'uploads/profile-pictures/')) {
        return '/' . $profilePic;
    }

    if (str_starts_with($profilePic, 'profile-pictures/')) {
        return '/uploads/' . $profilePic;
    }

    if (str_starts_with($profilePic, 'uploads/')) {
        $durableProfilePicPath = PROFILE_PIC_UPLOAD_PATH . basename($profilePic);
        if (is_file($durableProfilePicPath)) {
            return PROFILE_PIC_UPLOAD_URL . basename($profilePic);
        }

        return '/challenge/' . $profilePic;
    }

    return '/challenge/' . $profilePic;
}

/**
 * Attach a resolved profile_pic_url for feed/API payloads.
 * @param array $message
 * @return array
 */
function withProfilePicUrl(array $message): array {
    $message['profile_pic_url'] = profilePicUrl($message['profile_pic'] ?? null);
    return $message;
}

/**
 * @param array<int, array> $messages
 * @return array<int, array>
 */
function withProfilePicUrls(array $messages): array {
    return array_map('withProfilePicUrl', $messages);
}

/**
 * Resolve a stored profile picture path to a local filesystem path when it is
 * one of the upload locations this app owns.
 * @param string|null $profilePic
 * @return string|null
 */
function profilePicFilesystemPath(?string $profilePic): ?string {
    $profilePic = trim($profilePic ?? '');
    if ($profilePic === '' || preg_match('#^https?://#i', $profilePic)) {
        return null;
    }

    $profilePic = ltrim($profilePic, '/');

    if (str_starts_with($profilePic, 'uploads/profile-pictures/')) {
        return PROFILE_PIC_UPLOAD_PATH . basename($profilePic);
    }

    if (str_starts_with($profilePic, 'profile-pictures/')) {
        return PROFILE_PIC_UPLOAD_PATH . basename($profilePic);
    }

    if (str_starts_with($profilePic, 'challenge/uploads/')) {
        return __DIR__ . '/../uploads/' . basename($profilePic);
    }

    if (str_starts_with($profilePic, 'uploads/')) {
        $durableProfilePicPath = PROFILE_PIC_UPLOAD_PATH . basename($profilePic);
        if (is_file($durableProfilePicPath)) {
            return $durableProfilePicPath;
        }

        return __DIR__ . '/../' . $profilePic;
    }

    return null;
}

/**
 * Generate a secure random string
 * @param int $length
 * @return string
 */
function generateRandomString(int $length = 10): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate invite code (readable format)
 * @return string
 */
function generateInviteCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing chars
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Normalize an invite code copied from a link, message, or manual entry.
 */
function normalizeCircleInviteCode(?string $code): string {
    $code = strtoupper(trim((string) $code));
    return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
}

/**
 * Validate and remember a circle invite across login, registration, and onboarding.
 *
 * @return array|null Circle details when valid; null when invalid.
 */
function rememberCircleInvite(?string $rawCode): ?array {
    $inviteCode = normalizeCircleInviteCode($rawCode);
    if ($inviteCode === '' || strlen($inviteCode) > 20) {
        return null;
    }

    $circle = dbFetchOne(
        "SELECT id, name, created_by, invite_code FROM inner_circles WHERE invite_code = ?",
        [$inviteCode]
    );
    if (!$circle) {
        return null;
    }

    $_SESSION['pending_invite_code'] = $inviteCode;
    $_SESSION['pending_circle_name'] = $circle['name'];
    return $circle;
}

/**
 * Common activity choices for the 30-minute workout checklist item.
 * @return array<string, string>
 */
function getWorkoutTypeOptions(): array {
    return [
        'walking' => 'Walking',
        'running' => 'Running/Jogging',
        'cycling' => 'Cycling',
        'swimming' => 'Swimming',
        'strength' => 'Strength Training',
        'yoga_mobility' => 'Yoga/Mobility',
        'dance' => 'Dance',
        'sports' => 'Sports/Recreation',
        'yard_work' => 'Yard Work/Active Chores',
        'custom' => 'Custom'
    ];
}

/**
 * Get a display label for a stored workout type.
 * @param string|null $type
 * @param string|null $custom
 * @return string
 */
function getWorkoutTypeLabel(?string $type, ?string $custom = null): string {
    $type = trim($type ?? '');
    $custom = trim($custom ?? '');

    if ($type === 'custom') {
        return $custom !== '' ? $custom : 'Custom workout';
    }

    $options = getWorkoutTypeOptions();
    return $options[$type] ?? ($type !== '' ? ucwords(str_replace(['_', '-'], ' ', $type)) : '');
}

/**
 * Profile prompt choices used during onboarding and profile editing.
 * @return array<string, string>
 */
function getProfilePromptOptions(): array {
    return [
        'motivation' => 'What motivates you?',
        'reset' => 'What helps you reset on a hard day?',
        'encouragement' => 'What kind of encouragement helps you most?',
        'goal' => 'What are you hoping to grow through this challenge?',
        'rhythm' => 'What daily rhythm are you building?',
        'community' => 'How can your circle support you?'
    ];
}

function getProfilePromptQuestion(?string $key): string {
    $options = getProfilePromptOptions();
    return $options[$key ?? ''] ?? $options['motivation'];
}

/**
 * Ensure the daily workout log table exists on older production databases.
 * @return void
 */
function ensureWorkoutLogTable(): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    dbQuery(
        "CREATE TABLE IF NOT EXISTS workout_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            user_date DATE NOT NULL,
            workout_type VARCHAR(50) NOT NULL,
            workout_custom VARCHAR(100) DEFAULT NULL,
            duration_minutes INT NOT NULL DEFAULT 30,
            logged_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_workout_day (user_id, user_date),
            INDEX idx_user_date (user_id, user_date),
            CONSTRAINT fk_workout_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ready = true;
}

/**
 * Fetch one user's workout entry for a date.
 * @param int $userId
 * @param string $userDate
 * @return array|null
 */
function getWorkoutForDate(int $userId, string $userDate): ?array {
    ensureWorkoutLogTable();

    return dbFetchOne(
        "SELECT * FROM workout_log WHERE user_id = ? AND user_date = ?",
        [$userId, $userDate]
    );
}

/**
 * Ensure the weight tracking table exists and retire the legacy checklist item.
 * @return void
 */
function ensureWeightTrackingReady(): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    dbQuery(
        "CREATE TABLE IF NOT EXISTS weight_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            user_date DATE NOT NULL,
            weight_lbs DECIMAL(5,1) NOT NULL,
            bmi DECIMAL(4,1) NOT NULL,
            logged_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_weight_day (user_id, user_date),
            INDEX idx_user_date (user_id, user_date),
            CONSTRAINT fk_weight_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Weight is updated from Insights now, not from the daily checklist.
    dbQuery(
        "UPDATE daily_checklist_items
         SET active = 0
         WHERE id = 8 AND item_type = 'weight_tracker'"
    );

    $ready = true;
}

/**
 * Fetch one user's weight entry for a date.
 * @param int $userId
 * @param string $userDate
 * @return array|null
 */
function getWeightForDate(int $userId, string $userDate): ?array {
    ensureWeightTrackingReady();

    return dbFetchOne(
        "SELECT * FROM weight_log WHERE user_id = ? AND user_date = ?",
        [$userId, $userDate]
    );
}

/**
 * Save a weight entry, recalculate BMI and water goal, and sync current user stats.
 * @param int $userId
 * @param string $userDate
 * @param float $weightLbs
 * @param int $heightInches
 * @return array{weight_lbs: float, bmi: float, daily_water_oz: int}
 */
function saveWeightEntry(int $userId, string $userDate, float $weightLbs, int $heightInches): array {
    ensureWeightTrackingReady();

    $bmi = calculateBMI($weightLbs, $heightInches);
    $dailyWater = calculateDailyWater($weightLbs);

    dbQuery(
        "INSERT INTO weight_log (user_id, user_date, weight_lbs, bmi, logged_at_utc, updated_at_utc)
         VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            weight_lbs = VALUES(weight_lbs),
            bmi = VALUES(bmi),
            updated_at_utc = UTC_TIMESTAMP()",
        [$userId, $userDate, $weightLbs, $bmi]
    );

    updateUserProfile($userId, [
        'weight_lbs' => $weightLbs,
        'bmi' => $bmi,
        'daily_water_oz' => $dailyWater,
    ]);

    return [
        'weight_lbs' => round($weightLbs, 1),
        'bmi' => $bmi,
        'daily_water_oz' => $dailyWater,
    ];
}

/**
 * Get user's timezone
 * @param int $userId
 * @return string
 */
function getUserTimezone(int $userId): string {
    $user = dbFetchOne("SELECT timezone FROM users WHERE id = ?", [$userId]);
    return $user['timezone'] ?? DEFAULT_TIMEZONE;
}

/**
 * Compute user's local date from timezone
 * @param string $timezone
 * @param DateTimeImmutable|null $utcNow
 * @return string Y-m-d format
 */
function computeUserDate(string $timezone, ?DateTimeImmutable $utcNow = null): string {
    $tz = new DateTimeZone($timezone);
    $now = $utcNow ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $local = $now->setTimezone($tz);
    return $local->format('Y-m-d');
}

/**
 * Easy mode keeps yesterday editable until 1:00 AM. Intermediate closes at midnight.
 */
function checklistAllowsGracePeriod(?string $challengeMode): bool {
    return $challengeMode === 'easy';
}

/**
 * Local hour when a checklist day closes on the following calendar day.
 * Easy: 1:00 AM. Intermediate: midnight (00:00).
 */
function getChecklistDeadlineHour(?string $challengeMode): int {
    return checklistAllowsGracePeriod($challengeMode) ? 1 : 0;
}

/**
 * Resolve the checklist date a user is allowed to edit.
 *
 * The current local day is always available. In Easy mode, during the first
 * hour after midnight the previous local day is also available so a user can
 * finish a checklist whose deadline is 1:00 AM. Intermediate has no grace.
 *
 * @return array{selected_date:string,today:string,yesterday:string,can_log_yesterday:bool,is_yesterday:bool}
 */
function resolveChecklistDate(
    string $timezone,
    ?string $requestedDate = null,
    ?DateTimeImmutable $utcNow = null,
    bool $allowGracePeriod = true
): array {
    $tz = new DateTimeZone($timezone);
    $now = ($utcNow ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz);
    $today = $now->format('Y-m-d');
    $yesterday = $now->modify('-1 day')->format('Y-m-d');
    $canLogYesterday = $allowGracePeriod && (int) $now->format('G') < 1;

    $selectedDate = $today;
    if ($requestedDate !== null && $requestedDate !== '') {
        $validFormat = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) === 1;
        if (!$validFormat || ($requestedDate !== $today && (!$canLogYesterday || $requestedDate !== $yesterday))) {
            $msg = $allowGracePeriod
                ? 'That checklist is no longer available. Yesterday can only be logged until 1:00 AM.'
                : 'That checklist is no longer available. Intermediate mode closes at midnight.';
            throw new InvalidArgumentException($msg);
        }
        $selectedDate = $requestedDate;
    }

    return [
        'selected_date' => $selectedDate,
        'today' => $today,
        'yesterday' => $yesterday,
        'can_log_yesterday' => $canLogYesterday,
        'is_yesterday' => $selectedDate === $yesterday,
    ];
}

/**
 * Return true during Easy mode's local 12:00-12:59 AM grace period.
 * Intermediate never has a grace window.
 */
function isChecklistGracePeriod(
    string $timezone,
    ?DateTimeImmutable $utcNow = null,
    bool $allowGracePeriod = true
): bool {
    if (!$allowGracePeriod) {
        return false;
    }
    return resolveChecklistDate($timezone, null, $utcNow, true)['can_log_yesterday'];
}

/**
 * Get current UTC datetime
 * @return string
 */
function utcNow(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

/**
 * Calculate BMI from weight (lbs) and height (inches)
 * @param float $weightLbs
 * @param int $heightInches
 * @return float
 */
function calculateBMI(float $weightLbs, int $heightInches): float {
    // BMI = (weight in pounds * 703) / (height in inches)^2
    return round(($weightLbs * 703) / ($heightInches * $heightInches), 1);
}

/**
 * Calculate daily water intake in oz based on weight
 * @param float $weightLbs
 * @return int
 */
function calculateDailyWater(float $weightLbs): int {
    // Standard recommendation: half body weight in ounces
    return (int) round($weightLbs * 0.5);
}

/**
 * Calculate age from date of birth
 * @param string $dob Y-m-d format
 * @return int
 */
function calculateAge(string $dob): int {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * @param string $password
 * @return array [bool valid, string message]
 */
function validatePassword(string $password): array {
    if (strlen($password) < 8) {
        return [false, 'Password must be at least 8 characters long'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return [false, 'Password must contain at least one uppercase letter'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return [false, 'Password must contain at least one lowercase letter'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return [false, 'Password must contain at least one number'];
    }
    return [true, ''];
}

/**
 * Handle file upload for profile pictures
 * @param array $file $_FILES element
 * @param int $userId
 * @return array [bool success, string pathOrError]
 */
function handleProfilePicUpload(array $file, int $userId): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That photo is too large. Choose a photo under 5 MB.',
            UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Choose the photo and try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not save that photo. Try again without a photo, then add one from Settings.',
            default => 'The photo could not be uploaded. Try again without a photo, then add one from Settings.',
        };
        return [false, $message];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return [false, 'That photo is too large. Choose a photo under 5 MB.'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return [false, 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP'];
    }
    
    // Create durable profile picture directory if it doesn't exist.
    if (!is_dir(PROFILE_PIC_UPLOAD_PATH)) {
        mkdir(PROFILE_PIC_UPLOAD_PATH, 0755, true);
    }
    
    // Generate unique filename
    $ext = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    
    $filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
    $destination = PROFILE_PIC_UPLOAD_PATH . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [false, 'Failed to save uploaded file'];
    }
    
    return [true, 'uploads/profile-pictures/' . $filename];
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate(string $date, string $format = 'M j, Y'): string {
    return (new DateTime($date))->format($format);
}

/**
 * Get time remaining until end of day in user's timezone
 * @param string $timezone
 * @return array [hours, minutes, seconds]
 */
function getTimeUntilMidnight(string $timezone): array {
    $tz = new DateTimeZone($timezone);
    $now = new DateTime('now', $tz);
    $midnight = new DateTime('tomorrow', $tz);
    $diff = $now->diff($midnight);
    
    return [
        'hours' => $diff->h,
        'minutes' => $diff->i,
        'seconds' => $diff->s
    ];
}

/**
 * Get the next local midnight for a timezone as a UTC ISO-8601 timestamp.
 * This gives browser countdowns a server-authoritative expiry target while
 * still honoring the user's stored timezone.
 * @param string $timezone
 * @return string
 */
function getNextLocalMidnightUtcIso(string $timezone): string {
    $tz = new DateTimeZone($timezone);
    $midnight = new DateTimeImmutable('tomorrow', $tz);
    return $midnight->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
}

/**
 * Get time remaining until the checklist deadline after a checklist date.
 * Easy closes at 1:00 AM next day; Intermediate at midnight.
 */
function getTimeUntilChecklistDeadline(string $timezone, string $userDate, ?string $challengeMode = 'easy'): array {
    $tz = new DateTimeZone($timezone);
    $now = new DateTimeImmutable('now', $tz);
    $hour = getChecklistDeadlineHour($challengeMode);
    $deadline = (new DateTimeImmutable(
        sprintf('%s %02d:00:00', $userDate, $hour),
        $tz
    ))->modify('+1 day');
    $seconds = max(0, $deadline->getTimestamp() - $now->getTimestamp());

    return [
        'hours' => intdiv($seconds, 3600),
        'minutes' => intdiv($seconds % 3600, 60),
        'seconds' => $seconds % 60,
    ];
}

/** Get the checklist deadline as a UTC ISO-8601 timestamp. */
function getChecklistDeadlineUtcIso(string $timezone, string $userDate, ?string $challengeMode = 'easy'): string {
    $tz = new DateTimeZone($timezone);
    $hour = getChecklistDeadlineHour($challengeMode);
    $deadline = (new DateTimeImmutable(
        sprintf('%s %02d:00:00', $userDate, $hour),
        $tz
    ))->modify('+1 day');
    return $deadline->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
}

/**
 * JSON response helper
 * @param array $data
 * @param int $statusCode
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect helper
 * @param string $url
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Check if request is AJAX
 * @return bool
 */
function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get POST data safely
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function post(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data safely
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get(string $key, mixed $default = null): mixed {
    return $_GET[$key] ?? $default;
}

/**
 * Flash message system
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function clearFlash(): void {
    unset($_SESSION['flash']);
}

/**
 * Get list of common timezones for select dropdown
 * @return array
 */
function getTimezoneList(): array {
    return [
        'America/New_York' => 'Eastern Time (ET)',
        'America/Chicago' => 'Central Time (CT)',
        'America/Denver' => 'Mountain Time (MT)',
        'America/Los_Angeles' => 'Pacific Time (PT)',
        'America/Anchorage' => 'Alaska Time (AKT)',
        'Pacific/Honolulu' => 'Hawaii Time (HT)',
        'America/Phoenix' => 'Arizona (No DST)',
        'America/Indiana/Indianapolis' => 'Indiana (Eastern)',
        'Europe/London' => 'London (GMT/BST)',
        'Europe/Paris' => 'Central European Time',
        'Asia/Tokyo' => 'Japan Standard Time',
        'Australia/Sydney' => 'Australian Eastern Time',
        'UTC' => 'UTC'
    ];
}

/**
 * Post a system message to a circle (for join notifications, milestones, etc.)
 * @param int $circleId
 * @param int $userId The user associated with the event
 * @param string $message The system message text
 * @param string $type 'system_join' or 'system_milestone'
 */
function postSystemMessage(int $circleId, int $userId, string $message, string $type = 'system_join'): void {
    dbQuery(
        "INSERT INTO circle_messages (circle_id, user_id, message, message_type, created_at_utc) 
         VALUES (?, ?, ?, ?, UTC_TIMESTAMP())",
        [$circleId, $userId, $message, $type]
    );
}

/**
 * Notify existing circle members when someone new joins.
 * Kept best-effort so notification failures never block joining a circle.
 */
function notifyCircleMembersOfJoin(int $circleId, int $joiningUserId, string $joiningName, string $circleName): void {
    if (!function_exists('pushTablesReady') || !function_exists('isPushConfigured') || !function_exists('sendPushToUsers')) {
        return;
    }

    if (!pushTablesReady() || !isPushConfigured()) {
        return;
    }

    $recipients = dbFetchAll(
        "SELECT DISTINCT icm.user_id
         FROM inner_circle_members icm
         JOIN push_subscriptions ps ON ps.user_id = icm.user_id
         WHERE icm.circle_id = ?
           AND icm.user_id <> ?
           AND ps.feed_enabled = 1",
        [$circleId, $joiningUserId]
    );

    $recipientIds = array_map(fn($row) => (int) $row['user_id'], $recipients);
    if (empty($recipientIds)) {
        return;
    }

    sendPushToUsers(
        $recipientIds,
        $joiningName . ' joined your circle',
        $joiningName . ' has joined ' . $circleName . '.',
        '/challenge/app/feed.php?circle=' . (int) $circleId
    );
}
