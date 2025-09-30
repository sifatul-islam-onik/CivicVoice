<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$env = parse_ini_file(__DIR__ . '/.env');

// Database config
define('DB_HOST', $env['DB_HOST']);
define('DB_USERNAME', $env['DB_USERNAME']);
define('DB_PASSWORD', $env['DB_PASSWORD']);
define('DB_NAME', $env['DB_NAME']);

// App config
define('SITE_URL', $env['SITE_URL']);
define('UPLOAD_DIR', $env['UPLOAD_DIR']);
define('MAX_FILE_SIZE', $env['MAX_FILE_SIZE']);
define('ALLOWED_IMAGE_TYPES', explode(',', $env['ALLOWED_IMAGE_TYPES']));

// Security
define('SESSION_LIFETIME', $env['SESSION_LIFETIME']);
define('PASSWORD_MIN_LENGTH', $env['PASSWORD_MIN_LENGTH']);
define('BCRYPT_COST', $env['BCRYPT_COST']);

// Mail config
define('MAIL_HOST', $env['MAIL_HOST']);
define('MAIL_PORT', $env['MAIL_PORT']);
define('MAIL_USERNAME', $env['MAIL_USERNAME']);
define('MAIL_PASSWORD', $env['MAIL_PASSWORD']);
define('MAIL_FROM_EMAIL', $env['MAIL_FROM_EMAIL']);
define('MAIL_FROM_NAME', $env['MAIL_FROM_NAME']);
define('MAIL_ENCRYPTION', $env['MAIL_ENCRYPTION']);

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
        // Show the real error for debugging
        throw new Exception($e->getMessage());
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