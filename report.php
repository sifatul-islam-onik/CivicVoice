<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Require login
requireLogin();
$success = '';
$error = '';

// Check success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Your report has been submitted successfully! You will be notified when authorities review your report.";
}

$user = getCurrentUser();
$page_title = "Report Issue - CivicVoice";

$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    // Convert empty latitude/longitude to null for database
    $latitude = ($latitude === '') ? null : $latitude;
    $longitude = ($longitude === '') ? null : $longitude;

    $priority = $_POST['priority'] ?? 'medium';
    
    // Validation
    if (empty($title) || empty($description) || empty($category) || empty($location)) {
        $error = 'All required fields must be filled.';
    } elseif (!in_array($category, ['streetlight', 'pothole', 'garbage', 'traffic', 'other'])) {
        $error = 'Invalid category selected.';
    } elseif (!in_array($priority, ['low', 'medium', 'high'])) {
        $error = 'Invalid priority level.';
    } else {
        // Handle photo upload if provided (photo is optional)
        $photo_path = null;
        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE &&
            $_FILES['photo']['error'] === UPLOAD_ERR_OK
        ) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['photo']['type'], $allowed_types)) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'report_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $target = UPLOAD_DIR . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                    $photo_path = $filename;
                }
            }
        }

        try {
            executeQuery(
                "INSERT INTO reports (user_id, title, description, category, location, latitude, longitude, status, photo_path, priority, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())",
                [
                    $user['id'],
                    $title,
                    $description,
                    $category,
                    $location,
                    $latitude,
                    $longitude,
                    $photo_path,
                    $priority
                ]
            );
            // Redirect (prevents resubmission on refresh)
            header("Location: report.php?success=1");
            exit;
        } catch (Exception $e) {
            $error = 'Failed to submit report: ' . $e->getMessage();
        }
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
                        <a href="report.php" class="nav-link active">Report Issue</a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">All reports</a>
                    </li>
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

    <main class="form-main">
        <div class="form-container">
            <div class="form-header">
                <h1>📍 Report a Community Issue</h1>
                <p>Help improve your community by reporting issues that need attention</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <div class="success-actions">
                    <a href="reports.php" class="btn btn-secondary">View All Reports</a>
                    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="report-form" id="reportForm">
                <!-- Report Title -->
                <div class="form-group">
                    <label for="title">Issue Title *</label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo htmlspecialchars($title ?? ''); ?>" 
                           required placeholder="Brief description of the issue"
                           maxlength="255">
                    <small class="form-help">Provide a clear, concise title for the issue</small>
                </div>

                <!-- Category Selection -->
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select Issue Category</option>
                        <option value="streetlight" <?php echo ($category ?? '') === 'streetlight' ? 'selected' : ''; ?>>
                            💡 Streetlight Issues
                        </option>
                        <option value="pothole" <?php echo ($category ?? '') === 'pothole' ? 'selected' : ''; ?>>
                            🕳️ Road Potholes
                        </option>
                        <option value="garbage" <?php echo ($category ?? '') === 'garbage' ? 'selected' : ''; ?>>
                            🗑️ Garbage Collection
                        </option>
                        <option value="traffic" <?php echo ($category ?? '') === 'traffic' ? 'selected' : ''; ?>>
                            🚦 Traffic Signals
                        </option>
                        <option value="other" <?php echo ($category ?? '') === 'other' ? 'selected' : ''; ?>>
                            📍 Other Issues
                        </option>
                    </select>
                </div>

                <!-- Priority Level -->
                <div class="form-group">
                    <label for="priority">Priority Level *</label>
                    <select id="priority" name="priority" required>
                        <option value="low" <?php echo ($priority ?? 'medium') === 'low' ? 'selected' : ''; ?>>
                            🟢 Low - Non-urgent issue
                        </option>
                        <option value="medium" <?php echo ($priority ?? 'medium') === 'medium' ? 'selected' : ''; ?>>
                            🟡 Medium - Moderate concern
                        </option>
                        <option value="high" <?php echo ($priority ?? 'medium') === 'high' ? 'selected' : ''; ?>>
                            🔴 High - Safety hazard or urgent
                        </option>
                    </select>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">Detailed Description *</label>
                    <textarea id="description" name="description" rows="5" required 
                              placeholder="Provide detailed information about the issue, when you noticed it, and how it affects the community"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    <small class="form-help">Include as much detail as possible to help authorities understand and address the issue</small>
                </div>

                <!-- Location -->
                <div class="form-group">
                    <label for="location">Location *</label>
                    <div class="location-input-group">
                        <input type="text" id="location" name="location" 
                               value="<?php echo htmlspecialchars($location ?? ''); ?>" 
                               required placeholder="Enter the exact address or landmark"
                               maxlength="500">
                        <button type="button" id="getCurrentLocation" class="btn btn-secondary">
                            📍 Use Current Location
                        </button>
                    </div>
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude ?? ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude ?? ''); ?>">
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
                    <div id="imagePreview" class="image-preview"></div>
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
                        📝 Submit Report
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-secondary">
                        🔄 Reset Form
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        ← Back to Dashboard
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
        // Form functionality
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

                if (title.length < 10) {
                    e.preventDefault();
                    alert('Please provide a more descriptive title (minimum 10 characters).');
                    return;
                }

                if (description.length < 20) {
                    e.preventDefault();
                    alert('Please provide a more detailed description (minimum 20 characters).');
                    return;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Submitting Report...';
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
        document.getElementById('title').addEventListener('input', function() {
            const maxLength = 255;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            let helpText = this.nextElementSibling;
            if (remaining < 50) {
                helpText.textContent = `${remaining} characters remaining`;
                helpText.style.color = remaining < 20 ? '#e53e3e' : '#f56500';
            } else {
                helpText.textContent = 'Provide a clear, concise title for the issue';
                helpText.style.color = '#666';
            }
        });

        document.getElementById('description').addEventListener('input', function() {
            const minLength = 20;
            const currentLength = this.value.length;
            
            let helpText = this.nextElementSibling;
            if (currentLength < minLength) {
                helpText.textContent = `At least ${minLength - currentLength} more characters needed`;
                helpText.style.color = '#e53e3e';
            } else {
                helpText.textContent = 'Include as much detail as possible to help authorities understand and address the issue';
                helpText.style.color = '#666';
            }
        });
    </script>
</body>
</html>