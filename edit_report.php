<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

requireLogin();
$user = getCurrentUser();

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$reportId) {
    header('Location: reports.php');
    exit;
}

// Fetch report
$stmt = executeQuery("SELECT * FROM reports WHERE id = ?", [$reportId]);
$report = $stmt->fetch();
if (!$report) {
    header('Location: reports.php');
    exit;
}

// Only owner can edit
if ($report['user_id'] != $user['id']) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied.');
}

// Only pending reports can be edited
if ($report['status'] !== 'pending') {
    header('Location: reports.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    $latitude = ($latitude === '') ? null : $latitude;
    $longitude = ($longitude === '') ? null : $longitude;

    // Basic validation
    if (empty($title) || empty($description) || empty($category) || empty($location)) {
        $error = 'All required fields must be filled.';
    } elseif (mb_strlen($title) > 60) {
        $error = 'Issue title must not exceed 60 characters.';
    } elseif (mb_strlen($description) > 1000) {
        $error = 'Description must not exceed 1000 characters.';
    } elseif (!in_array($category, ['streetlight', 'pothole', 'garbage', 'traffic', 'other'])) {
        $error = 'Invalid category selected.';
    } elseif (!in_array($priority, ['low','medium','high'])) {
        $error = 'Invalid priority selected.';
    } else {
        // Handle optional photo upload (same logic as report.php)
        $photo_path = $report['photo_path'];
        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE &&
            $_FILES['photo']['error'] === UPLOAD_ERR_OK
        ) {
            if (!defined('UPLOAD_DIR') || !UPLOAD_DIR) {
                $error = 'Upload directory not configured.';
            } else {
                if (!is_dir(UPLOAD_DIR)) {
                    if (!mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
                        $error = 'Failed to create upload directory.';
                    }
                }

                $allowed_types = defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg','image/png','image/gif'];
                $max_size = defined('MAX_FILE_SIZE') ? (int)MAX_FILE_SIZE : 5 * 1024 * 1024;

                $fileType = $_FILES['photo']['type'];
                $fileSize = $_FILES['photo']['size'];

                if (!in_array($fileType, $allowed_types)) {
                    $error = 'Invalid file type uploaded.';
                } elseif ($fileSize > $max_size) {
                    $error = 'Uploaded file exceeds maximum allowed size.';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                    finfo_close($finfo);
                    $extMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif'
                    ];
                    $ext = isset($extMap[$mime]) ? $extMap[$mime] : pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $timestamp = time();
                    $filename = $timestamp . '.' . $ext;
                    $target = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                        @chmod($target, 0644);
                        // Optionally delete old photo file
                        if (!empty($report['photo_path'])) {
                            $old = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $report['photo_path'];
                            if (file_exists($old)) @unlink($old);
                        }
                        $photo_path = $filename;
                    } else {
                        $error = 'Failed to move uploaded file.';
                    }
                }
            }
        }

        if (empty($error)) {
            try {
                executeQuery(
                    "UPDATE reports SET title = ?, description = ?, category = ?, location = ?, latitude = ?, longitude = ?, photo_path = ?, priority = ?, updated_at = NOW() WHERE id = ?",
                    [$title, $description, $category, $location, $latitude, $longitude, $photo_path, $priority, $reportId]
                );

                        // Notify authorities that the report was edited
                        try {
                            $stmt = executeQuery("SELECT id, full_name FROM users WHERE role = 'authority' AND is_active = 1", []);
                            $authorities = $stmt->fetchAll();
                            foreach ($authorities as $auth) {
                                $notifTitle = 'Report updated by reporter';
                                $notifBody = sprintf('The report "%s" was edited by %s.', $title, $user['full_name']);
                                createNotification($auth['id'], $notifTitle, $notifBody);
                            }
                        } catch (Exception $e) {
                            error_log('Failed to notify authorities on edit: ' . $e->getMessage());
                        }

                header('Location: reports.php');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to update report: ' . $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Report - CivicVoice</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/forms.css">
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
                        <a href="report.php" class="nav-link">Report Issue</a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link active">All reports</a>
                    </li>
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

    <main class="form-main">
        <div class="form-container">
            <div class="form-header">
                <h1>✏️ Edit Your Report</h1>
                <p>Update the details of your issue. Only pending reports can be edited.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="report-form" id="reportForm">
                <!-- Report Title -->
                <div class="form-group">
                    <label for="title">Issue Title *</label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? $report['title']); ?>" 
                           required placeholder="Brief description of the issue"
                           maxlength="60">
                    <small class="form-help">Provide a clear, concise title for the issue</small>
                    <div id="titleError" class="field-error" style="display:none;color:#e53e3e;margin-top:6px;font-size:0.95em;"></div>
                </div>

                <!-- Category Selection -->
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select Issue Category</option>
                        <option value="streetlight" <?php echo (($_POST['category'] ?? $report['category']) === 'streetlight') ? 'selected' : ''; ?>>
                            💡 Streetlight Issues
                        </option>
                        <option value="pothole" <?php echo (($_POST['category'] ?? $report['category']) === 'pothole') ? 'selected' : ''; ?>>
                            🕳️ Road Potholes
                        </option>
                        <option value="garbage" <?php echo (($_POST['category'] ?? $report['category']) === 'garbage') ? 'selected' : ''; ?>>
                            🗑️ Garbage Collection
                        </option>
                        <option value="traffic" <?php echo (($_POST['category'] ?? $report['category']) === 'traffic') ? 'selected' : ''; ?>>
                            🚦 Traffic Signals
                        </option>
                        <option value="other" <?php echo (($_POST['category'] ?? $report['category']) === 'other') ? 'selected' : ''; ?>>
                            📍 Other Issues
                        </option>
                    </select>
                </div>

                <!-- Priority Level -->
                <div class="form-group">
                    <label for="priority">Priority Level *</label>
                    <select id="priority" name="priority" required>
                        <option value="low" <?php echo (($_POST['priority'] ?? $report['priority']) === 'low') ? 'selected' : ''; ?>>
                            🟢 Low - Non-urgent issue
                        </option>
                        <option value="medium" <?php echo (($_POST['priority'] ?? $report['priority']) === 'medium') ? 'selected' : ''; ?>>
                            🟡 Medium - Moderate concern
                        </option>
                        <option value="high" <?php echo (($_POST['priority'] ?? $report['priority']) === 'high') ? 'selected' : ''; ?>>
                            🔴 High - Safety hazard or urgent
                        </option>
                    </select>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">Detailed Description *</label>
                    <textarea id="description" name="description" rows="5" required 
                              placeholder="Provide detailed information about the issue, when you noticed it, and how it affects the community" maxlength="1000"><?php echo htmlspecialchars($_POST['description'] ?? $report['description']); ?></textarea>
                    <small class="form-help">Include as much detail as possible to help authorities understand and address the issue</small>
                    <div id="descriptionError" class="field-error" style="display:none;color:#e53e3e;margin-top:6px;font-size:0.95em;"></div>
                </div>

                <!-- Location -->
                <div class="form-group">
                    <label for="location">Location *</label>
                    <div class="location-input-group">
                        <input type="text" id="location" name="location" 
                               value="<?php echo htmlspecialchars($_POST['location'] ?? $report['location']); ?>" 
                               required placeholder="Enter the exact address or landmark"
                               maxlength="500">
                        <button type="button" id="getCurrentLocation" class="btn btn-secondary">
                            📍 Use Current Location
                        </button>
                    </div>
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? $report['latitude']); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? $report['longitude']); ?>">
                    <div id="locationStatus" class="location-status"></div>
                </div>

                <!-- Photo Upload -->
                <div class="form-group">
                    <label for="photo">Photo Evidence (Optional)</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="photo" name="photo" accept="image/*" capture="environment">
                        <div class="file-upload-display">
                            <span class="upload-text">📸 Choose photo or take picture</span>
                            <span class="upload-hint">Max size: 5MB. Formats: JPG, PNG, GIF</span>
                        </div>
                    </div>
                    <div id="imagePreview" class="image-preview">
                        <?php if (!empty($report['photo_path'])): ?>
                            <div class="preview-container">
                                <img src="<?php echo htmlspecialchars(rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $report['photo_path']); ?>" alt="Current photo" class="preview-image">
                                <button type="button" onclick="removeImage()" class="remove-image">✕</button>
                            </div>
                            <script>document.addEventListener('DOMContentLoaded', function(){ document.querySelector('.upload-text').textContent = '📸 <?php echo htmlspecialchars(basename($report['photo_path'])); ?>'; });</script>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="form-section">
                    <h3>Contact Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reporter_name">Your Name</label>
                            <input type="text" id="reporter_name" name="reporter_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                   readonly>
                        </div>
                        <div class="form-group">
                            <label for="reporter_email">Email Address</label>
                            <input type="email" id="reporter_email" name="reporter_email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   readonly>
                        </div>
                    </div>
                    <small class="form-help">We'll use this information to update you on the progress of your report</small>
                </div>

                <!-- Submit Button -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        <span class="btn-emoji">💾</span> Save Changes
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-secondary">
                        <span class="btn-emoji">🔄</span> Reset Form
                    </button>
                    <a href="reports.php" class="btn btn-secondary">
                        ← Back to Reports
                    </a>
                </div>
            </form>

            <!-- Help Section -->
            <div class="help-section">
                <h3>📋 Reporting Guidelines</h3>
                <ul>
                    <li><strong>Be Specific:</strong> Provide exact location and detailed description</li>
                    <li><strong>Include Photos:</strong> Visual evidence helps authorities understand the issue better</li>
                    <li><strong>Choose Priority:</strong> Select appropriate priority level based on safety and urgency</li>
                    <li><strong>Stay Updated:</strong> You'll receive notifications about the status of your report</li>
                    <li><strong>Emergency Issues:</strong> For immediate safety hazards, also contact local emergency services</li>
                </ul>
                
                <div class="emergency-notice">
                    <strong>⚠️ Emergency Notice:</strong> For life-threatening situations or immediate safety hazards, 
                    please call emergency services (999) in addition to submitting this report.
                </div>
            </div>
        </div>
    </main>

    <script>
        // Form functionality (copied from report.php)
        document.addEventListener('DOMContentLoaded', function() {
            initializeReportForm();
        });

        function initializeReportForm() {
            // Photo upload preview
            const photoInput = document.getElementById('photo');
            const imagePreview = document.getElementById('imagePreview');
            
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file size (5MB limit)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size too large. Please choose a file smaller than 5MB.');
                        this.value = '';
                        return;
                    }
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `
                            <div class="preview-container">
                                <img src="${e.target.result}" alt="Preview" class="preview-image">
                                <button type="button" onclick="removeImage()" class="remove-image">✕</button>
                            </div>
                        `;
                    };
                    reader.readAsDataURL(file);
                    
                    // Update upload text
                    document.querySelector('.upload-text').textContent = `📸 ${file.name}`;
                }
            });

            // Location functionality
            const getCurrentLocationBtn = document.getElementById('getCurrentLocation');
            const locationInput = document.getElementById('location');
            const locationStatus = document.getElementById('locationStatus');
            const latInput = document.getElementById('latitude');
            const lonInput = document.getElementById('longitude');

            getCurrentLocationBtn.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by this browser.');
                    return;
                }

                this.disabled = true;
                this.textContent = '📍 Getting location...';
                locationStatus.innerHTML = '<span class="status-loading">🔄 Getting your location...</span>';

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        
                        // Store coordinates
                        latInput.value = lat;
                        lonInput.value = lon;
                        
                        // Reverse geocoding simulation (in real app, use Google Maps API)
                        const address = `Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)}`;
                        locationInput.value = address;
                        
                        locationStatus.innerHTML = '<span class="status-success">✅ Location captured successfully!</span>';
                        getCurrentLocationBtn.disabled = false;
                        getCurrentLocationBtn.textContent = '📍 Update Location';
                    },
                    function(error) {
                        let errorMessage = 'Unable to get location. ';
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += 'Please allow location access.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += 'Location information unavailable.';
                                break;
                            case error.TIMEOUT:
                                errorMessage += 'Location request timed out.';
                                break;
                        }
                        
                        locationStatus.innerHTML = `<span class="status-error">❌ ${errorMessage}</span>`;
                        getCurrentLocationBtn.disabled = false;
                        getCurrentLocationBtn.textContent = '📍 Try Again';
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });

            // Form validation
            const form = document.getElementById('reportForm');
            form.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const description = document.getElementById('description').value.trim();
                const category = document.getElementById('category').value;
                const location = document.getElementById('location').value.trim();

                if (!title || !description || !category || !location) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return;
                }

                // Client-side title length checks
                const titleErrorEl = document.getElementById('titleError');
                titleErrorEl.style.display = 'none';

                if (title.length < 10) {
                    e.preventDefault();
                    alert('Please provide a more descriptive title (minimum 10 characters).');
                    return;
                }

                if (title.length > 60) {
                    e.preventDefault();
                    titleErrorEl.textContent = 'Title exceeds maximum length of 60 characters.';
                    titleErrorEl.style.display = 'block';
                    return;
                }

                const descriptionErrorEl = document.getElementById('descriptionError');
                descriptionErrorEl.style.display = 'none';

                if (description.length < 20) {
                    e.preventDefault();
                    alert('Please provide a more detailed description (minimum 20 characters).');
                    return;
                }

                if (description.length > 1000) {
                    e.preventDefault();
                    descriptionErrorEl.textContent = 'Description exceeds maximum length of 1000 characters.';
                    descriptionErrorEl.style.display = 'block';
                    return;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Saving Changes...';
            });
        }

        function removeImage() {
            document.getElementById('photo').value = '';
            document.getElementById('imagePreview').innerHTML = '';
            document.querySelector('.upload-text').textContent = '📸 Choose photo or take picture';
        }

        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('reportForm').reset();
                document.getElementById('imagePreview').innerHTML = '';
                document.getElementById('locationStatus').innerHTML = '';
                document.querySelector('.upload-text').textContent = '📸 Choose photo or take picture';
                document.getElementById('latitude').value = '';
                document.getElementById('longitude').value = '';
            }
        }

        // Character counter for title and description
        const titleMinLength = 10; // minimum title length (used in validation and helper)
        const titleMaxLength = 60;

        const titleEl = document.getElementById('title');
        const titleHelp = titleEl.nextElementSibling;

        titleEl.addEventListener('input', function() {
            const currentLength = this.value.length;

            if (currentLength < titleMinLength) {
                titleHelp.textContent = `At least ${titleMinLength - currentLength} more characters needed`;
                titleHelp.style.color = '#e53e3e';
            } else if (titleMaxLength - currentLength < 50) {
                const remaining = titleMaxLength - currentLength;
                titleHelp.textContent = `${remaining} characters remaining`;
                titleHelp.style.color = remaining < 20 ? '#e53e3e' : '#f56500';
            } else {
                titleHelp.textContent = 'Provide a clear, concise title for the issue';
                titleHelp.style.color = '#666';
            }
        });

        const descriptionEl = document.getElementById('description');
        const descriptionHelp = descriptionEl.nextElementSibling;
        const descriptionMinLength = 20;
        const descriptionMaxLength = 1000;

        descriptionEl.addEventListener('input', function() {
            const currentLength = this.value.length;

            if (currentLength < descriptionMinLength) {
                descriptionHelp.textContent = `At least ${descriptionMinLength - currentLength} more characters needed`;
                descriptionHelp.style.color = '#e53e3e';
            } else if (descriptionMaxLength - currentLength < 100) {
                const remaining = descriptionMaxLength - currentLength;
                descriptionHelp.textContent = `${remaining} characters remaining`;
                descriptionHelp.style.color = remaining < 50 ? '#e53e3e' : '#f56500';
            } else {
                descriptionHelp.textContent = 'Include as much detail as possible to help authorities understand and address the issue';
                descriptionHelp.style.color = '#666';
            }
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
        });
    </script>
</body>
</html>