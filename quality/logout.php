<?php
// quality/logout.php

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear authentication cookies (matching login setup)
if (isset($_COOKIE['quality_username'])) {
    setcookie("quality_username", "", time() - 3600, "/", "", false, true); 
}

// Redirect to login page
header("Location: index.php?message=logged_out");
exit();
?>
