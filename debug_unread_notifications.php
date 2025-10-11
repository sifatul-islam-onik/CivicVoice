<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';
header('Content-Type: text/plain');

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
if (!$userId) {
    $u = getCurrentUser();
    $userId = $u ? $u['id'] : null;
}

if (!$userId) {
    echo "No user id available. Provide ?user_id= or login to use current session.\n";
    exit;
}

try {
    $stmt = executeQuery("SELECT id, title, body, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$userId]);
    $rows = $stmt->fetchAll();
    echo "Unread notifications for user_id=$userId (total: " . count($rows) . "):\n\n";
    foreach ($rows as $r) {
        echo "id={$r['id']} is_read={$r['is_read']} created_at={$r['created_at']}\n";
        echo "title: {$r['title']}\n";
        echo "body: {$r['body']}\n";
        echo "----\n";
    }
} catch (Exception $e) {
    echo "Error fetching notifications: " . $e->getMessage() . "\n";
}

?>