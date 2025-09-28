<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
$success = '';
$email = '';
$step = 'email'; // 'email' or 'otp' or 'password'
$otpToken = '';
$validatedOTP = ''; // Store the validated OTP code

// Handle different steps of the process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        $step = $_POST['step'];
    }
    
    // Restore data from previous steps
    if (isset($_POST['email'])) $email = trim($_POST['email']);
    if (isset($_POST['otp_token'])) $otpToken = $_POST['otp_token'];
    if (isset($_POST['validated_otp'])) $validatedOTP = $_POST['validated_otp'];
    
    // Step 1: Email submission
    if ($step === 'email') {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Check if email exists in database
                $stmt = executeQuery(
                    "SELECT id, username, full_name, email FROM users WHERE email = ? AND is_active = 1",
                    [$email]
                );
                
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate OTP
                    $otpResult = generatePasswordResetOTP($user['id'], $user['email']);
                    
                    if ($otpResult['success']) {
                        $success = 'A 6-digit OTP code has been sent to your email. Please check your inbox and enter the code below.';
                        $step = 'otp';
                        $otpToken = $otpResult['token'];
                        // Keep email for the next step
                    } else {
                        $error = 'Unable to send OTP. Please try again later.';
                    }
                } else {
                    // Don't reveal that email doesn't exist - show success message anyway
                    $success = 'If an account with this email exists, you will receive an OTP code shortly.';
                    // Don't proceed to OTP step for non-existent users
                    $email = '';
                }
            } catch (Exception $e) {
                $error = 'Unable to process password reset request. Please try again later.';
                error_log("Forgot password error: " . $e->getMessage());
            }
        }
    }
    
    // Step 2: OTP verification
    elseif ($step === 'otp') {
        $email = trim($_POST['email'] ?? '');
        $otpCode = trim($_POST['otp_code'] ?? '');
        $otpToken = $_POST['otp_token'] ?? '';
        
        if (empty($email) || empty($otpCode)) {
            $error = 'Please enter both your email and OTP code.';
        } elseif (strlen($otpCode) !== 6 || !is_numeric($otpCode)) {
            $error = 'OTP code must be exactly 6 digits.';
        } else {
            try {
                $otpResult = validatePasswordResetOTP($email, $otpCode);
                
                if ($otpResult['valid']) {
                    $success = 'OTP verified! Please enter your new password below.';
                    $step = 'password';
                    // Keep email and store the validated OTP code for password reset
                    $otpToken = $otpResult['token'];
                    $validatedOTP = $otpCode; // Store the validated OTP code
                } else {
                    $error = $otpResult['message'];
                    // Stay on OTP step
                }
            } catch (Exception $e) {
                $error = 'Unable to verify OTP. Please try again.';
                error_log("OTP verification error: " . $e->getMessage());
            }
        }
    }
    
    // Step 3: Password reset
    elseif ($step === 'password') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $otpToken = $_POST['otp_token'] ?? '';
        $validatedOTP = $_POST['validated_otp'] ?? ''; // Get the validated OTP code
        
        if (empty($password) || empty($confirmPassword)) {
            $error = 'Please enter and confirm your new password.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } else {
            try {
                if (empty($validatedOTP)) {
                    $error = 'OTP code missing. Please start over.';
                    $step = 'email';
                    $email = '';
                } else {
                    // Reset password using OTP validation
                    $resetResult = resetPasswordWithOTP($email, $validatedOTP, $password);
                    
                    if ($resetResult['success']) {
                        $success = 'Password reset successfully! You can now log in with your new password.';
                        $step = 'complete';
                        $email = '';
                        $otpToken = '';
                        $validatedOTP = '';
                    } else {
                        $error = $resetResult['message'];
                    }
                }
            } catch (Exception $e) {
                $error = 'Unable to reset password. Please try again.';
                error_log("Password reset error: " . $e->getMessage());
            }
        }
    }
}

$page_title = "Forgot Password - CivicVoice";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>
                    <?php if ($step === 'email'): ?>
                        Forgot Password
                    <?php elseif ($step === 'otp'): ?>
                        Enter OTP Code
                    <?php elseif ($step === 'password'): ?>
                        Set New Password
                    <?php else: ?>
                        Password Reset Complete
                    <?php endif; ?>
                </h1>
                <p>
                    <?php if ($step === 'email'): ?>
                        Enter your email address and we'll send you a secure OTP code
                    <?php elseif ($step === 'otp'): ?>
                        Check your email for the 6-digit verification code
                    <?php elseif ($step === 'password'): ?>
                        Enter your new password below
                    <?php else: ?>
                        Your password has been reset successfully
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 'email'): ?>
            <!-- Step 1: Email Input -->
            <form method="POST" class="auth-form">
                <input type="hidden" name="step" value="email">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($email); ?>"
                            placeholder="Enter your email address"
                            required 
                            autocomplete="email"
                        >
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Send OTP Code
                </button>
            </form>
            
            <?php elseif ($step === 'otp'): ?>
            <!-- Step 2: OTP Verification -->
            <form method="POST" class="auth-form">
                <input type="hidden" name="step" value="otp">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="otp_token" value="<?php echo htmlspecialchars($otpToken); ?>">
                
                <div class="form-group">
                    <label for="email_display">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input 
                            type="email" 
                            id="email_display" 
                            value="<?php echo htmlspecialchars($email); ?>"
                            readonly 
                            class="readonly"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="otp_code">6-Digit OTP Code</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="text" 
                            id="otp_code" 
                            name="otp_code" 
                            placeholder="Enter 6-digit code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required 
                            autocomplete="off"
                            style="font-family: monospace; font-size: 18px; text-align: center; letter-spacing: 3px;"
                        >
                    </div>
                    <small class="form-help">
                        <i class="fas fa-info-circle"></i>
                        Code expires in 10 minutes. Check your spam folder if you don't see the email.
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-shield-alt"></i>
                    Verify OTP Code
                </button>
                
                <button type="submit" name="step" value="email" class="btn btn-secondary" style="margin-top: 10px;">
                    <i class="fas fa-arrow-left"></i>
                    Resend Code
                </button>
            </form>
            
            <?php elseif ($step === 'password'): ?>
            <!-- Step 3: New Password -->
            <form method="POST" class="auth-form">
                <input type="hidden" name="step" value="password">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="otp_token" value="<?php echo htmlspecialchars($otpToken); ?>">
                <input type="hidden" name="validated_otp" value="<?php echo htmlspecialchars($validatedOTP ?? ''); ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter new password"
                            required 
                            minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                            autocomplete="new-password"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Confirm new password"
                            required 
                            minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                            autocomplete="new-password"
                        >
                    </div>
                </div>
                
                <div class="forget-password-requirements password-requirements">
                    <small>Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i>
                    Reset Password
                </button>
            </form>
            
            <?php else: ?>
            <!-- Step 4: Success -->
            <div class="success-message">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 20px;"></i>
                <h3>Password Reset Successful!</h3>
                <p>Your password has been changed successfully. You can now log in with your new password.</p>
                
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Go to Login
                </a>
            </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>
                    <?php if ($step === 'complete'): ?>
                        <a href="index.php">← Back to Home</a>
                    <?php else: ?>
                        Remember your password? <a href="login.php">Back to Login</a>
                    <?php endif; ?>
                </p>
                <?php if ($step !== 'complete'): ?>
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p><a href="index.php">← Back to Home</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .readonly {
            background-color: #f8f9fa !important;
            cursor: not-allowed;
        }
        .success-message {
            text-align: center;
            padding: 30px;
        }
        .success-message h3 {
            color: #28a745;
            margin-bottom: 15px;
        }
        .password-requirements {
            margin-top: -10px;
            margin-bottom: 20px;
        }
        .password-requirements small {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
            border-color: #5a6268;
        }
    </style>
    
    <script>
        // Auto-focus handling for different steps
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            const otpField = document.getElementById('otp_code');
            const passwordField = document.getElementById('password');
            
            if (otpField) {
                otpField.focus();
                // Auto-format OTP input
                otpField.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            } else if (passwordField) {
                passwordField.focus();
            } else if (emailField && !emailField.disabled && !emailField.readOnly) {
                emailField.focus();
            }
            
            // Password confirmation validation
            const confirmField = document.getElementById('confirm_password');
            if (passwordField && confirmField) {
                confirmField.addEventListener('blur', function() {
                    if (passwordField.value && confirmField.value && 
                        passwordField.value !== confirmField.value) {
                        this.setCustomValidity('Passwords do not match');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
        
        // Form submission handling
        document.querySelectorAll('.auth-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]:not([name])');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    // Re-enable if form submission fails
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    }, 10000);
                }
            });
        });
    </script>
</body>
</html>