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

// Handle AJAX status update
if (
    isset($_POST['action']) && $_POST['action'] === 'update_status'
    && isset($_POST['report_id'], $_POST['new_status'])
    && hasAnyRole(['authority', 'admin'])
) {
    $reportId = (int)$_POST['report_id'];
    $newStatus = $_POST['new_status'];
    $allowed = ['pending', 'in-progress', 'fixed'];

    if (!in_array($newStatus, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    // Fetch current status
    $stmt = executeQuery("SELECT status FROM reports WHERE id = ?", [$reportId]);
    $oldStatus = $stmt->fetchColumn();

    if (!$oldStatus || $oldStatus === $newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or unchanged status']);
        exit;
    }

    // Update report status
    executeQuery("UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $reportId]);
    // Log status update
    executeQuery(
        "INSERT INTO status_updates (report_id, updated_by_user_id, old_status, new_status) VALUES (?, ?, ?, ?)",
        [$reportId, $user['id'], $oldStatus, $newStatus]
    );

    echo json_encode(['success' => true, 'message' => 'Status updated']);
    exit;
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
                    <?php if (!hasAnyRole(['authority'])): ?>
                    <li class="nav-item">
                        <a href="report.php" class="nav-link ">Report Issue</a>
                    </li>
                    <?php endif; ?>
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
                                <button class="btn btn-small btn-secondary" onclick="viewOnMap(
    <?php echo $report['latitude'] ?: 'null'; ?>, 
    <?php echo $report['longitude'] ?: 'null'; ?>, 
    '<?php echo htmlspecialchars(addslashes($report['location'])); ?>')">
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
                <?php if (hasAnyRole(['citizen'])): ?>
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

    <!-- Map Modal -->
    <div id="mapModal" class="modal" onclick="closeMapModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <span class="close" onclick="closeMapModal()">&times;</span>
            <div id="mapAreaName" style="font-weight:bold; margin-bottom:8px;"></div>
            <iframe id="mapFrame" width="100%" height="400" frameborder="0" style="border:0" allowfullscreen></iframe>
        </div>
    </div>

    <!-- Map View Modal -->
    <div id="allMapModal" class="modal" onclick="closeAllMapModal()">
        <div class="modal-content" style="max-width:900px;width:95vw;" onclick="event.stopPropagation()">
            <span class="close" onclick="closeAllMapModal()">&times;</span>
            <h3 style="margin-bottom:8px;">All Reported Areas</h3>
            <div id="allMap" style="width:100%;height:500px;border-radius:8px;"></div>
        </div>
    </div>

    <script>
        // Report functionality
        function updateReportStatus(reportId, newStatus) {
            if (!newStatus) return;
            if (!confirm(`Are you sure you want to change the status to "${newStatus.replace('-', ' ')}"?`)) return;

            fetch('reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update_status&report_id=${encodeURIComponent(reportId)}&new_status=${encodeURIComponent(newStatus)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Status updated successfully!');
                    location.reload();
                } else {
                    alert('Failed: ' + data.message);
                }
            })
            .catch(() => alert('Error updating status.'));
        }

        function viewOnMap(lat, lng, areaName) {
            if (!lat || !lng) {
                alert('Location not available for this report.');
                return;
            }
            document.getElementById('mapAreaName').textContent = areaName || '';
            const mapUrl = `https://www.google.com/maps?q=${lat},${lng}&hl=es;z=16&output=embed`;
            document.getElementById('mapFrame').src = mapUrl;
            document.getElementById('mapModal').style.display = 'flex';
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
            window.location.href = "export_reports.php";
        }

        function toggleMapView() {
            document.getElementById('allMapModal').style.display = 'flex';
            setTimeout(initAllMap, 100); // Ensure modal is visible before rendering map
        }

        function initAllMap() {
    if (!reportsData.length) {
        document.getElementById('allMap').innerHTML = '<p style="text-align:center;">No locations to display.</p>';
        return;
    }

    const center = {lat: parseFloat(reportsData[0].lat), lng: parseFloat(reportsData[0].lng)};
    allMap = new google.maps.Map(document.getElementById('allMap'), {
        zoom: 12,
        center: center
    });

    google.maps.event.addListenerOnce(allMap, 'idle', function() {
        google.maps.event.trigger(allMap, 'resize');
        allMap.setCenter(center); // recenters properly
    });

    allMarkers.forEach(m => m.setMap(null));
    allMarkers = [];

    reportsData.forEach(report => {
        const marker = new google.maps.Marker({
            position: {lat: parseFloat(report.lat), lng: parseFloat(report.lng)},
            map: allMap,
            title: report.title,
            label: report.category[0].toUpperCase()
        });

        const info = new google.maps.InfoWindow({
            content: `<strong>${report.title}</strong><br>
                      <span>${report.location}</span><br>
                      <span>Status: ${report.status.replace('-', ' ')}</span><br>
                      <a href="reports.php?id=${report.id}" target="_blank">View Details</a>`
        });
        marker.addListener('click', () => info.open(allMap, marker));
        allMarkers.push(marker);
    });

    if (allMarkers.length > 1) {
        const bounds = new google.maps.LatLngBounds();
        allMarkers.forEach(m => bounds.extend(m.getPosition()));
        allMap.fitBounds(bounds);
    }
}


        function closeAllMapModal() {
            document.getElementById('allMapModal').style.display = 'none';
        }

        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'flex';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        function closeMapModal() {
            document.getElementById('mapModal').style.display = 'none';
            document.getElementById('mapFrame').src = '';
        }

        // Prepare reports data for map view
        const reportsData = <?php
            $mapReports = [];
            foreach ($reports as $r) {
                if (!empty($r['latitude']) && !empty($r['longitude'])) {
                    $mapReports[] = [
                        'title' => $r['title'],
                        'location' => $r['location'],
                        'lat' => $r['latitude'],
                        'lng' => $r['longitude'],
                        'status' => $r['status'],
                        'category' => $r['category'],
                        'id' => $r['id']
                    ];
                }
            }
            echo json_encode($mapReports);
        ?>;

        let allMap, allMarkers = [];

        // Auto-submit form when filters change
        document.querySelectorAll('.filter-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCskAowPO-5o7MbetcjCQXczbIyJj5OieU"></script>
    <style>
        /* Add styles for the map modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.6);
            align-items: center; justify-content: center;
        }
        .modal-content {
            background: #fff;
            padding: 1em;
            border-radius: 8px;
            position: relative;
            max-width: 600px;
            width: 90vw;
        }
        .modal .close {
            position: absolute;
            top: 8px; right: 16px;
            font-size: 2em;
            cursor: pointer;
        }
        #mapFrame { border-radius: 8px; }
        #allMap { min-height: 400px; }
    </style>
</body>
</html>