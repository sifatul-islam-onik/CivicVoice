<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'citizen'; // Fixed role for public registration
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (emailExists($email)) {
        $error = 'Email already exists. Please use a different email.';
    } else {
        // Generate username from full name
        $username = generateUsername($full_name);
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        try {
            // Insert user into database
            $stmt = executeQuery(
                "INSERT INTO users (username, email, password_hash, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)",
                [$username, $email, $password_hash, $full_name, $phone, $role]
            );
            
                        $success = 'Registration successful! You can now log in with your credentials.';
            $username = $email = $full_name = '';
            
            // Clear form data
            $full_name = $email = $phone = '';
        } catch (Exception $e) {
            $error = 'Registration failed. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

$page_title = "Register - CivicVoice";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Add this line in the <head> section -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Join CivicVoice</h1>
                <p>Create a citizen account to report and track community issues</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($full_name ?? ''); ?>" 
                           required placeholder="Enter your full name">
                    <small class="form-help">Your username will be auto-generated from your full name</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                           required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($phone ?? ''); ?>" 
                           placeholder="Enter your phone number">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper" style="position:relative;">
                        <input type="password" id="password" name="password" required placeholder="Enter password (min. <?php echo PASSWORD_MIN_LENGTH; ?> characters)">
                        <span class="material-icons toggle-password"
                            onclick="togglePassword('password', this)"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;">

                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper" style="position:relative;">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                        <span class="material-icons toggle-password"
                            onclick="togglePassword('confirm_password', this)"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;">
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Create Account</button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p class="text-small">Note: Authority accounts are created by administrators only</p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-generate username preview
        document.getElementById('full_name').addEventListener('input', function() {
            const fullName = this.value;
            if (fullName.trim()) {
                const username = fullName.toLowerCase()
                    .replace(/[^a-z0-9\s]/g, '')
                    .replace(/\s+/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');
                
                const helpText = this.nextElementSibling;
                helpText.textContent = `Your username will be: ${username || '[generated from name]'}`;
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.textContent = "visibility_off";
            } else {
                input.type = "password";
                icon.textContent = "visibility";
            }
        }
    </script>
</body>
</html>