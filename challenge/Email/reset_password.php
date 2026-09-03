<?php
$legacyToken = rawurlencode((string) ($_GET['token'] ?? ''));
header('Location: /challenge/app/reset_password.php' . ($legacyToken !== '' ? '?token=' . $legacyToken : ''), true, 302);
exit;
session_start();
require_once __DIR__ . '/../config.php';

// Get token from URL
$token = trim($_GET['token'] ?? '');
$token_valid = false;
$token_error = "";
$email = "";
$user_type = "";

if(empty($token)){
    $token_error = "Invalid or missing password reset token.";
} else {
    // Validate token
    $sql = "SELECT email, user_type, expires_at, used FROM password_resets WHERE token = ?";
    
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) == 1){
            mysqli_stmt_bind_result($stmt, $email, $user_type, $expires_at, $used);
            mysqli_stmt_fetch($stmt);
            
            // Check if token is already used
            if($used){
                $token_error = "This password reset link has already been used. Please request a new one.";
            }
            // Check if token is expired
            elseif(strtotime($expires_at) < time()){
                $token_error = "This password reset link has expired. Password reset links are valid for 15 minutes only.";
            }
            else {
                $token_valid = true;
            }
        } else {
            $token_error = "Invalid password reset token. Please check your link or request a new one.";
        }
        
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-NHSZ9V9D');</script>
    <!-- End Google Tag Manager -->
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-BBEQYLCZDX"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-BBEQYLCZDX');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - CompassionCloud</title>
    <!-- Favicon Links -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png">
    <link rel="manifest" href="../site.webmanifest">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4A90E2;
            --cloud-white: #F7F9FC;
            --secondary-color: #FF6F61;
            --slate-gray: #2F3A4A;
            --text-dark: #2F3A4A;
            --text-light: #666666;
            --white: #FFFFFF;
        }
        
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: var(--cloud-white);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            background: var(--white);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        
        .header {
            margin-bottom: 30px;
        }
        
        .logo {
            height: 200px;
            width: auto;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        .header h1 {
            color: var(--slate-gray);
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .header p {
            color: var(--text-light);
            margin: 0;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--slate-gray);
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #3a7bc8;
        }
        
        .alert-danger {
            padding: 12px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
        }
        
        .requirements {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 13px;
        }
        
        .requirements h4 {
            margin: 0 0 8px 0;
            color: var(--slate-gray);
            font-size: 14px;
        }
        
        .requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .requirements li {
            margin: 4px 0;
            color: var(--text-dark);
        }
        
        .password-field {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            padding: 5px;
        }
        
        .back-link {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: var(--primary-color);
        }

        .error-container {
            text-align: center;
        }

        .error-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .container {
                padding: 30px 20px;
            }

            .logo {
                height: 140px;
            }

            .header h1 {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 15px;
            }

            .logo {
                height: 100px;
            }

            .header h1 {
                font-size: 18px;
            }
        }
<?php include __DIR__ . '/../includes/micro_interactions_styles.php'; ?>
</style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NHSZ9V9D"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <div class="container">
        <div class="header">
            <img src="../CClogo2.png" alt="CompassionCloud" class="logo">
            <h1>Reset Your Password</h1>
            <?php if($token_valid): ?>
                <p>Enter your new password below.</p>
            <?php endif; ?>
        </div>
        
        <?php if(!$token_valid): ?>
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-danger">
                    <strong>Error:</strong> <?php echo htmlspecialchars($token_error); ?>
                </div>
                <p style="color: var(--text-light); margin: 20px 0;">
                    Need to reset your password? Request a new reset link below.
                </p>
                <a href="forgot_password.php" class="btn-primary" style="display: inline-block; text-decoration: none;">
                    <i class="fas fa-redo"></i> Request New Reset Link
                </a>
            </div>
        <?php else: ?>
            <div class="requirements">
                <h4><i class="fas fa-lock"></i> Password Requirements:</h4>
                <ul>
                    <li>At least 8 characters long</li>
                    <li>Contains at least one uppercase letter (A-Z)</li>
                    <li>Contains at least one lowercase letter (a-z)</li>
                    <li>Contains at least one number (0-9)</li>
                </ul>
            </div>

            <form action="reset_password_handler.php" method="post" id="resetForm">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" class="form-control" required style="padding-right: 45px;">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', 'eyeIcon1')">
                            <i class="fas fa-eye" id="eyeIcon1"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required style="padding-right: 45px;">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', 'eyeIcon2')">
                            <i class="fas fa-eye" id="eyeIcon2"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> Reset Password
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <a href="../CCloud.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>
    
    <script>
        function togglePassword(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            
            if(field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Client-side validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if(password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match. Please try again.');
                return false;
            }
            
            // Check password requirements
            if(password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            if(!/[A-Z]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter.');
                return false;
            }
            
            if(!/[a-z]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one lowercase letter.');
                return false;
            }
            
            if(!/[0-9]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one number.');
                return false;
            }
        });
    </script>
<script><?php include __DIR__ . '/../includes/micro_interactions_script.php'; ?></script>
</body>
</html>

