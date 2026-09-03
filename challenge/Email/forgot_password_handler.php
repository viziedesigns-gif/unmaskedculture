<?php
header('Location: /challenge/app/forgot_password.php', true, 302);
exit;
/**
 * Forgot Password Handler
 * Processes password reset requests with rate limiting
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/email_service.php';

// Only allow POST requests
if($_SERVER["REQUEST_METHOD"] != "POST"){
    header("Location: forgot_password.php");
    exit;
}

// Validate email
$email = trim($_POST["email"] ?? '');

if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
    header("Location: forgot_password.php?error=invalid_email");
    exit;
}

// Create password_resets table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    user_type ENUM('regular', 'super_admin') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
@mysqli_query($link, $create_table_sql);

// Rate limiting: Check for recent requests (max 5 per hour)
$rate_limit_sql = "SELECT COUNT(*) as request_count FROM password_resets 
                   WHERE email = ? 
                   AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";

if($stmt = mysqli_prepare($link, $rate_limit_sql)){
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $request_count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if($request_count >= 5){
        header("Location: forgot_password.php?error=rate_limit");
        exit;
    }
}

// Search for user in both tables
$user_type = null;
$user_found = false;

// Check regular users table
$sql = "SELECT id, email FROM users WHERE email = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if(mysqli_stmt_num_rows($stmt) == 1){
        $user_found = true;
        $user_type = 'regular';
    }
    mysqli_stmt_close($stmt);
}

// Check super_admins table if not found
if(!$user_found){
    $check_table = @mysqli_query($link, "SHOW TABLES LIKE 'super_admins'");
    if($check_table && mysqli_num_rows($check_table) > 0){
        $sql = "SELECT id, email FROM super_admins WHERE email = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if(mysqli_stmt_num_rows($stmt) == 1){
                $user_found = true;
                $user_type = 'super_admin';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Always show success message for security (prevent email enumeration)
// But only send email if user actually exists
if($user_found && $user_type){
    // Generate secure token
    $token = bin2hex(random_bytes(32));
    
    // Set expiration (15 minutes from now)
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Insert token into database
    $sql = "INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, ?, ?)";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "ssss", $email, $token, $user_type, $expires_at);
        
        if(mysqli_stmt_execute($stmt)){
            // Generate reset link
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $base_path = dirname(dirname($_SERVER['PHP_SELF']));
            $reset_link = $protocol . "://" . $host . $base_path . "/Email/reset_password.php?token=" . $token;
            
            // Send email
            $emailService = new EmailService($link);
            $result = $emailService->sendPasswordResetEmail($email, $reset_link, $user_type, 15);
            
            if(!$result['success']){
                error_log("Failed to send password reset email to {$email}: " . $result['message']);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Always redirect with success message (security)
mysqli_close($link);
header("Location: forgot_password.php?success=1");
exit;
?>

