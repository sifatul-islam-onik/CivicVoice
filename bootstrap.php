<?php

/**
 * Class Autoloader for CivicVoice
 * Automatically loads classes when they are used
 */
spl_autoload_register(function($className) {
    $classFile = __DIR__ . '/classes/' . $className . '.php';
    
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

/**
 * Bootstrap File for CivicVoice Object-Oriented System
 * This file initializes the class-based system while maintaining backward compatibility
 */

// Include original config for database connection and constants
require_once __DIR__ . '/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize services
$authService = null;
$userRepository = null;
$reportRepository = null;
$mapService = null;

/**
 * Get AuthService instance (singleton pattern)
 */
function getAuthService() {
    global $authService;
    if ($authService === null) {
        $authService = new AuthService();
    }
    return $authService;
}

/**
 * Get UserRepository instance (singleton pattern)
 */
function getUserRepository() {
    global $userRepository;
    if ($userRepository === null) {
        $userRepository = new UserRepository();
    }
    return $userRepository;
}

/**
 * Get ReportRepository instance (singleton pattern)
 */
function getReportRepository() {
    global $reportRepository;
    if ($reportRepository === null) {
        $reportRepository = new ReportRepository();
    }
    return $reportRepository;
}

/**
 * Get MapService instance (singleton pattern)
 */
function getMapService() {
    global $mapService;
    if ($mapService === null) {
        // You can set Google Maps API key here if available
        $apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : null;
        $mapService = new MapService($apiKey);
    }
    return $mapService;
}

// Backward compatibility functions to maintain existing functionality
// These functions wrap the new class-based system

/**
 * Check if user is logged in (backward compatibility)
 */
function isLoggedIn() {
    return getAuthService()->isLoggedIn();
}

/**
 * Get current user (backward compatibility)
 */
function getCurrentUser() {
    $user = getAuthService()->getCurrentUser();
    return $user ? $user->toArray() : null;
}

/**
 * Check if user has role (backward compatibility)
 */
function hasRole($role) {
    return getAuthService()->hasRole($role);
}

/**
 * Check if user has any role (backward compatibility)
 */
function hasAnyRole($roles) {
    return getAuthService()->hasAnyRole($roles);
}

/**
 * Require login (backward compatibility)
 */
function requireLogin() {
    getAuthService()->requireLogin();
}

/**
 * Require specific role (backward compatibility)
 */
function requireRole($role) {
    getAuthService()->requireRole($role);
}

/**
 * Require any role (backward compatibility)
 */
function requireAnyRole($roles) {
    getAuthService()->requireAnyRole($roles);
}

/**
 * Redirect to dashboard (backward compatibility)
 */
function redirectToDashboard() {
    getAuthService()->redirectToDashboard();
}

/**
 * Get user display name (backward compatibility)
 */
function getUserDisplayName() {
    $user = getAuthService()->getCurrentUser();
    return $user ? $user->getFullName() : 'Guest';
}

/**
 * Create user session (backward compatibility)
 */
function createUserSession($userId, $rememberMe = false) {
    // This function is now handled internally by AuthService::login()
    // Keeping for compatibility but it should not be called directly
    return true;
}

/**
 * Get unread notifications (backward compatibility)
 */
function getUnreadNotifications($userId, $limit = 10) {
    return Notification::getForUser($userId, $limit, true);
}

/**
 * Mark notification as read (backward compatibility)
 */
function markNotificationRead($notificationId) {
    $notification = Notification::findById($notificationId);
    if ($notification) {
        return $notification->markAsRead();
    }
    return false;
}

/**
 * Create notification (enhanced with class system)
 */
function createNotification($userId, $title, $body) {
    $notification = new Notification();
    $notification->setUserId($userId);
    $notification->setTitle($title);
    $notification->setBody($body);
    return $notification->save();
}

// Auto-restore session from cookie if available
if (!isLoggedIn() && isset($_COOKIE['session_token'])) {
    $user = getAuthService()->validateSessionToken($_COOKIE['session_token']);
    if ($user) {
        // Restore session variables
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['username'] = $user->getUsername();
        $_SESSION['full_name'] = $user->getFullName();
        $_SESSION['email'] = $user->getEmail();
        $_SESSION['role'] = $user->getRole();
        $_SESSION['logged_in'] = true;
    } else {
        // Invalid token, clear cookie
        setcookie('session_token', '', time() - 3600, '/', '', false, true);
    }
}