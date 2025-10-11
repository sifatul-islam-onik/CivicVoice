<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$user = getCurrentUser();

try {
    // Ensure the notification belongs to the user
    $stmt = executeQuery("SELECT user_id FROM notifications WHERE id = ?", [$id]);
    $row = $stmt->fetch();
    if (!$row || $row['user_id'] != $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false]);
        exit;
    }

    markNotificationRead($id);
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
    exit;
}
