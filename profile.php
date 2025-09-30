<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
$page_title = "Your Profile - CivicVoice";
$success = '';
$error = '';

// Fetch report stats for this user
$myReports = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ?", [$user['id']])->fetchColumn();
$pending = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'pending'", [$user['id']])->fetchColumn();
$inProgress = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'in-progress'", [$user['id']])->fetchColumn();
$fixed = executeQuery("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'fixed'", [$user['id']])->fetchColumn();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error = 'Name and email are required.';
    } else {
        try {
            executeQuery(
                "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?",
                [$full_name, $email, $phone, $user['id']]
            );
            $success = 'Profile updated successfully!';
            $user = getCurrentUser();
        } catch (Exception $e) {
            $error = 'Failed to update profile.';
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
                        <?php if (!empty($user['phone'])): ?>
                            <span> | 📞 <?php echo htmlspecialchars($user['phone']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($user['created_at'])): ?>
                            <span> | Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Report Stats -->
            <div class="profile-stats">
                <div class="stat-card">
                    <h4>Total Issued</h4>
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
</body>
</html>