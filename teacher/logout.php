<?php
// teacher/logout.php

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear authentication cookies (matching login setup)
if (isset($_COOKIE['teacher_email'])) {
    setcookie("teacher_email", "", time() - 3600, "/", "", false, true); // Clear with same attributes
}

// Redirect to login page
header("Location: index.php?message=logged_out");
exit();
?>
