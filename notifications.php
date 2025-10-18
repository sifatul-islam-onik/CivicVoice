<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

requireLogin();
$user = getCurrentUser();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    try {
        executeQuery("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$user['id']]);
        header('Location: notifications.php');
        exit;
    } catch (Exception $e) {
        $error = 'Failed to mark all as read.';
    }
}

// Handle marking a single notification as read (POST from this page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['id'])) {
    $nid = (int)$_POST['id'];
    try {
        // Use helper from includes to mark it read
        markNotificationRead($nid);
        // Redirect back to avoid resubmission and keep user on notifications page
        header('Location: notifications.php');
        exit;
    } catch (Exception $e) {
        $error = 'Failed to mark notification as read.';
    }
}

// Fetch notifications and total count
try {
    $stmt = executeQuery("SELECT SQL_CALC_FOUND_ROWS id, title, body, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?", [$user['id'], $perPage, $offset]);
    $notifications = $stmt->fetchAll();
    $total = executeQuery("SELECT FOUND_ROWS() AS total")->fetchColumn();
} catch (Exception $e) {
    $notifications = [];
    $total = 0;
}

$totalPages = (int)ceil($total / $perPage);

$page_title = 'Notifications - CivicVoice';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <header class="dashboard-header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo"><a href="dashboard.php">CivicVoice</a></div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                    <?php if (!hasRole('admin') && !hasAnyRole(['authority'])): ?>
                    <li class="nav-item"><a href="report.php" class="nav-link">Report Issue</a></li>
                    <?php endif; ?>
                    <?php if (!hasRole('admin')): ?>
                    <li class="nav-item"><a href="reports.php" class="nav-link">All reports</a></li>
                    <?php endif; ?>
                </ul>
                <div class="nav-user">
                    <div class="notification-area">
                        <?php 
                            $unreadCount = isset($civicVoiceService) ? (int)$civicVoiceService->countUnreadNotifications($user['id']) : (int)executeQuery("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$user['id']])->fetchColumn();
                            $unreads = getUnreadNotifications($user['id'], 4);
                        ?>
                        <button class="btn btn-small btn-notify" onclick="location.href='notifications.php'">🔔 <?php echo $unreadCount ? '<span class="notify-count">'.htmlspecialchars($unreadCount).'</span>' : ''; ?></button>
                    </div>
                    <div class="user-menu">
                        <span class="user-name"><?php echo htmlspecialchars(getUserDisplayName()); ?></span>
                        <span class="user-role">(<?php echo ucfirst($user['role']); ?>)</span>
                        <div class="user-dropdown">
                            <a href="profile.php">Profile</a>
                            <a href="logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main class="dashboard-main">
        <div class="dashboard-container">
            <div class="dashboard-welcome">
                <h1>Notifications</h1>
                <p>All activity related to your reports and account.</p>
            </div>

            <div class="stat-card" style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong>Total:</strong> <?php echo (int)$total; ?>
                </div>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button class="btn btn-primary">Mark all as read</button>
                </form>
            </div>

            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="report-card">No notifications</div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="report-card <?php echo !$n['is_read'] ? 'unread' : ''; ?>">
                            <div style="flex:1">
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                                    </div>
                                    <div class="meta" style="font-size:0.9em; color:#666"><?php echo date('M j, Y g:i A', strtotime($n['created_at'])); ?></div>
                                </div>
                                <div class="body" style="margin-top:8px"><?php echo nl2br(htmlspecialchars($n['body'])); ?></div>
                            </div>
                            <div class="actions">
                                <?php if (!$n['is_read']): ?>
                                    <form method="POST" action="notifications.php" style="display:inline">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                        <button class="btn btn-secondary">Mark read</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#888">Read</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div style="margin-top:16px">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="notifications.php?page=<?php echo $p; ?>" style="margin-right:8px; <?php if ($p === $page) echo 'font-weight:bold'; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
