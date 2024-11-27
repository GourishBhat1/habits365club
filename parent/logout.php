<?php
// parent/logout.php

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear the authentication cookies if set
if (isset($_COOKIE['parent_email'])) {
    setcookie('parent_email', '', time() - 3600, "/");
}

// Redirect to login page
header("Location: index.php");
exit();
?>
