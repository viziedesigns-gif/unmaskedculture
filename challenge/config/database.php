<?php
/**
 * Database Configuration
 * Kinto App
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'u389024941_challenge');
define('DB_USER', 'u389024941_mstull');
define('DB_PASS', 'Slekim1999!');
define('DB_CHARSET', 'utf8mb4');

// Site configuration
define('SITE_URL', 'https://unmaskedculture.org');
define('APP_URL', SITE_URL . '/app');
define('CHALLENGE_PATH', '/challenge');

// Session configuration
define('SESSION_NAME', 'unfiltered_challenge');
define('SESSION_LIFETIME', 86400 * 7); // 7 days

// Upload configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('PROFILE_PIC_UPLOAD_PATH', __DIR__ . '/../../uploads/profile-pictures/');
define('PROFILE_PIC_UPLOAD_URL', '/uploads/profile-pictures/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Timezone default
define('DEFAULT_TIMEZONE', 'America/New_York');

/** Bump when static assets or the service worker shell changes. */
define('APP_VERSION', '86.7');

/**
 * Get PDO database connection
 * @return PDO
 */
function getDbConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Execute a query with parameters
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function dbQuery(string $sql, array $params = []): PDOStatement {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Get single row from query
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function dbFetchOne(string $sql, array $params = []): ?array {
    $stmt = dbQuery($sql, $params);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get all rows from query
 * @param string $sql
 * @param array $params
 * @return array
 */
function dbFetchAll(string $sql, array $params = []): array {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Get last inserted ID
 * @return string
 */
function dbLastId(): string {
    return getDbConnection()->lastInsertId();
}
