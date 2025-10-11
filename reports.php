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
// Order by latest activity: use the most recent of created_at/updated_at, then by priority (high -> medium -> low)
$sql .= " ORDER BY GREATEST(r.created_at, COALESCE(r.updated_at, r.created_at)) DESC, FIELD(r.priority, 'high','medium','low') ASC";

$stmt = executeQuery($sql, $params);
$reports = $stmt->fetchAll();

// Calculate statistics
$totalReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
$pendingCount = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$inProgressCount = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'in-progress'")->fetchColumn();
$fixedCount = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'fixed'")->fetchColumn();
$rejectedCount = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'rejected'")->fetchColumn();

// Handle AJAX status update
if (
    isset($_POST['action']) && $_POST['action'] === 'update_status'
    && isset($_POST['report_id'], $_POST['new_status'])
    && hasAnyRole(['authority', 'admin'])
) {
    $reportId = (int)$_POST['report_id'];
    $newStatus = $_POST['new_status'];
    $allowed = ['pending', 'in-progress', 'fixed', 'rejected'];

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

    // Optional update note (e.g., rejection reason)
    $updateNote = isset($_POST['update_note']) ? trim($_POST['update_note']) : null;

    try {
        // Update report status
        executeQuery("UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $reportId]);

        // Log status update (include note)
        executeQuery(
            "INSERT INTO status_updates (report_id, updated_by_user_id, old_status, new_status, update_note) VALUES (?, ?, ?, ?, ?)",
            [$reportId, $user['id'], $oldStatus, $newStatus, $updateNote]
        );

        // Notify the original reporter about the status change
        try {
            $stmt = executeQuery("SELECT user_id, title FROM reports WHERE id = ?", [$reportId]);
            $r = $stmt->fetch();
            if ($r) {
                $reporterId = $r['user_id'];
                $titleStr = $r['title'];
                $notifTitle = sprintf('Report status updated: %s', $titleStr);
                $notifBody = sprintf('The status of your report "%s" was changed to %s.', $titleStr, $newStatus);
                if ($newStatus === 'rejected' && $updateNote) {
                    $notifBody .= ' Reason: ' . $updateNote;
                }
                createNotification($reporterId, $notifTitle, $notifBody);
            }
        } catch (Exception $e) {
            error_log('Failed to create notification for reporter: ' . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Status updated']);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}
// Handle AJAX delete report (allow owner to delete their report)
if (
    isset($_POST['action']) && $_POST['action'] === 'delete_report' 
    && isset($_POST['report_id'])
    && isLoggedIn()
) {
    $reportId = (int)$_POST['report_id'];
    try {
        // Verify ownership and get photo path
        $stmt = executeQuery("SELECT user_id, photo_path FROM reports WHERE id = ?", [$reportId]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            exit;
        }

        if ($row['user_id'] != $user['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this report']);
            exit;
        }

        // Attempt to delete associated photo file if present
        if (!empty($row['photo_path']) && defined('UPLOAD_DIR') && UPLOAD_DIR) {
            $safeName = basename($row['photo_path']); // prevent directory traversal
            $filePath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $safeName;
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }

        // Delete the report (hard delete)
        executeQuery("DELETE FROM reports WHERE id = ?", [$reportId]);
        echo json_encode(['success' => true, 'message' => 'Report deleted']);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete report']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- <link rel="stylesheet" href="assets/css/style.css"> -->
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
                    <div class="notification-area">
                        <?php if (isLoggedIn()): ?>
                            <?php $unreads = getUnreadNotifications($user['id'], 4); ?>
                            <button class="btn btn-small btn-notify" onclick="toggleNotifications()">🔔 <?php echo count($unreads) ? '<span class="notify-count">'.count($unreads).'</span>' : ''; ?></button>
                            <div id="notifyDropdown" class="notify-dropdown" style="display:none;">
                                <button class="notify-close" aria-label="Close notifications" onclick="closeAllDropdowns()">✕</button>
                                <?php if (empty($unreads)): ?>
                                    <div class="notify-item">No new notifications</div>
                                <?php else: ?>
                                    <?php foreach ($unreads as $n): ?>
                                        <div class="notify-item" data-id="<?php echo $n['id']; ?>">
                                            <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                                            <div class="notify-body"><?php echo htmlspecialchars($n['body']); ?></div>
                                            <div class="notify-time"><?php echo date('M j, Y g:i A', strtotime($n['created_at'])); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="notify-actions"><a href="notifications.php">View all</a></div>
                            </div>
                        <?php endif; ?>
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
                <div class="stat-card">
                    <h3>Rejected</h3>
                    <span class="stat-number rejected"><?php echo $rejectedCount; ?></span>
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
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                                            <?php
                                                // Show 'edited' badge only when updated_at is strictly later than created_at
                                                if (!empty($report['updated_at']) && !empty($report['created_at'])) {
                                                    $createdTs = strtotime($report['created_at']);
                                                    $updatedTs = strtotime($report['updated_at']);
                                                    if ($updatedTs > $createdTs) {
                                                        echo '<span class="edited-badge">edited</span>';
                                                    }
                                                }
                                            ?>
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
                                        <option value="rejected" <?php echo $report['status'] === 'rejected' ? 'disabled' : ''; ?>>Rejected</option>
                                    </select>
                                <?php endif; ?>
                                
                                <button class="btn btn-small btn-primary" onclick="shareReport(<?php echo $report['id']; ?>)">
                                    📤 Share
                                </button>

                                <?php if (hasRole('citizen') && $report['user_id'] == $user['id']): ?>
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <a class="btn btn-small btn-secondary" href="edit_report.php?id=<?php echo $report['id']; ?>">✏️ Edit</a>
                                        <button class="btn btn-small btn-delete" onclick="deleteReport(<?php echo $report['id']; ?>)">🗑️ Delete</button>
                                    <?php endif; ?>
                                <?php endif; ?>
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
            // If rejected, ask for reason
            let updateNote = '';
            if (newStatus === 'rejected') {
                updateNote = prompt('Please provide a brief reason for rejection (e.g. repetitive issue):', 'repetitive issue');
                if (updateNote === null) return; // cancelled
            }

            if (!confirm(`Are you sure you want to change the status to "${newStatus.replace('-', ' ')}"?`)) return;

            const body = `action=update_status&report_id=${encodeURIComponent(reportId)}&new_status=${encodeURIComponent(newStatus)}&update_note=${encodeURIComponent(updateNote)}`;

            fetch('reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
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

        function deleteReport(reportId) {
            if (!confirm('Are you sure you want to delete this report? This action cannot be undone.')) return;

            fetch('reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_report&report_id=${encodeURIComponent(reportId)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Report deleted successfully');
                    location.reload();
                } else {
                    alert('Failed to delete: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(() => alert('Error deleting report.'));
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

    <script>
        function closeAllDropdowns() {
            const nd = document.getElementById('notifyDropdown');
            if (nd) nd.style.display = 'none';
            const um = document.querySelector('.user-menu');
            if (um) um.classList.remove('open');
        }

        function toggleNotifications() {
            const dd = document.getElementById('notifyDropdown');
            if (!dd) return;
            const isOpen = dd.style.display === 'flex';
            closeAllDropdowns();
            dd.style.display = isOpen ? 'none' : 'flex';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.addEventListener('click', function(e) {
                    if (e.target.closest('.notification-area')) return;
                    const dd = document.querySelector('.user-dropdown');
                    if (!dd) return;
                    const wasOpen = userMenu.classList.contains('open');
                    closeAllDropdowns();
                    if (!wasOpen) userMenu.classList.add('open');
                });
            }
        });

        // Close dropdowns when clicking outside or pressing Escape
        document.addEventListener('click', function(e) {
            if (e.target.closest('.notify-dropdown') || e.target.closest('.btn-notify')) return;
            if (e.target.closest('.user-menu')) return;
            closeAllDropdowns();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAllDropdowns();
        });

        // Mark notification as read when clicked (only when clicking a .notify-item)
        document.addEventListener('click', function(e) {
            const item = e.target.closest('.notify-item');
            if (!item) return;
            const id = item.getAttribute('data-id');
            if (!id) return;
            fetch('mark_notification.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `id=${encodeURIComponent(id)}`
            }).then(() => {
                item.style.opacity = '0.6';
                const badge = document.querySelector('.notify-count');
                if (badge) {
                    const current = parseInt(badge.textContent || '0', 10);
                    if (current > 1) badge.textContent = current - 1;
                    else badge.remove();
                }
            }).catch(() => {});
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