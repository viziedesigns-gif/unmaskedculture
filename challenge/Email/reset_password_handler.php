<?php
$legacyToken = rawurlencode((string) ($_POST['token'] ?? ''));
header('Location: /challenge/app/reset_password.php' . ($legacyToken !== '' ? '?token=' . $legacyToken : ''), true, 302);
exit;
/**
 * Reset Password Handler
 * Processes password reset form submission and updates the password
 */

session_start();
require_once __DIR__ . '/../config.php';

// Only allow POST requests
if($_SERVER["REQUEST_METHOD"] != "POST"){
    header("Location: ../CCloud.php");
    exit;
}

// Get form data
$token = trim($_POST["token"] ?? '');
$password = trim($_POST["password"] ?? '');
$confirm_password = trim($_POST["confirm_password"] ?? '');

// Validate inputs
$errors = [];

if(empty($token)){
    $errors[] = "Invalid token";
}

if(empty($password)){
    $errors[] = "Password is required";
}

if(empty($confirm_password)){
    $errors[] = "Please confirm your password";
}

if($password !== $confirm_password){
    $errors[] = "Passwords do not match";
}

// Validate password requirements
if(strlen($password) < 8){
    $errors[] = "Password must be at least 8 characters long";
}

if(!preg_match('/[A-Z]/', $password)){
    $errors[] = "Password must contain at least one uppercase letter";
}

if(!preg_match('/[a-z]/', $password)){
    $errors[] = "Password must contain at least one lowercase letter";
}

if(!preg_match('/[0-9]/', $password)){
    $errors[] = "Password must contain at least one number";
}

// If validation errors, redirect back to reset form
if(!empty($errors)){
    $error_msg = implode(", ", $errors);
    header("Location: reset_password.php?token=" . urlencode($token) . "&error=" . urlencode($error_msg));
    exit;
}

// Verify token
$sql = "SELECT email, user_type, expires_at, used FROM password_resets WHERE token = ?";
$token_valid = false;
$email = "";
$user_type = "";

if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if(mysqli_stmt_num_rows($stmt) == 1){
        mysqli_stmt_bind_result($stmt, $email, $user_type, $expires_at, $used);
        mysqli_stmt_fetch($stmt);
        
        // Check if token is valid
        if(!$used && strtotime($expires_at) >= time()){
            $token_valid = true;
        }
    }
    mysqli_stmt_close($stmt);
}

// If token is not valid, redirect with error
if(!$token_valid){
    header("Location: reset_password.php?token=" . urlencode($token) . "&error=invalid_token");
    exit;
}

// Hash the new password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Update password in the appropriate table
$update_success = false;

if($user_type === 'regular'){
    // Update in users table
    $sql = "UPDATE users SET password = ? WHERE email = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $email);
        if(mysqli_stmt_execute($stmt)){
            $update_success = true;
        }
        mysqli_stmt_close($stmt);
    }
} 
elseif($user_type === 'super_admin'){
    // Update in super_admins table
    $sql = "UPDATE super_admins SET password_hash = ? WHERE email = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $email);
        if(mysqli_stmt_execute($stmt)){
            $update_success = true;
        }
        mysqli_stmt_close($stmt);
    }
}

if($update_success){
    // Mark this token as used
    $sql = "UPDATE password_resets SET used = TRUE WHERE token = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Invalidate all other unused tokens for this email
    $sql = "UPDATE password_resets SET used = TRUE WHERE email = ? AND token != ? AND used = FALSE";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "ss", $email, $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Log the password change
    error_log("Password reset successful for user: {$email} (type: {$user_type})");
    
    // Redirect to login with success message
    mysqli_close($link);
    header("Location: ../CCloud.php?password_reset=success");
    exit;
} else {
    // Update failed
    error_log("Password reset failed for user: {$email} (type: {$user_type})");
    mysqli_close($link);
    header("Location: reset_password.php?token=" . urlencode($token) . "&error=update_failed");
    exit;
}
?>

