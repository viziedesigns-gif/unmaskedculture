<?php
header('Location: /challenge/app/forgot_password.php', true, 302);
exit;
session_start();

// Redirect if already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: ../app/dashboard.php");
    exit;
}

// Redirect if super admin is logged in
if(isset($_SESSION["is_super_admin"]) && $_SESSION["is_super_admin"] === true){
    header("location: ../super-admin/dashboard.php");
    exit;
}

$success_message = "";
$rate_limit_error = "";

// Check for messages from handler
if(isset($_GET['success']) && $_GET['success'] == '1'){
    $success_message = "If an account with that email exists, a password reset link has been sent. Please check your email.";
}
if(isset($_GET['error']) && $_GET['error'] == 'rate_limit'){
    $rate_limit_error = "Too many password reset requests. Please wait before trying again.";
}
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
    <title>Forgot Password - CompassionCloud</title>
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
            line-height: 1.5;
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
        
        .alert-success {
            padding: 12px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
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
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid var(--primary-color);
            padding: 12px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 13px;
            color: var(--text-dark);
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
            <h1>Forgot Password?</h1>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
        </div>
        
        <?php if(!empty($success_message)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($rate_limit_error)): ?>
            <div class="alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $rate_limit_error; ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i> <strong>Note:</strong> Password reset links expire after 15 minutes. You can request up to 5 reset emails per hour.
        </div>

        <form action="forgot_password_handler.php" method="post">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="your.email@example.com" required autofocus>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </div>
        </form>
        
        <a href="../CCloud.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>
<script><?php include __DIR__ . '/../includes/micro_interactions_script.php'; ?></script>
</body>
</html>

