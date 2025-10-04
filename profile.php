<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
$page_title = "Your Profile - CivicVoice";
$success = '';
$error = '';

// Fetch complete user data from database to ensure we have all current information including phone
try {
    $stmt = executeQuery(
        "SELECT id, username, full_name, email, phone, role, created_at, updated_at FROM users WHERE id = ?",
        [$user['id']]
    );
    $userData = $stmt->fetch();
    
    if ($userData) {
        // Update user data with fresh information from database
        $user = $userData;
        // Debug: Log phone number for troubleshooting
        error_log("Profile phone loaded: " . ($user['phone'] ?? 'NULL'));
    }
} catch (Exception $e) {
    error_log("Profile data fetch error: " . $e->getMessage());
    // Continue with session data if database fetch fails
}

// Fetch report stats based on user role
if (hasAnyRole(['authority', 'admin'])) {
    // Authority and admin see stats for ALL user issues
    $myReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
    $pending = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
    $inProgress = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'in-progress'")->fetchColumn();
    $fixed = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'fixed'")->fetchColumn();
} else {
    // Citizens see only their own reports
    $myReports = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ?", [$user['id']])->fetchColumn();
    $pending = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'pending'", [$user['id']])->fetchColumn();
    $inProgress = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'in-progress'", [$user['id']])->fetchColumn();
    $fixed = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'fixed'", [$user['id']])->fetchColumn();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($full_name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($full_name) < 2) {
        $error = 'Full name must be at least 2 characters long.';
    } else {
        try {
            // Check if email is already taken by another user
            $existingUser = executeQuery(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$email, $user['id']]
            )->fetch();
            
            if ($existingUser) {
                $error = 'This email is already registered to another account.';
            } else {
                // Update user profile
                executeQuery(
                    "UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?",
                    [$full_name, $email, $phone, $user['id']]
                );
                
                // Update session data to reflect changes immediately
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                
                // Debug: Log phone update
                error_log("Phone updated to: " . $phone);
                
                // Refresh user data from database to get updated phone number
                try {
                    $stmt = executeQuery(
                        "SELECT id, username, full_name, email, phone, role, created_at, updated_at FROM users WHERE id = ?",
                        [$user['id']]
                    );
                    $userData = $stmt->fetch();
                    
                    if ($userData) {
                        $user = $userData;
                        // Debug: Log refreshed phone number
                        error_log("Phone refreshed to: " . ($user['phone'] ?? 'NULL'));
                    }
                } catch (Exception $e) {
                    error_log("Profile refresh error: " . $e->getMessage());
                }
                
                $success = 'Profile updated successfully!';
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = 'Failed to update profile. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/profile.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="profile-page-bg">
        <div class="profile-container">
            <h1>Your Profile</h1>
            <div class="profile-summary">
                <div class="profile-avatar">
                    <span><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                </div>
                <div class="profile-details">
                    <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <span class="role"><?php echo ucfirst($user['role']); ?></span>
                    <div class="email"><?php echo htmlspecialchars($user['email']); ?></div>
                    <div class="meta">
                        <span>Username: <?php echo htmlspecialchars($user['username']); ?></span>
                        <!-- <?php if (!empty($user['phone'])): ?>
                            <span> | 📞 <?php echo htmlspecialchars($user['phone']); ?></span>
                        <?php endif; ?> -->
                        <?php if (!empty($user['created_at'])): ?>
                            <span> | Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (hasAnyRole(['authority', 'admin'])): ?>
                <div style="text-align: center; margin-bottom: 16px;">
                    <h3 style="color: #1976d2; font-size: 1.2rem; margin: 0; display: inline-block;">Issues Overview</h3>
                    <button id="refresh-stats" style="margin-left: 10px; background: #2196F3; color: white; border: none; border-radius: 4px; padding: 6px 12px; cursor: pointer; font-size: 0.9rem;">
                        🔄 Refresh
                    </button>
                </div>
            <?php endif; ?>
            <!-- Report Stats -->
            <div class="profile-stats<?php echo hasAnyRole(['authority', 'admin']) ? ' authority-admin' : ''; ?>">
                <div class="stat-card">
                    <h4><?php echo hasAnyRole(['authority', 'admin']) ? 'Total Issues' : 'Total Issued'; ?></h4>
                    <div class="stat-number"><?php echo $myReports; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Pending</h4>
                    <div class="stat-number"><?php echo $pending; ?></div>
                </div>
                <div class="stat-card">
                    <h4>In Progress</h4>
                    <div class="stat-number"><?php echo $inProgress; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Fixed</h4>
                    <div class="stat-number"><?php echo $fixed; ?></div>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST" class="profile-form">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                <label for="role">Role:</label>
                <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled>
                <label for="username">Username:</label>
                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <label for="phone">Phone:</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                <button type="submit" class="btn-primary">Update Profile</button>
            </form>
            <a href="dashboard.php" class="btn-secondary">← Back to Dashboard</a>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Handle form submission with AJAX
        $('.profile-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.text();
            
            // Show loading state
            submitBtn.prop('disabled', true).text('Updating...');
            
            // Clear previous messages
            $('.alert').remove();
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    // Parse the response to extract success/error messages
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(response, 'text/html');
                    const successMsg = doc.querySelector('.alert-success');
                    const errorMsg = doc.querySelector('.alert-error');
                    
                    if (successMsg) {
                        // Show success message
                        $('<div class="alert alert-success">' + successMsg.textContent + '</div>')
                            .insertAfter('.profile-stats');
                        
                        // Update profile display with new data
                        updateProfileDisplay(doc);
                        
                        // Scroll to top to show success message
                        $('html, body').animate({scrollTop: 0}, 500);
                    } else if (errorMsg) {
                        // Show error message
                        $('<div class="alert alert-error">' + errorMsg.textContent + '</div>')
                            .insertAfter('.profile-stats');
                        
                        // Scroll to top to show error message
                        $('html, body').animate({scrollTop: 0}, 500);
                    }
                },
                error: function() {
                    $('<div class="alert alert-error">An error occurred. Please try again.</div>')
                        .insertAfter('.profile-stats');
                },
                complete: function() {
                    // Reset button state
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Function to update profile display with new data
        function updateProfileDisplay(doc) {
            // Update profile summary
            const newFullName = doc.querySelector('.profile-details h2').textContent;
            const newEmail = doc.querySelector('.profile-details .email').textContent;
            const newPhone = doc.querySelector('.profile-details .meta span:last-child')?.textContent;
            
            $('.profile-details h2').text(newFullName);
            $('.profile-details .email').text(newEmail);
            
            // Update avatar initial
            $('.profile-avatar span').text(newFullName.charAt(0).toUpperCase());
            
            // Update form values
            $('#full_name').val(newFullName);
            $('#email').val(newEmail);
            if (newPhone && newPhone.includes('📞')) {
                $('#phone').val(newPhone.replace('📞 ', ''));
            }
            
            // Update stats if they changed
            const newStats = doc.querySelectorAll('.stat-number');
            $('.stat-number').each(function(index) {
                if (newStats[index]) {
                    $(this).text(newStats[index].textContent);
                }
            });
        }
        
        // Add real-time validation
        $('#email').on('blur', function() {
            const email = $(this).val();
            if (email && !isValidEmail(email)) {
                $(this).addClass('error');
                if (!$('#email-error').length) {
                    $(this).after('<div id="email-error" class="field-error">Please enter a valid email address.</div>');
                }
            } else {
                $(this).removeClass('error');
                $('#email-error').remove();
            }
        });
        
        $('#full_name').on('blur', function() {
            const name = $(this).val();
            if (name && name.length < 2) {
                $(this).addClass('error');
                if (!$('#name-error').length) {
                    $(this).after('<div id="name-error" class="field-error">Name must be at least 2 characters long.</div>');
                }
            } else {
                $(this).removeClass('error');
                $('#name-error').remove();
            }
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Handle refresh stats button
        $('#refresh-stats').on('click', function() {
            const btn = $(this);
            const originalText = btn.text();
            
            // Show loading state
            btn.prop('disabled', true).text('🔄 Refreshing...');
            
            // Reload the page to get fresh data
            setTimeout(function() {
                window.location.reload();
            }, 500);
        });
        
        // Auto-refresh stats every 30 seconds for authority/admin users
        if ($('#refresh-stats').length > 0) {
            setInterval(function() {
                // Silently refresh stats without user interaction
                $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    success: function(response) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(response, 'text/html');
                        const newStats = doc.querySelectorAll('.stat-number');
                        
                        // Update stats with animation
                        $('.stat-number').each(function(index) {
                            if (newStats[index]) {
                                const newValue = newStats[index].textContent;
                                const currentValue = $(this).text();
                                
                                if (newValue !== currentValue) {
                                    $(this).fadeOut(200, function() {
                                        $(this).text(newValue).fadeIn(200);
                                    });
                                }
                            }
                        });
                    }
                });
            }, 30000); // 30 seconds
        }
    });
    </script>
    
    <style>
    .field-error {
        color: #f44336;
        font-size: 0.9rem;
        margin-top: 4px;
    }
    
    .profile-form input.error {
        border-color: #f44336;
        background-color: #ffebee;
    }
    
    .profile-form input:focus.error {
        border-color: #f44336;
        box-shadow: 0 0 0 2px rgba(244, 67, 54, 0.2);
    }
    </style>
</body>
</html>