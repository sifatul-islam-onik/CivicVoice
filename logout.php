<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Require login to logout
requireLogin();
//testing
// Process logout
logout();

// Redirect to login page with success message
header("Location: login.php?logged_out=1");
exit();
?>

