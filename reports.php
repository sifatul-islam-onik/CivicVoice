<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
$page_title = "Community Reports - CivicVoice";

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$userFilter = $_GET['user'] ?? '';

// Build SQL query with filters
$sql = "SELECT r.*, u.full_name AS reporter, u.email AS reporter_email
        FROM reports r
        JOIN users u ON r.user_id = u.id
        WHERE 1";
$params = [];

if ($statusFilter) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}
if ($categoryFilter) {
    $sql .= " AND r.category = ?";
    $params[] = $categoryFilter;
}
if ($userFilter === 'me') {
    $sql .= " AND u.id = ?";
    $params[] = $user['id'];
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = executeQuery($sql, $params);
$reports = $stmt->fetchAll();

// Calculate statistics
$totalReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
$pendingCount = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$inProgressCount = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'in-progress'")->fetchColumn();
$fixedCount = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'fixed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/reports.css">
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
                        <a href="dashboard.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="report.php" class="nav-link ">Report Issue</a>
                    </li>
                    <?php if (hasAnyRole(['citizen', 'authority'])): ?>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link active">All reports</a>
                    </li>
                    <?php endif; ?>
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

    <main class="reports-main">
        <div class="reports-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Community Reports</h1>
                <p>Track and manage community issues across the city</p>
            </div>

            <!-- Statistics Overview -->
            <div class="reports-stats">
                <div class="stat-card">
                    <h3>Total Reports</h3>
                    <span class="stat-number"><?php echo $totalReports; ?></span>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <span class="stat-number pending"><?php echo $pendingCount; ?></span>
                </div>
                <div class="stat-card">
                    <h3>In Progress</h3>
                    <span class="stat-number in-progress"><?php echo $inProgressCount; ?></span>
                </div>
                <div class="stat-card">
                    <h3>Fixed</h3>
                    <span class="stat-number fixed"><?php echo $fixedCount; ?></span>
                </div>
            </div>

            <!-- Filters -->
            <div class="reports-filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in-progress" <?php echo $statusFilter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="fixed" <?php echo $statusFilter === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="category">Category:</label>
                        <select name="category" id="category">
                            <option value="">All Categories</option>
                            <option value="streetlight" <?php echo $categoryFilter === 'streetlight' ? 'selected' : ''; ?>>Streetlight</option>
                            <option value="pothole" <?php echo $categoryFilter === 'pothole' ? 'selected' : ''; ?>>Pothole</option>
                            <option value="garbage" <?php echo $categoryFilter === 'garbage' ? 'selected' : ''; ?>>Garbage</option>
                            <option value="traffic" <?php echo $categoryFilter === 'traffic' ? 'selected' : ''; ?>>Traffic</option>
                            <option value="other" <?php echo $categoryFilter === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <?php if (hasRole('citizen')): ?>
                    <div class="filter-group">
                        <label for="user">View:</label>
                        <select name="user" id="user">
                            <option value="">All Reports</option>
                            <option value="me" <?php echo $userFilter === 'me' ? 'selected' : ''; ?>>My Reports Only</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="reports.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>

            <!-- Reports List -->
            <div class="reports-list">
                <?php if (empty($reports)): ?>
                    <div class="no-reports">
                        <h3>No reports found</h3>
                        <p>No reports match your current filters.</p>
                        <a href="reports.php" class="btn btn-secondary">View All Reports</a>
                        <?php if (hasAnyRole(['citizen', 'authority'])): ?>
                            <a href="report.php" class="btn btn-primary">Report New Issue</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <div class="report-item">
                            <div class="report-content">
                                <div class="report-main">
                                    <div class="report-title-section">
                                        <h3><?php echo htmlspecialchars($report['title']); ?></h3>
                                        <div class="report-badges">
                                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $report['status'])); ?>
                                            </span>
                                            <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                                <?php echo ucfirst($report['priority']); ?> Priority
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <p class="report-description"><?php echo htmlspecialchars($report['description']); ?></p>
                                    
                                    <div class="report-details">
                                        <div class="detail-item">
                                            <span class="detail-icon">
                                                <?php 
                                                $icons = [
                                                    'streetlight' => '💡',
                                                    'pothole' => '🕳️',
                                                    'garbage' => '🗑️',
                                                    'traffic' => '🚦',
                                                    'other' => '📍'
                                                ];
                                                echo $icons[$report['category']];
                                                ?>
                                            </span>
                                            <span><?php echo ucfirst($report['category']); ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <span class="detail-icon">📍</span>
                                            <span><?php echo htmlspecialchars($report['location']); ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <span class="detail-icon">👤</span>
                                            <span>Reported by <?php echo htmlspecialchars($report['reporter']); ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <span class="detail-icon">🕒</span>
                                            <span><?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></span>
                                        </div>
                                        
                                        <?php if (!empty($report['updated_at']) && $report['updated_at'] !== $report['created_at']): ?>
                                        <div class="detail-item">
                                            <span class="detail-icon">🔄</span>
                                            <span>Updated <?php echo date('M j, Y g:i A', strtotime($report['updated_at'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($report['photo_path'])): ?>
                                <div class="report-image">
                                    <img src="uploads/<?php echo htmlspecialchars($report['photo_path']); ?>" 
                                         alt="Report image" onclick="openImageModal(this.src)">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="report-actions">
                                <button class="btn btn-small btn-secondary" onclick="viewOnMap(<?php echo $report['latitude'] ?: 'null'; ?>, <?php echo $report['longitude'] ?: 'null'; ?>)">
                                    🗺️ View on Map
                                </button>
                                
                                <?php if (hasAnyRole(['authority', 'admin'])): ?>
                                    <select class="status-update" onchange="updateReportStatus(<?php echo $report['id']; ?>, this.value)">
                                        <option value="">Update Status</option>
                                        <option value="pending" <?php echo $report['status'] === 'pending' ? 'disabled' : ''; ?>>Pending</option>
                                        <option value="in-progress" <?php echo $report['status'] === 'in-progress' ? 'disabled' : ''; ?>>In Progress</option>
                                        <option value="fixed" <?php echo $report['status'] === 'fixed' ? 'disabled' : ''; ?>>Fixed</option>
                                    </select>
                                <?php endif; ?>
                                
                                <button class="btn btn-small btn-primary" onclick="shareReport(<?php echo $report['id']; ?>)">
                                    📤 Share
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="reports-actions">
                <?php if (hasAnyRole(['citizen', 'authority'])): ?>
                    <a href="report.php" class="btn btn-primary">📍 Report New Issue</a>
                <?php endif; ?>
                <button onclick="exportReports()" class="btn btn-secondary">📊 Export Data</button>
                <button onclick="toggleMapView()" class="btn btn-secondary">🗺️ Map View</button>
            </div>
        </div>
    </main>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" onclick="closeImageModal()">
        <img id="modalImage" src="" alt="Report image">
    </div>

    <script>
        // Report functionality
        function updateReportStatus(reportId, newStatus) {
            if (!newStatus) return;
            
            if (confirm(`Are you sure you want to change the status to "${newStatus.replace('-', ' ')}"?`)) {
                // Simulate API call
                alert(`Status updated to: ${newStatus.replace('-', ' ')}`);
                location.reload();
            }
        }

        function viewOnMap(lat, lng) {
            alert(`Opening map view for coordinates: ${lat}, ${lng}\n(Google Maps integration would be implemented here)`);
        }

        function shareReport(reportId) {
            const shareUrl = window.location.origin + window.location.pathname + '?id=' + reportId;
            if (navigator.share) {
                navigator.share({
                    title: 'Community Report',
                    text: 'Check out this community issue report',
                    url: shareUrl
                });
            } else {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    alert('Report link copied to clipboard!');
                });
            }
        }

        function exportReports() {
            alert('Export functionality would generate CSV/PDF reports here');
        }

        function toggleMapView() {
            alert('Map view would show all reports on an interactive map');
        }

        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'flex';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Auto-submit form when filters change
        document.querySelectorAll('.filter-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>