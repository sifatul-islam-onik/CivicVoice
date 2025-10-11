<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Run as CLI: php debug_create_notification.php
// Or visit in browser if your server is running

header('Content-Type: text/plain');

try {
    $pdo = getDbConnection();
    $res = $pdo->query("SHOW TABLES LIKE 'notifications'");
    $exists = $res && $res->rowCount() > 0;
    echo "notifications table exists: " . ($exists ? 'yes' : 'no') . PHP_EOL;
} catch (Exception $e) {
    echo "Error checking table existence: " . $e->getMessage() . PHP_EOL;
}

try {
    if ($exists) {
        $countStmt = executeQuery("SELECT COUNT(*) FROM notifications");
        $count = $countStmt->fetchColumn();
        echo "notifications count: " . $count . PHP_EOL;

        $user = getCurrentUser();
        $testUserId = $user ? $user['id'] : 1;

        echo "Using user id: " . $testUserId . PHP_EOL;

        $title = 'Debug test ' . time();
        $body = 'Debug body at ' . date('c');

        $ok = createNotification($testUserId, $title, $body);
        echo "createNotification returned: " . ($ok ? 'true' : 'false') . PHP_EOL;

        try {
            $last = executeQuery("SELECT * FROM notifications ORDER BY id DESC LIMIT 1")->fetch();
            echo "Last notification row:\n" . print_r($last, true) . PHP_EOL;
        } catch (Exception $e) {
            echo "Error fetching last notification: " . $e->getMessage() . PHP_EOL;
        }
    } else {
        echo "Table does not exist. Run the migration to create notifications table." . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error during test insert: " . $e->getMessage() . PHP_EOL;
}

// Also print the CREATE TABLE SQL that should be run
echo "\n-- If the table is missing, run this SQL in your database (phpMyAdmin or mysql CLI):\n";
echo "CREATE TABLE IF NOT EXISTS notifications (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    user_id INT NOT NULL,\n    title VARCHAR(255) NOT NULL,\n    body TEXT NULL,\n    is_read TINYINT(1) NOT NULL DEFAULT 0,\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";

?>