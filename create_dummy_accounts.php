<?php
/**
 * CivicVoice - Dummy Account Generator
 * This script generates proper password hashes for dummy accounts
 * Run this script to get the correct password hashes for your dummy accounts
 */

require_once 'config.php';

echo "<h2>CivicVoice - Dummy Account Password Generator</h2>";
echo "<p>This script generates properly hashed passwords for dummy accounts.</p>";

// Define dummy accounts with their plain passwords
$dummyAccounts = [
    'admin' => [
        'username' => 'admin',
        'email' => 'admin@civicvoice.test',
        'password' => 'admin123',
        'full_name' => 'System Administrator',
        'role' => 'admin'
    ],
    'authority_john' => [
        'username' => 'authority_john',
        'email' => 'john.smith@cityworks.test',
        'password' => 'authority123',
        'full_name' => 'John Smith',
        'role' => 'authority'
    ],
    'authority_sarah' => [
        'username' => 'authority_sarah',
        'email' => 'sarah.johnson@maintenance.test',
        'password' => 'authority456',
        'full_name' => 'Sarah Johnson',
        'role' => 'authority'
    ],
    'mike_wilson' => [
        'username' => 'mike_wilson',
        'email' => 'mike.wilson@email.test',
        'password' => 'citizen123',
        'full_name' => 'Mike Wilson',
        'role' => 'citizen'
    ],
    'emily_davis' => [
        'username' => 'emily_davis',
        'email' => 'emily.davis@email.test',
        'password' => 'citizen456',
        'full_name' => 'Emily Davis',
        'role' => 'citizen'
    ],
    'robert_brown' => [
        'username' => 'robert_brown',
        'email' => 'robert.brown@email.test',
        'password' => 'citizen789',
        'full_name' => 'Robert Brown',
        'role' => 'citizen'
    ],
    'lisa_garcia' => [
        'username' => 'lisa_garcia',
        'email' => 'lisa.garcia@email.test',
        'password' => 'citizen101',
        'full_name' => 'Lisa Garcia',
        'role' => 'citizen'
    ]
];

// Function to create dummy accounts
function createDummyAccounts($accounts) {
    try {
        $pdo = getDbConnection();
        $created = 0;
        $updated = 0;
        
        foreach ($accounts as $key => $account) {
            $hashedPassword = password_hash($account['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            // Check if user already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$account['username'], $account['email']]);
            $existingUser = $checkStmt->fetch();
            
            if ($existingUser) {
                // Update existing user
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET password_hash = ?, full_name = ?, email = ?, role = ?, is_active = 1, email_verified = 1, updated_at = NOW()
                    WHERE username = ?
                ");
                $updateStmt->execute([
                    $hashedPassword,
                    $account['full_name'],
                    $account['email'],
                    $account['role'],
                    $account['username']
                ]);
                $updated++;
                
                echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
                echo "✅ <strong>Updated:</strong> " . htmlspecialchars($account['username']) . " (" . htmlspecialchars($account['role']) . ")";
                echo "</div>";
            } else {
                // Create new user
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, is_active, email_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, 1, 1, NOW())
                ");
                $insertStmt->execute([
                    $account['username'],
                    $account['email'],
                    $hashedPassword,
                    $account['full_name'],
                    $account['role']
                ]);
                $created++;
                
                echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
                echo "✅ <strong>Created:</strong> " . htmlspecialchars($account['username']) . " (" . htmlspecialchars($account['role']) . ")";
                echo "</div>";
            }
        }
        
        return ['created' => $created, 'updated' => $updated];
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
        return false;
    }
}

// Function to display password information
function displayPasswordInfo($accounts) {
    echo "<h3>Dummy Account Credentials</h3>";
    echo "<div style='background: #e2e3e5; border: 1px solid #d6d8db; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p><strong>⚠️ Important:</strong> These are development/testing credentials only. Do not use in production!</p>";
    echo "</div>";
    
    // Group by role
    $roleGroups = [];
    foreach ($accounts as $account) {
        $roleGroups[$account['role']][] = $account;
    }
    
    foreach ($roleGroups as $role => $users) {
        echo "<h4>" . ucfirst($role) . " Accounts:</h4>";
        echo "<table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Username</th>";
        echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Email</th>";
        echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Password</th>";
        echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Full Name</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 10px;'><code>" . htmlspecialchars($user['username']) . "</code></td>";
            echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 10px;'><code>" . htmlspecialchars($user['password']) . "</code></td>";
            echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check if form was submitted
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        echo "<h3>Creating Dummy Accounts...</h3>";
        $result = createDummyAccounts($dummyAccounts);
        
        if ($result) {
            echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
            echo "<h4>Summary:</h4>";
            echo "<p>✅ Accounts created: " . $result['created'] . "</p>";
            echo "<p>🔄 Accounts updated: " . $result['updated'] . "</p>";
            echo "<p><strong>Total accounts processed: " . ($result['created'] + $result['updated']) . "</strong></p>";
            echo "</div>";
        }
    }
}

// Display the form and account information
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
}

.container {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.btn {
    background: #007bff;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    margin: 10px 5px;
    text-decoration: none;
    display: inline-block;
}

.btn:hover {
    background: #0056b3;
}

.btn-danger {
    background: #dc3545;
}

.btn-danger:hover {
    background: #c82333;
}

.btn-success {
    background: #28a745;
}

.btn-success:hover {
    background: #218838;
}
</style>

<div class="container">
    <form method="POST" style="margin: 20px 0;">
        <button type="submit" name="action" value="create" class="btn btn-success">
            🚀 Create/Update Dummy Accounts
        </button>
        
        <a href="login.php" class="btn">
            🔐 Go to Login Page
        </a>
        
        <a href="index.php" class="btn">
            🏠 Go to Home Page
        </a>
    </form>
    
    <?php displayPasswordInfo($dummyAccounts); ?>
    
    <div style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h4>Usage Instructions:</h4>
        <ol>
            <li>Click "Create/Update Dummy Accounts" to insert the test accounts into your database</li>
            <li>Use the credentials above to login and test different user roles</li>
            <li>Each role has different permissions and dashboard views</li>
            <li>Remember to remove or change these accounts before going to production</li>
        </ol>
    </div>
    
    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h4>⚠️ Security Notice:</h4>
        <p>These dummy accounts are for development and testing purposes only. Before deploying to production:</p>
        <ul>
            <li>Delete all dummy accounts</li>
            <li>Create proper admin accounts with strong passwords</li>
            <li>Use real email domains, not .test domains</li>
            <li>Implement proper user verification processes</li>
        </ul>
    </div>
</div>

<?php
// Additional functions for development
function verifyPasswordHashes($accounts) {
    echo "<h3>Password Hash Verification</h3>";
    
    foreach ($accounts as $account) {
        $hash = password_hash($account['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $verify = password_verify($account['password'], $hash);
        
        echo "<p>";
        echo "<strong>" . htmlspecialchars($account['username']) . ":</strong> ";
        echo $verify ? "✅ Valid" : "❌ Invalid";
        echo " (Password: " . htmlspecialchars($account['password']) . ")";
        echo "</p>";
    }
}

// Show additional debug info if requested
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<hr>";
    verifyPasswordHashes($dummyAccounts);
}
?>