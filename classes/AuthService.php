<?php

require_once __DIR__ . '/User.php';

/**
 * AuthService Class
 * 
 * Handles authentication, authorization, session management, 
 * and password reset functionality for the CivicVoice system.
 */
class AuthService {
    private $pdo;
    private $sessionKey = 'civicvoice_user';
    private $rememberTokenLength = 32;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        
        // Session is already started in config.php, no need to start it again
    }
    
    /**
     * Authenticate user with username/email and password
     * 
     * @param string $usernameOrEmail Username or email address
     * @param string $password Plain text password
     * @param bool $remember Whether to create remember token
     * @return array Result array with success status and user data
     */
    public function authenticate(string $usernameOrEmail, string $password, bool $remember = false): array {
        try {
            // Find user by username or email
            $user = $this->findUserByUsernameOrEmail($usernameOrEmail);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'user' => null
                ];
            }
            
            // Check if account is active
            if (!$user->isActive()) {
                return [
                    'success' => false,
                    'message' => 'Account is deactivated',
                    'user' => null
                ];
            }
            
            // Verify password
            if (!$user->verifyPassword($password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'user' => null
                ];
            }
            
            // Authentication successful
            $this->createSession($user);
            
            // Create remember token if requested
            if ($remember) {
                $this->createRememberToken($user);
            }
            
            return [
                'success' => true,
                'message' => 'Authentication successful',
                'user' => $user
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Authentication error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }
    
    /**
     * Register a new user
     * 
     * @param array $userData User registration data
     * @return array Result array with success status and user data
     */
    public function register(array $userData): array {
        try {
            // Create user object
            $user = new User($userData);
            
            // Validate user data
            $errors = $user->validate();
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'message' => implode(', ', $errors),
                    'user' => null
                ];
            }
            
            // Check if username already exists
            if ($this->usernameExists($user->getUsername())) {
                return [
                    'success' => false,
                    'message' => 'Username already exists',
                    'user' => null
                ];
            }
            
            // Check if email already exists
            if ($this->emailExists($user->getEmail())) {
                return [
                    'success' => false,
                    'message' => 'Email already exists',
                    'user' => null
                ];
            }
            
            // Hash password if not already hashed
            if (!empty($userData['password'])) {
                $user->setPassword($userData['password']);
            }
            
            // Insert user into database
            $userId = $this->insertUser($user);
            $user->setId($userId);
            
            // Create session for new user
            $this->createSession($user);
            
            return [
                'success' => true,
                'message' => 'Registration successful',
                'user' => $user
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Registration error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }
    
    /**
     * Get current authenticated user
     * 
     * @return User|null Current user or null if not authenticated
     */
    public function getCurrentUser(): ?User {
        // Check session
        if (isset($_SESSION[$this->sessionKey])) {
            $userData = $_SESSION[$this->sessionKey];
            return User::fromDatabaseRow($userData);
        }
        
        // Check remember token
        if (isset($_COOKIE['remember_token'])) {
            $user = $this->getUserByRememberToken($_COOKIE['remember_token']);
            if ($user) {
                $this->createSession($user);
                return $user;
            }
        }
        
        return null;
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool True if user is logged in
     */
    public function isLoggedIn(): bool {
        return $this->getCurrentUser() !== null;
    }
    
    /**
     * Log out current user
     * 
     * @return bool True on successful logout
     */
    public function logout(): bool {
        try {
            // Remove legacy session variables
            unset($_SESSION['user_id']);
            unset($_SESSION['username']);
            unset($_SESSION['full_name']);
            unset($_SESSION['email']);
            unset($_SESSION['role']);
            unset($_SESSION['logged_in']);
            
            // Remove new session data
            unset($_SESSION[$this->sessionKey]);
            
            // Remove remember token
            if (isset($_COOKIE['remember_token'])) {
                $this->deleteRememberToken($_COOKIE['remember_token']);
                setcookie('remember_token', '', time() - 3600, '/');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Logout error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change user password
     * 
     * @param User $user User object
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Result array with success status
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array {
        try {
            // Verify current password
            if (!$user->verifyPassword($currentPassword)) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Validate new password
            if (strlen($newPassword) < 6) {
                return [
                    'success' => false,
                    'message' => 'New password must be at least 6 characters long'
                ];
            }
            
            // Update password
            $user->setPassword($newPassword);
            $this->updateUserPassword($user);
            
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error changing password: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialize password reset process
     * 
     * @param string $email Email address
     * @return array Result array with success status
     */
    public function initiatePasswordReset(string $email): array {
        try {
            $user = $this->findUserByEmail($email);
            
            if (!$user) {
                // Don't reveal if email exists or not for security
                return [
                    'success' => true,
                    'message' => 'If the email exists, a reset link has been sent'
                ];
            }
            
            // Generate OTP and token
            $otp = sprintf('%06d', mt_rand(0, 999999));
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token
            $this->storePasswordResetToken($user->getId(), $email, $otp, $token, $expiresAt);
            
            // TODO: Send email with OTP (integrate with email service)
            // For now, return the OTP for testing purposes
            return [
                'success' => true,
                'message' => 'Reset code sent to your email',
                'otp' => $otp, // Remove this in production
                'token' => $token
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error initiating password reset: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify password reset OTP
     * 
     * @param string $email Email address
     * @param string $otp OTP code
     * @return array Result array with success status and token
     */
    public function verifyPasswordResetOTP(string $email, string $otp): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT token, expires_at, attempts 
                FROM password_reset_tokens 
                WHERE email = ? AND otp_code = ? AND used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$email, $otp]);
            $record = $stmt->fetch();
            
            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ];
            }
            
            // Check attempt limit
            if ($record['attempts'] >= 3) {
                return [
                    'success' => false,
                    'message' => 'Too many attempts. Please request a new OTP'
                ];
            }
            
            // Increment attempts
            $this->incrementOTPAttempts($email, $otp);
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'token' => $record['token']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error verifying OTP: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Reset password with token
     * 
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return array Result array with success status
     */
    public function resetPassword(string $token, string $newPassword): array {
        try {
            // Validate new password
            if (strlen($newPassword) < 6) {
                return [
                    'success' => false,
                    'message' => 'Password must be at least 6 characters long'
                ];
            }
            
            // Find valid token
            $stmt = $this->pdo->prepare("
                SELECT user_id, email 
                FROM password_reset_tokens 
                WHERE token = ? AND used = 0 AND expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $record = $stmt->fetch();
            
            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ];
            }
            
            // Get user and update password
            $user = $this->findUserById($record['user_id']);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            $user->setPassword($newPassword);
            $this->updateUserPassword($user);
            
            // Mark token as used
            $this->markPasswordResetTokenAsUsed($token);
            
            return [
                'success' => true,
                'message' => 'Password reset successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error resetting password: ' . $e->getMessage()
            ];
        }
    }
    
    // Private helper methods
    
    private function findUserByUsernameOrEmail(string $usernameOrEmail): ?User {
        $stmt = $this->pdo->prepare("
            SELECT * FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    private function findUserByEmail(string $email): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    private function findUserById(int $id): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    private function usernameExists(string $username): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function emailExists(string $email): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function insertUser(User $user): int {
        $data = $user->toDatabaseArray();
        unset($data['id']); // Remove ID for insert
        
        $sql = "INSERT INTO users (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    private function updateUserPassword(User $user): void {
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$user->getPasswordHash(), $user->getId()]);
    }
    
    private function createSession(User $user): void {
        // Store user data in the format expected by existing auth functions
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['username'] = $user->getUsername();
        $_SESSION['full_name'] = $user->getFullName();
        $_SESSION['email'] = $user->getEmail();
        $_SESSION['role'] = $user->getRole();
        $_SESSION['logged_in'] = true;
        
        // Also store in our format for the new system
        $_SESSION[$this->sessionKey] = $user->toArray(true);
    }
    
    private function createRememberToken(User $user): void {
        $token = bin2hex(random_bytes($this->rememberTokenLength));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Store in database
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user->getId(),
            $token,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        // Set cookie
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
    }
    
    private function getUserByRememberToken(string $token): ?User {
        $stmt = $this->pdo->prepare("
            SELECT u.* FROM users u
            JOIN user_sessions us ON u.id = us.user_id
            WHERE us.session_token = ? AND us.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    private function deleteRememberToken(string $token): void {
        $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$token]);
    }
    
    private function storePasswordResetToken(int $userId, string $email, string $otp, string $token, string $expiresAt): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, email, otp_code, token, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $email, $otp, $token, $expiresAt]);
    }
    
    private function incrementOTPAttempts(string $email, string $otp): void {
        $stmt = $this->pdo->prepare("
            UPDATE password_reset_tokens 
            SET attempts = attempts + 1 
            WHERE email = ? AND otp_code = ?
        ");
        $stmt->execute([$email, $otp]);
    }
    
    private function markPasswordResetTokenAsUsed(string $token): void {
        $stmt = $this->pdo->prepare("
            UPDATE password_reset_tokens 
            SET used = 1, used_at = NOW() 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
    }
    
    /**
     * Clean expired sessions and reset tokens
     * 
     * @return int Number of records cleaned
     */
    public function cleanExpiredTokens(): int {
        $cleaned = 0;
        
        // Clean expired sessions
        $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
        $cleaned += $stmt->rowCount();
        
        // Clean expired password reset tokens (older than 24 hours)
        $stmt = $this->pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $cleaned += $stmt->rowCount();
        
        return $cleaned;
    }
    
    /**
     * Get active sessions for a user
     * 
     * @param int $userId User ID
     * @return array Array of active sessions
     */
    public function getActiveSessions(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT id, ip_address, user_agent, created_at, expires_at
            FROM user_sessions 
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Revoke all sessions for a user
     * 
     * @param int $userId User ID
     * @return int Number of sessions revoked
     */
    public function revokeAllSessions(int $userId): int {
        $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
}
