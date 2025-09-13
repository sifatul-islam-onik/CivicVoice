<?php
// Authentication helper functions for CivicVoice

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
}

// Get current user data
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

// Check if user has specific role
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return in_array($_SESSION['role'], $roles);
}

// Require login - redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $current_url = $_SERVER['REQUEST_URI'];
        header("Location: login.php?redirect=" . urlencode($current_url));
        exit();
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied. You don't have permission to access this page.");
    }
}

// Require any of the specified roles
function requireAnyRole($roles) {
    requireLogin();
    
    if (!hasAnyRole($roles)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied. You don't have permission to access this page.");
    }
}

// Create user session
function createUserSession($userId, $rememberMe = false) {
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    
    // Set expiration time
    $expiresAt = $rememberMe ? 
        date('Y-m-d H:i:s', strtotime('+30 days')) : 
        date('Y-m-d H:i:s', strtotime('+' . SESSION_LIFETIME . ' seconds'));
    
    // Store session in database
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
        
        // Set session cookie
        if ($rememberMe) {
            setcookie('session_token', $sessionToken, strtotime('+30 days'), '/', '', false, true);
        } else {
            setcookie('session_token', $sessionToken, 0, '/', '', false, true);
        }
        
        // Clean up expired sessions
        cleanupExpiredSessions();
        
    } catch (Exception $e) {
        error_log("Session creation error: " . $e->getMessage());
    }
}

// Validate session token
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

// Clean up expired sessions
function cleanupExpiredSessions() {
    try {
        executeQuery("DELETE FROM user_sessions WHERE expires_at < NOW()");
    } catch (Exception $e) {
        error_log("Session cleanup error: " . $e->getMessage());
    }
}

// Logout user
function logout() {
    // Remove session from database
    if (isset($_COOKIE['session_token'])) {
        try {
            executeQuery("DELETE FROM user_sessions WHERE session_token = ?", [$_COOKIE['session_token']]);
        } catch (Exception $e) {
            error_log("Session removal error: " . $e->getMessage());
        }
        
        // Clear session cookie
        setcookie('session_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Clear session variables
    $_SESSION = [];
    
    // Destroy session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Redirect to appropriate dashboard based on role
function redirectToDashboard() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    $redirectUrl = $_GET['redirect'] ?? '';
    
    if ($redirectUrl && filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
        // Local redirect
        header("Location: " . $redirectUrl);
        exit();
    }
    
    // Default redirects based on role - all roles use the same dashboard
    header("Location: dashboard.php");
    exit();
}

// Check and restore session from cookie
function checkAndRestoreSession() {
    if (!isLoggedIn() && isset($_COOKIE['session_token'])) {
        $user = validateSessionToken($_COOKIE['session_token']);
        
        if ($user) {
            // Restore session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
        } else {
            // Invalid token, clear cookie
            setcookie('session_token', '', time() - 3600, '/', '', false, true);
        }
    }
}

// Get user's full name or username
function getUserDisplayName() {
    if (!isLoggedIn()) {
        return 'Guest';
    }
    
    return $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get user avatar or default
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

// Check session on every page load
checkAndRestoreSession();
?>