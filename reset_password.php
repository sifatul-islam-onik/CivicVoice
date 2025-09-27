<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

// This page is now deprecated - redirect to the new OTP-based forgot password flow
// Show a message if there's a legacy token in URL
$legacyToken = $_GET['token'] ?? '';
$showLegacyMessage = !empty($legacyToken);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - CivicVoice</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>🔐 New Security System</h1>
                <p>We've upgraded to a more secure OTP-based password reset system</p>
            </div>
            
            <?php if ($showLegacyMessage): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <strong>Old reset link detected!</strong> We've upgraded our security system. 
                Please use the new OTP-based password reset below.
            </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3><i class="fas fa-mobile-alt"></i> How the New System Works</h3>
                <ol>
                    <li><strong>Enter your email</strong> - We'll verify your account</li>
                    <li><strong>Receive OTP code</strong> - Check your email for a 6-digit code</li>
                    <li><strong>Enter OTP</strong> - Verify the code to proceed</li>
                    <li><strong>Set new password</strong> - Create your new secure password</li>
                </ol>
            </div>
            
            <div class="benefits-box">
                <h4><i class="fas fa-star"></i> Why OTP is Better</h4>
                <ul>
                    <li><i class="fas fa-check"></i> <strong>More secure</strong> - Codes expire in 10 minutes</li>
                    <li><i class="fas fa-check"></i> <strong>No broken links</strong> - No URL complications</li>
                    <li><i class="fas fa-check"></i> <strong>Mobile friendly</strong> - Easy to copy from email</li>
                    <li><i class="fas fa-check"></i> <strong>Better protection</strong> - Harder to intercept</li>
                </ul>
            </div>
            
            <a href="forgot_password.php" class="btn btn-primary btn-full">
                <i class="fas fa-arrow-right"></i>
                Start OTP Password Reset
            </a>
            
            <div class="auth-footer">
                <p>Remember your password? <a href="login.php">Back to Login</a></p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <style>
        .info-box, .benefits-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-box h3, .benefits-box h4 {
            color: #495057;
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .info-box ol {
            margin: 0;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .benefits-box ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .benefits-box li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .benefits-box .fas.fa-check {
            color: #28a745;
            margin-right: 8px;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        .btn-full {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
    
    <script>
        // Auto redirect after 8 seconds if legacy token is present
        <?php if ($showLegacyMessage): ?>
        setTimeout(function() {
            window.location.href = 'forgot_password.php';
        }, 8000);
        <?php endif; ?>
    </script>
</body>
</html>