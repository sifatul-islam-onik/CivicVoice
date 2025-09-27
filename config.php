<?php
// Database configuration for CivicVoice (comment changed)

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'civicvoice');

// Application configuration
define('SITE_URL', 'http://localhost/CivicVoice');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Security configuration
define('SESSION_LIFETIME', 7200); // 2 hours
define('PASSWORD_MIN_LENGTH', 6);
define('BCRYPT_COST', 12);

// Email configuration - Gmail SMTP
define('MAIL_HOST', 'smtp.gmail.com');        // Gmail SMTP server
define('MAIL_PORT', 587);                     // Gmail SMTP port (587 for TLS, 465 for SSL)
define('MAIL_USERNAME', 'lostlink.dev@gmail.com'); // Your Gmail address
define('MAIL_PASSWORD', 'kcrneiuyrhpzwxuk');     // Gmail App Password (NOT regular password)
define('MAIL_FROM_EMAIL', 'lostlink.dev@gmail.com'); // Same as username
define('MAIL_FROM_NAME', 'CivicVoice Support');
define('MAIL_ENCRYPTION', 'tls');             // Use 'tls' for port 587, 'ssl' for port 465

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Log error and return user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Function to get database connection
function getDbConnection() {
    global $pdo;
    return $pdo;
}

// Function to execute queries safely
function executeQuery($query, $params = []) {
    try {
        $stmt = getDbConnection()->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        throw new Exception("Database operation failed");
    }
}

// Function to generate username from full name
function generateUsername($fullName) {
    // Remove special characters and convert to lowercase
    $username = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $fullName));
    
    // Replace spaces with underscores
    $username = str_replace(' ', '_', $username);
    
    // Remove extra spaces/underscores
    $username = preg_replace('/_{2,}/', '_', $username);
    $username = trim($username, '_');
    
    // Check if username exists and add number if needed
    $originalUsername = $username;
    $counter = 1;
    
    while (usernameExists($username)) {
        $username = $originalUsername . '_' . $counter;
        $counter++;
    }
    
    return $username;
}

// Function to check if username exists
function usernameExists($username) {
    try {
        $stmt = executeQuery("SELECT id FROM users WHERE username = ?", [$username]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if email exists
function emailExists($email) {
    try {
        $stmt = executeQuery("SELECT id FROM users WHERE email = ?", [$email]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Error reporting (disable in production)
if (defined('DEVELOPMENT') && DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone settings
date_default_timezone_set('Asia/Dhaka');
?>