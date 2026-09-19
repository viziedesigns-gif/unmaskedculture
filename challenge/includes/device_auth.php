<?php
/** Persistent device credentials survive PHP session garbage collection. */
const DEVICE_AUTH_COOKIE = 'kinto_device';
const DEVICE_AUTH_DAYS = 90;

function ensureDeviceAuthTable(): void {
    static $ready = false;
    if ($ready) return;
    dbQuery("CREATE TABLE IF NOT EXISTS user_device_logins (
        selector CHAR(24) PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        auth_version INT UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        INDEX idx_device_user (user_id),
        INDEX idx_device_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function deviceCookieParts(): ?array {
    $value = $_COOKIE[DEVICE_AUTH_COOKIE] ?? '';
    if (!is_string($value) || !preg_match('/\A([a-f0-9]{24}):([a-f0-9]{64})\z/', $value, $matches)) return null;
    return [$matches[1], $matches[2]];
}

function setDeviceCookie(string $value, int $expires): void {
    setcookie(DEVICE_AUTH_COOKIE, $value, [
        'expires' => $expires, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    if ($value === '') unset($_COOKIE[DEVICE_AUTH_COOKIE]);
    else $_COOKIE[DEVICE_AUTH_COOKIE] = $value;
}

function forgetDeviceLogin(): void {
    $parts = deviceCookieParts();
    try {
        if ($parts) {
            ensureDeviceAuthTable();
            dbQuery('DELETE FROM user_device_logins WHERE selector = ? AND token_hash = ?', [$parts[0], hash('sha256', $parts[1])]);
        }
    } catch (Throwable $e) { error_log('Device sign-out storage unavailable'); }
    setDeviceCookie('', time() - 3600);
}

function rememberDeviceLogin(int $userId, int $authVersion): void {
    try {
        ensureDeviceAuthTable();
        forgetDeviceLogin();
        $selector = bin2hex(random_bytes(12));
        $token = bin2hex(random_bytes(32));
        $expires = time() + DEVICE_AUTH_DAYS * 86400;
        dbQuery('INSERT INTO user_device_logins (selector, user_id, token_hash, auth_version, expires_at) VALUES (?, ?, ?, ?, ?)',
            [$selector, $userId, hash('sha256', $token), $authVersion, gmdate('Y-m-d H:i:s', $expires)]);
        setDeviceCookie($selector . ':' . $token, $expires);
        dbQuery('DELETE FROM user_device_logins WHERE expires_at < UTC_TIMESTAMP()');
    } catch (Throwable $e) { error_log('Device sign-in could not be saved'); }
}

function restoreDeviceLogin(): void {
    static $attempted = false;
    if ($attempted || !empty($_SESSION['user_id'])) return;
    $attempted = true;
    $parts = deviceCookieParts();
    if (!$parts) return;
    try {
        ensureDeviceAuthTable();
        $device = dbFetchOne('SELECT d.*, u.auth_version AS current_auth_version FROM user_device_logins d JOIN users u ON u.id = d.user_id WHERE d.selector = ? AND d.expires_at > UTC_TIMESTAMP()', [$parts[0]]);
        if (!$device || !hash_equals($device['token_hash'], hash('sha256', $parts[1])) || (int) $device['auth_version'] !== (int) $device['current_auth_version']) {
            forgetDeviceLogin();
            return;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $device['user_id'];
        $_SESSION['auth_version'] = (int) $device['current_auth_version'];
        $_SESSION['login_time'] = time();
    } catch (Throwable $e) { error_log('Device sign-in restoration unavailable'); }
}
