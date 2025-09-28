<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role']
    ];
}

function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return in_array($_SESSION['role'], $roles);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $current_url = $_SERVER['REQUEST_URI'];
        header("Location: login.php?redirect=" . urlencode($current_url));
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied. You don't have permission to access this page.");
    }
}

function requireAnyRole($roles) {
    requireLogin();
    
    if (!hasAnyRole($roles)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied. You don't have permission to access this page.");
    }
}

function createUserSession($userId, $rememberMe = false) {
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = $rememberMe ? 
        date('Y-m-d H:i:s', strtotime('+30 days')) : 
        date('Y-m-d H:i:s', strtotime('+' . SESSION_LIFETIME . ' seconds'));

    try {
        executeQuery(
            "INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
            [
                $userId,
                $sessionToken,
                $expiresAt,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );

        if ($rememberMe) {
            setcookie('session_token', $sessionToken, strtotime('+30 days'), '/', '', false, true);
        } else {
            setcookie('session_token', $sessionToken, 0, '/', '', false, true);
        }

        cleanupExpiredSessions();
        
    } catch (Exception $e) {
        error_log("Session creation error: " . $e->getMessage());
    }
}

function validateSessionToken($token) {
    try {
        $stmt = executeQuery(
            "SELECT u.id, u.username, u.email, u.full_name, u.role, u.is_active 
             FROM user_sessions s 
             JOIN users u ON s.user_id = u.id 
             WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1",
            [$token]
        );
        
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

function cleanupExpiredSessions() {
    try {
        executeQuery("DELETE FROM user_sessions WHERE expires_at < NOW()");
    } catch (Exception $e) {
        error_log("Session cleanup error: " . $e->getMessage());
    }
}

function logout() {
    if (isset($_COOKIE['session_token'])) {
        try {
            executeQuery("DELETE FROM user_sessions WHERE session_token = ?", [$_COOKIE['session_token']]);
        } catch (Exception $e) {
            error_log("Session removal error: " . $e->getMessage());
        }
        setcookie('session_token', '', time() - 3600, '/', '', false, true);
    }
    
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function redirectToDashboard() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    $redirectUrl = $_GET['redirect'] ?? '';
    
    if ($redirectUrl && filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
        header("Location: " . $redirectUrl);
        exit();
    }

    header("Location: dashboard.php");
    exit();
}

function checkAndRestoreSession() {
    if (!isLoggedIn() && isset($_COOKIE['session_token'])) {
        $user = validateSessionToken($_COOKIE['session_token']);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
        } else {
            setcookie('session_token', '', time() - 3600, '/', '', false, true);
        }
    }
}

function getUserDisplayName() {
    if (!isLoggedIn()) {
        return 'Guest';
    }
    
    return $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
}


function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getUserAvatar($userId = null) {
    if (!$userId && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return 'assets/images/default-avatar.png';
    }
    
    $avatarPath = "uploads/avatars/{$userId}.jpg";
    if (file_exists($avatarPath)) {
        return $avatarPath;
    }
    
    return 'assets/images/default-avatar.png';
}

checkAndRestoreSession();

function generatePasswordResetOTP($userId, $email) {
    try {
        $otpCode = sprintf('%06d', random_int(100000, 999999));
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        executeQuery(
            "UPDATE password_reset_tokens SET used = 1, used_at = NOW() WHERE user_id = ? AND used = 0",
            [$userId]
        );
        
        executeQuery(
            "INSERT INTO password_reset_tokens (user_id, email, otp_code, token, expires_at) VALUES (?, ?, ?, ?, ?)",
            [$userId, $email, $otpCode, $token, $expiresAt]
        );
        
        $emailResult = sendPasswordResetOTP($email, $otpCode, $userId);
        
        if ($emailResult['success']) {
            return [
                'success' => true,
                'message' => 'OTP sent successfully.',
                'token' => $token, // Session token for verification
                'expires_minutes' => 10
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send OTP email.'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Password reset OTP generation error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Unable to generate password reset OTP.'
        ];
    }
}

function validatePasswordResetOTP($email, $otpCode) {
    if (empty($email) || empty($otpCode)) {
        return [
            'valid' => false,
            'message' => 'Email and OTP code are required.',
            'user_id' => null
        ];
    }

    try {
        // Find valid OTP token
        $stmt = executeQuery(
            "SELECT id, user_id, token, expires_at, attempts FROM password_reset_tokens 
             WHERE email = ? AND otp_code = ? AND used = 0 AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1",
            [$email, $otpCode]
        );

        $resetToken = $stmt->fetch();

        if (!$resetToken) {
            // Check if there's an expired or used token
            $stmt = executeQuery(
                "SELECT id FROM password_reset_tokens WHERE email = ? AND otp_code = ? 
                 ORDER BY created_at DESC LIMIT 1",
                [$email, $otpCode]
            );
            
            if ($stmt->fetch()) {
                return [
                    'valid' => false,
                    'message' => 'OTP code has expired or already been used.',
                    'user_id' => null
                ];
            } else {
                // Increment attempts for rate limiting
                executeQuery(
                    "UPDATE password_reset_tokens SET attempts = attempts + 1 
                     WHERE email = ? AND used = 0 AND expires_at > NOW()",
                    [$email]
                );
                
                return [
                    'valid' => false,
                    'message' => 'Invalid OTP code.',
                    'user_id' => null
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'OTP code is valid.',
            'user_id' => $resetToken['user_id'],
            'token' => $resetToken['token'],
            'reset_id' => $resetToken['id']
        ];

    } catch (Exception $e) {
        error_log("Password reset OTP validation error: " . $e->getMessage());
        return [
            'valid' => false,
            'message' => 'Unable to validate OTP code.',
            'user_id' => null
        ];
    }
}

function resetPasswordWithOTP($email, $otpCode, $newPassword) {
    try {
        $stmt = executeQuery(
            "SELECT id, user_id, token, expires_at FROM password_reset_tokens 
             WHERE email = ? AND otp_code = ? AND used = 0 AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1",
            [$email, $otpCode]
        );

        $resetToken = $stmt->fetch();

        if (!$resetToken) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP code. Please request a new one.'
            ];
        }
        
        $userId = $resetToken['user_id'];
        $resetId = $resetToken['id'];
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
        
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        
        try {
            executeQuery(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                [$passwordHash, $userId]
            );

            executeQuery(
                "UPDATE password_reset_tokens SET used = 1, used_at = NOW() WHERE id = ?",
                [$resetId]
            );
            
            executeQuery(
                "DELETE FROM user_sessions WHERE user_id = ?",
                [$userId]
            );
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Password reset successfully.'
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Unable to reset password. Please try again.'
        ];
    }
}

function cleanupExpiredResetTokens() {
    try {
        executeQuery(
            "DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = 1",
            []
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Cleanup expired reset tokens error: " . $e->getMessage());
        return false;
    }
}

function sendPasswordResetOTP($email, $otpCode, $userId) {
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - CivicVoice';

        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Reset OTP</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">🔐 Password Reset OTP</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">CivicVoice Security Code</p>
            </div>
            
            <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef; border-top: none;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <div style="background: white; display: inline-block; padding: 20px 40px; border-radius: 10px; border: 2px solid #007bff; margin: 20px 0;">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Your OTP Code</div>
                        <div style="font-size: 36px; font-weight: bold; color: #007bff; letter-spacing: 5px; font-family: monospace;">' . $otpCode . '</div>
                    </div>
                </div>
                
                <p><strong>Hello,</strong></p>
                
                <p>You requested a password reset for your CivicVoice account. Use the OTP code above to reset your password.</p>
                
                <div style="background: white; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <h3 style="margin-top: 0; color: #dc3545;">⏰ Important Security Information</h3>
                    <ul style="margin: 0;">
                        <li><strong>This OTP expires in 10 minutes</strong></li>
                        <li>Use it on the password reset page</li>
                        <li>Don\'t share this code with anyone</li>
                        <li>If you didn\'t request this, please ignore this email</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . SITE_URL . '/forgot_password.php" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Go to Password Reset Page</a>
                </div>
                
                <p style="color: #666; font-size: 14px; margin-top: 30px;">
                    This email was sent from CivicVoice password reset system.
                    <br>If you didn\'t request this password reset, please ignore this email or contact support.
                </p>
            </div>
        </body>
        </html>';

        $mail->AltBody = "CivicVoice Password Reset OTP\n\nYour OTP Code: " . $otpCode . "\n\nThis code expires in 10 minutes.\nEnter this code on the password reset page to continue.\n\nIf you didn't request this password reset, please ignore this email.\n\nGo to: " . SITE_URL . "/forgot_password.php";

        $mail->send();
        
        return [
            'success' => true,
            'message' => 'OTP email sent successfully.'
        ];
        
    } catch (Exception $e) {
        error_log("Password reset OTP email error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send OTP email: ' . $e->getMessage()
        ];
    }
}

?>
