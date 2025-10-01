<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
$page_title = "Dashboard - CivicVoice";

// Handle admin actions for authority management
if (hasRole('admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_authority':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $phone = trim($_POST['phone']) ?: null;
                $password = $_POST['password'];
                
                if (!empty($username) && !empty($email) && !empty($full_name) && !empty($password)) {
                    try {
                        // Check if username or email already exists
                        $checkStmt = executeQuery("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
                        if ($checkStmt->fetch()) {
                            $error = "Username or email already exists.";
                        } else {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
                            executeQuery(
                                "INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, email_verified) VALUES (?, ?, ?, ?, ?, 'authority', 1, 1)",
                                [$username, $email, $passwordHash, $full_name, $phone]
                            );
                            $success = "Authority account created successfully!";
                        }
                    } catch (Exception $e) {
                        $error = "Failed to create authority account.";
                    }
                }
                break;
                
            case 'toggle_authority_status':
                $userId = (int)$_POST['user_id'];
                $newStatus = $_POST['status'] === 'active' ? 1 : 0;
                
                try {
                    executeQuery("UPDATE users SET is_active = ? WHERE id = ? AND role = 'authority'", [$newStatus, $userId]);
                    $success = "Authority status updated successfully!";
                } catch (Exception $e) {
                    $error = "Failed to update authority status.";
                }
                break;
                
            case 'delete_authority':
                $userId = (int)$_POST['user_id'];
                
                try {
                    // First check if this authority has any report updates
                    $hasUpdates = executeQuery("SELECT COUNT(*) FROM status_updates WHERE updated_by_user_id = ?", [$userId])->fetchColumn();
                    
                    if ($hasUpdates > 0) {
                        $error = "Cannot delete authority account with existing report updates. Deactivate instead.";
                    } else {
                        executeQuery("DELETE FROM users WHERE id = ? AND role = 'authority'", [$userId]);
                        $success = "Authority account deleted successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Failed to delete authority account.";
                }
                break;
        }
    }
}

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
    // Admin stats - comprehensive system analytics
    $totalUsers = executeQuery("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalAuthorities = executeQuery("SELECT COUNT(*) FROM users WHERE role = 'authority'")->fetchColumn();
    $activeAuthorities = executeQuery("SELECT COUNT(*) FROM users WHERE role = 'authority' AND is_active = 1")->fetchColumn();
    $totalCitizens = executeQuery("SELECT COUNT(*) FROM users WHERE role = 'citizen'")->fetchColumn();
    $totalReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
    $activeIssues = executeQuery("SELECT COUNT(*) FROM reports WHERE status IN ('pending', 'in-progress')")->fetchColumn();
    $resolvedThisMonth = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'fixed' AND MONTH(updated_at) = MONTH(CURRENT_DATE())")->fetchColumn();
    $avgResolutionTime = executeQuery("SELECT ROUND(AVG(DATEDIFF(updated_at, created_at)), 1) FROM reports WHERE status = 'fixed'")->fetchColumn() ?: 0;
    
    // Category breakdown
    $categoryStats = executeQuery("SELECT category, COUNT(*) as count FROM reports GROUP BY category")->fetchAll();
    
    // Monthly report trends (last 6 months)
    $monthlyTrends = executeQuery("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM reports 
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ")->fetchAll();
    
    // Authority performance
    $authorityPerformance = executeQuery("
        SELECT u.full_name, u.username, 
               COUNT(su.id) as updates_made,
               COUNT(DISTINCT su.report_id) as reports_handled,
               u.is_active
        FROM users u 
        LEFT JOIN status_updates su ON u.id = su.updated_by_user_id 
        WHERE u.role = 'authority' 
        GROUP BY u.id, u.full_name, u.username, u.is_active
        ORDER BY updates_made DESC
    ")->fetchAll();
    
    // Fetch all authorities for management
    $authorities = executeQuery("
        SELECT id, username, email, full_name, phone, is_active, created_at,
               (SELECT COUNT(*) FROM status_updates WHERE updated_by_user_id = users.id) as total_updates
        FROM users 
        WHERE role = 'authority' 
        ORDER BY created_at DESC
    ")->fetchAll();
}

// Fetch latest 4 reports from the database (but not for admin role since they shouldn't see issues)
if (!hasRole('admin')) {
    $stmt = executeQuery(
        "SELECT r.*, u.full_name AS reporter
         FROM reports r
         JOIN users u ON r.user_id = u.id
         ORDER BY r.created_at DESC
         LIMIT 4"
    );
    $recentReports = $stmt->fetchAll();
}
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
                    <?php if (!hasRole('admin')): ?>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">All Reports</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item">
                        <a href="#authorities" class="nav-link" onclick="showSection('authorities')">Manage Authorities</a>
                    </li>
                    <li class="nav-item">
                        <a href="#analytics" class="nav-link" onclick="showSection('analytics')">System Analytics</a>
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
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="admin-dashboard">
                    <!-- System Overview Stats -->
                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <h3>Total Users</h3>
                            <span class="stat-number"><?php echo $totalUsers; ?></span>
                            <div class="stat-detail">
                                Citizens: <?php echo $totalCitizens; ?> | Authorities: <?php echo $totalAuthorities; ?>
                            </div>
                        </div>
                        <div class="stat-card">
                            <h3>Active Authorities</h3>
                            <span class="stat-number"><?php echo $activeAuthorities; ?></span>
                            <div class="stat-detail">
                                Out of <?php echo $totalAuthorities; ?> total
                            </div>
                        </div>
                        <div class="stat-card">
                            <h3>System Reports</h3>
                            <span class="stat-number"><?php echo $totalReports; ?></span>
                            <div class="stat-detail">
                                <?php echo $activeIssues; ?> active issues
                            </div>
                        </div>
                        <div class="stat-card">
                            <h3>Avg Resolution</h3>
                            <span class="stat-number"><?php echo $avgResolutionTime; ?></span>
                            <div class="stat-detail">
                                days average
                            </div>
                        </div>
                    </div>

                    <!-- Admin Navigation Tabs -->
                    <div class="admin-tabs">
                        <button class="tab-button active" onclick="showSection('overview')">📊 Overview</button>
                        <button class="tab-button" onclick="showSection('authorities')">👥 Manage Authorities</button>
                        <button class="tab-button" onclick="showSection('analytics')">📈 System Analytics</button>
                    </div>

                    <!-- Overview Section -->
                    <div id="overview-section" class="admin-section active">
                        <h2>System Overview</h2>
                        
                        <!-- Quick Stats Grid -->
                        <div class="overview-grid">
                            <div class="overview-card">
                                <h3>📊 Report Categories</h3>
                                <div class="category-stats">
                                    <?php foreach ($categoryStats as $cat): ?>
                                        <div class="category-item">
                                            <span class="category-name"><?php echo ucfirst($cat['category']); ?></span>
                                            <span class="category-count"><?php echo $cat['count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="overview-card">
                                <h3>📈 Monthly Trends</h3>
                                <div class="trends-chart">
                                    <?php foreach ($monthlyTrends as $trend): ?>
                                        <div class="trend-item">
                                            <span class="trend-month"><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></span>
                                            <span class="trend-count"><?php echo $trend['count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="overview-card">
                                <h3>⚡ Authority Performance</h3>
                                <div class="performance-list">
                                    <?php foreach (array_slice($authorityPerformance, 0, 5) as $auth): ?>
                                        <div class="performance-item">
                                            <span class="auth-name"><?php echo htmlspecialchars($auth['full_name']); ?></span>
                                            <span class="auth-stats"><?php echo $auth['reports_handled']; ?> reports</span>
                                            <span class="auth-status <?php echo $auth['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $auth['is_active'] ? '✅' : '❌'; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Authority Management Section -->
                    <div id="authorities-section" class="admin-section">
                        <h2>Authority Management</h2>
                        
                        <!-- Add New Authority Form -->
                        <div class="authority-form-container">
                            <h3>➕ Create New Authority Account</h3>
                            <form method="POST" class="authority-form">
                                <input type="hidden" name="action" value="create_authority">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="username">Username:</label>
                                        <input type="text" id="username" name="username" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email:</label>
                                        <input type="email" id="email" name="email" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="full_name">Full Name:</label>
                                        <input type="text" id="full_name" name="full_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Phone (Optional):</label>
                                        <input type="tel" id="phone" name="phone">
                                    </div>
                                    <div class="form-group">
                                        <label for="password">Password:</label>
                                        <input type="password" id="password" name="password" required minlength="8">
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Create Authority</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Existing Authorities Table -->
                        <div class="authorities-table-container">
                            <h3>📋 Existing Authority Accounts</h3>
                            <div class="table-responsive">
                                <table class="authorities-table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Reports Handled</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($authorities as $authority): ?>
                                            <tr class="<?php echo $authority['is_active'] ? '' : 'inactive-row'; ?>">
                                                <td><?php echo htmlspecialchars($authority['username']); ?></td>
                                                <td><?php echo htmlspecialchars($authority['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($authority['email']); ?></td>
                                                <td><?php echo htmlspecialchars($authority['phone'] ?: '-'); ?></td>
                                                <td><?php echo $authority['total_updates']; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $authority['is_active'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $authority['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($authority['created_at'])); ?></td>
                                                <td class="table-actions">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_authority_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $authority['id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $authority['is_active'] ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" class="btn btn-small <?php echo $authority['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                            <?php echo $authority['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                    <?php if ($authority['total_updates'] == 0): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this authority account?');">
                                                            <input type="hidden" name="action" value="delete_authority">
                                                            <input type="hidden" name="user_id" value="<?php echo $authority['id']; ?>">
                                                            <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Section -->
                    <div id="analytics-section" class="admin-section">
                        <h2>System Analytics & Reports</h2>
                        
                        <div class="analytics-grid">
                            <!-- System Performance Metrics -->
                            <div class="analytics-card">
                                <h3>📊 System Performance</h3>
                                <div class="metrics-list">
                                    <div class="metric-item">
                                        <span class="metric-label">Total Reports:</span>
                                        <span class="metric-value"><?php echo $totalReports; ?></span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label">Resolved This Month:</span>
                                        <span class="metric-value"><?php echo $resolvedThisMonth; ?></span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label">Average Resolution Time:</span>
                                        <span class="metric-value"><?php echo $avgResolutionTime; ?> days</span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label">Active Authorities:</span>
                                        <span class="metric-value"><?php echo $activeAuthorities; ?>/<?php echo $totalAuthorities; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Authority Performance Analytics -->
                            <div class="analytics-card">
                                <h3>👥 Authority Performance Analytics</h3>
                                <div class="performance-analytics">
                                    <?php foreach ($authorityPerformance as $auth): ?>
                                        <div class="authority-performance-item">
                                            <div class="auth-info">
                                                <span class="auth-name"><?php echo htmlspecialchars($auth['full_name']); ?></span>
                                                <span class="auth-username">(<?php echo htmlspecialchars($auth['username']); ?>)</span>
                                            </div>
                                            <div class="auth-metrics">
                                                <span class="metric">Reports: <?php echo $auth['reports_handled']; ?></span>
                                                <span class="metric">Updates: <?php echo $auth['updates_made']; ?></span>
                                                <span class="status <?php echo $auth['is_active'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $auth['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- System Health & Statistics -->
                            <div class="analytics-card">
                                <h3>🔧 System Health</h3>
                                <div class="health-indicators">
                                    <div class="health-item">
                                        <span class="health-indicator good">✅</span>
                                        <span class="health-label">Database Connection</span>
                                    </div>
                                    <div class="health-item">
                                        <span class="health-indicator good">✅</span>
                                        <span class="health-label">Email System</span>
                                    </div>
                                    <div class="health-item">
                                        <span class="health-indicator <?php echo $activeAuthorities > 0 ? 'good' : 'warning'; ?>">
                                            <?php echo $activeAuthorities > 0 ? '✅' : '⚠️'; ?>
                                        </span>
                                        <span class="health-label">Authority Coverage</span>
                                    </div>
                                    <div class="health-item">
                                        <span class="health-indicator good">✅</span>
                                        <span class="health-label">User Authentication</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!hasRole('admin')): ?>
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
                                <?php if (hasAnyRole(['authority'])): ?>
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
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
    <script>
        // Dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            <?php if (hasRole('admin')): ?>
            initializeAdminDashboard();
            <?php endif; ?>
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
            
            <?php if (!hasRole('admin')): ?>
            // Add click handlers for report cards (only for non-admin users)
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
            <?php endif; ?>
        }

        <?php if (hasRole('admin')): ?>
        function initializeAdminDashboard() {
            // Set default active section
            showSection('overview');
            
            // Add form validation for authority creation
            const authorityForm = document.querySelector('.authority-form');
            if (authorityForm) {
                authorityForm.addEventListener('submit', function(e) {
                    const password = this.querySelector('#password').value;
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long.');
                        return false;
                    }
                });
            }
        }

        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.admin-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected section
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
            }
            
            // Add active class to corresponding tab
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
        <?php endif; ?>

        <?php if (hasRole('authority')): ?>
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
        <?php endif; ?>

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
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Welcome message for new users
        <?php if (isset($_GET['welcome'])): ?>
        setTimeout(() => {
            showNotification('Welcome to CivicVoice! Start by exploring community reports or submitting a new issue.', 'success');
        }, 1000);
        <?php endif; ?>

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>