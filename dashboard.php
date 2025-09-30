<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
$page_title = "Dashboard - CivicVoice";

// Fetch stats from the database
if (hasRole('citizen')) {
    // Citizen stats
    $myReports = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ?", [$user['id']])->fetchColumn();
    $pending = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'pending'", [$user['id']])->fetchColumn();
    $inProgress = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'in-progress'", [$user['id']])->fetchColumn();
    $fixed = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'fixed'", [$user['id']])->fetchColumn();
} elseif (hasRole('authority')) {
    // Authority stats
    $totalReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
    $pending = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
    $inProgress = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'in-progress'")->fetchColumn();
    $resolvedToday = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'fixed' AND DATE(updated_at) = CURDATE()")->fetchColumn();
} elseif (hasRole('admin')) {
    // Admin stats
    $totalUsers = executeQuery("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
    $activeIssues = executeQuery("SELECT COUNT(*) FROM reports WHERE status IN ('pending', 'in-progress')")->fetchColumn();
    $systemHealth = 'Good';
}

// Fetch latest 4 reports from the database
$stmt = executeQuery(
    "SELECT r.*, u.full_name AS reporter
     FROM reports r
     JOIN users u ON r.user_id = u.id
     ORDER BY r.created_at DESC
     LIMIT 4"
);
$recentReports = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <header class="dashboard-header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="dashboard.php">CivicVoice</a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">Dashboard</a>
                    </li>
                    <?php if (hasAnyRole(['citizen', 'authority'])): ?>
                    <li class="nav-item">
                        <a href="report.php" class="nav-link">Report Issue</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">All Reports</a>
                    </li>
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item">
                        <a href="admin/users.php" class="nav-link">Manage Users</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="nav-user">
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
                <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
                <p>You are logged in as a <strong><?php echo ucfirst($user['role']); ?></strong></p>
            </div>

            <?php if (hasRole('citizen')): ?>
                <!-- Citizen Dashboard -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>My Reports</h3>
                        <span class="stat-number"><?php echo $myReports; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>Pending</h3>
                        <span class="stat-number"><?php echo $pending; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>In Progress</h3>
                        <span class="stat-number"><?php echo $inProgress; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>Fixed</h3>
                        <span class="stat-number"><?php echo $fixed; ?></span>
                    </div>
                </div>

                <div class="dashboard-actions">
                    <a href="report.php" class="btn btn-primary">📍 Report New Issue</a>
                    <a href="reports.php?user=me" class="btn btn-secondary">📋 View My Reports</a>
                    <a href="reports.php" class="btn btn-secondary">🌍 Browse Community Reports</a>
                </div>

            <?php elseif (hasRole('authority')): ?>
                <!-- Authority Dashboard -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>Total Reports</h3>
                        <span class="stat-number"><?php echo $totalReports; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Review</h3>
                        <span class="stat-number pending"><?php echo $pending; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>In Progress</h3>
                        <span class="stat-number in-progress"><?php echo $inProgress; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>Resolved Today</h3>
                        <span class="stat-number fixed"><?php echo $resolvedToday; ?></span>
                    </div>
                </div>

                <div class="dashboard-actions">
                    <a href="reports.php?status=pending" class="btn btn-primary">⚠️ Review Pending Reports</a>
                    <a href="reports.php" class="btn btn-secondary">📊 View All Reports</a>
                    <a href="report.php" class="btn btn-secondary">📍 Report Issue</a>
                </div>

            <?php elseif (hasRole('admin')): ?>
                <!-- Admin Dashboard -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <span class="stat-number"><?php echo $totalUsers; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>Total Reports</h3>
                        <span class="stat-number"><?php echo $totalReports; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>Active Issues</h3>
                        <span class="stat-number pending"><?php echo $activeIssues; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>System Health</h3>
                        <span class="stat-number health">✅</span>
                    </div>
                </div>

                <div class="dashboard-actions">
                    <a href="admin/users.php" class="btn btn-primary">👥 Manage Users</a>
                    <a href="reports.php" class="btn btn-secondary">📊 System Reports</a>
                    <a href="admin/settings.php" class="btn btn-secondary">⚙️ System Settings</a>
                </div>
            <?php endif; ?>

            <div class="recent-activity">
                <h2>Recent Community Reports</h2>
                <div class="reports-grid">
                    <?php foreach ($recentReports as $report): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <h3><?php echo htmlspecialchars($report['title']); ?></h3>
                                <span class="status-badge status-<?php echo $report['status']; ?>">
                                    <?php echo ucfirst(str_replace('-', ' ', $report['status'])); ?>
                                </span>
                            </div>
                            <div class="report-meta">
                                <div class="report-category">
                                    <span class="category-icon">
                                        <?php 
                                        $icons = [
                                            'streetlight' => '💡',
                                            'pothole' => '🕳️',
                                            'garbage' => '🗑️',
                                            'traffic' => '🚦'
                                        ];
                                        echo $icons[$report['category']] ?? '📍';
                                        ?>
                                    </span>
                                    <?php echo ucfirst($report['category']); ?>
                                </div>
                                <div class="report-location">
                                    📍 <?php echo htmlspecialchars($report['location']); ?>
                                </div>
                                <div class="report-time">
                                    🕒 <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?>
                                </div>
                                <div class="report-reporter">
                                    👤 Reported by <?php echo htmlspecialchars($report['reporter']); ?>
                                </div>
                            </div>
                            <div class="report-actions">
                                <a href="reports.php?id=<?php echo $report['id']; ?>" class="btn btn-small btn-secondary">View Details</a>
                                <?php if (hasAnyRole(['authority', 'admin'])): ?>
                                    <button class="btn btn-small btn-primary" onclick="updateStatus(<?php echo $report['id']; ?>)">Update Status</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="activity-footer">
                    <a href="reports.php" class="btn btn-secondary">View All Reports</a>
                    <?php if (hasRole('citizen')): ?>
                        <a href="report.php" class="btn btn-primary">Report New Issue</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (hasAnyRole(['authority', 'admin'])): ?>
            <!-- Quick Actions Panel -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="actions-grid">
                    <div class="action-card" onclick="filterReports('pending')">
                        <div class="action-icon">⚠️</div>
                        <h3>Review Pending</h3>
                        <p><?php echo $pending ?? 8; ?> reports waiting</p>
                    </div>
                    <div class="action-card" onclick="filterReports('in-progress')">
                        <div class="action-icon">🔄</div>
                        <h3>Track Progress</h3>
                        <p><?php echo $inProgress ?? 10; ?> in progress</p>
                    </div>
                    <div class="action-card" onclick="showMap()">
                        <div class="action-icon">🗺️</div>
                        <h3>View Map</h3>
                        <p>See all locations</p>
                    </div>
                    <div class="action-card" onclick="generateReport()">
                        <div class="action-icon">📊</div>
                        <h3>Generate Report</h3>
                        <p>Export statistics</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
    <script>
        // Dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
        });

        function initializeDashboard() {
            console.log('Dashboard initialized for <?php echo $user['role']; ?>');
            
            // Add hover effects to stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add click handlers for report cards
            document.querySelectorAll('.report-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
                        // Get report ID from the view details link
                        const viewLink = this.querySelector('a[href*="reports.php"]');
                        if (viewLink) {
                            window.location.href = viewLink.href;
                        }
                    }
                });
            });
        }

        function updateStatus(reportId) {
            const newStatus = prompt('Enter new status (pending, in-progress, fixed):');
            if (newStatus && ['pending', 'in-progress', 'fixed'].includes(newStatus)) {
                // Simulate status update
                alert('Status updated to: ' + newStatus);
                // In real implementation, this would make an AJAX call
                location.reload();
            } else if (newStatus) {
                alert('Invalid status. Please use: pending, in-progress, or fixed');
            }
        }

        function filterReports(status) {
            window.location.href = 'reports.php?status=' + status;
        }

        function showMap() {
            alert('Map view would be implemented here with real geolocation data');
        }

        function generateReport() {
            alert('Report generation feature would be implemented here');
        }

        // Auto-refresh functionality (every 30 seconds)
        setInterval(function() {
            // In real implementation, this would fetch updated data
            console.log('Auto-refreshing dashboard data...');
        }, 30000);

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            // Style the notification
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                z-index: 1000;
                animation: slideIn 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Welcome message for new users
        <?php if (isset($_GET['welcome'])): ?>
        setTimeout(() => {
            showNotification('Welcome to CivicVoice! Start by exploring community reports or submitting a new issue.', 'success');
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>