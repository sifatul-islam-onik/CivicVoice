<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
$success = '';

// Check for success message from registration
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Registration successful! Please login with your credentials.';
} elseif (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $success = 'You have been successfully logged out.';
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? ''); // Can be username or email
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($login) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            // Check if login is email or username
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            
            $stmt = executeQuery(
                "SELECT id, username, email, password_hash, full_name, role, is_active FROM users WHERE $field = ? AND is_active = 1",
                [$login]
            );
            
            $user = $stmt->fetch();
            
            if ($user) {
                // User found, now verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Login successful
                    createUserSession($user['id'], $remember_me);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Redirect based on role
                    redirectToDashboard();
                } else {
                    $error = 'Invalid username/email or password.';
                }
            } else {
                $error = 'Invalid username/email or password.';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

$page_title = "Login - CivicVoice";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Login to CivicVoice to manage community issues</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="login">Username or Email</label>
                    <input type="text" id="login" name="login" 
                           value="<?php echo htmlspecialchars($login ?? ''); ?>" 
                           required placeholder="Enter username or email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <label for="remember_me">Remember me for 30 days</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Login</button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p><a href="forgot_password.php">Forgot your password?</a></p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus on login field
        document.getElementById('login').focus();
        
        // Enter key handling
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.target.closest('form')?.submit();
            }
        });
    </script>
</body>
</html>