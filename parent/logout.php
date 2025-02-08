<?php
// parent/logout.php

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session if it exists
if (session_id()) {
    session_destroy();
}

// Clear the authentication cookies securely
if (isset($_COOKIE['parent_username'])) {
    setcookie("parent_username", "", time() - 3600, "/", "", false, true);
}

// Redirect to login page
header("Location: index.php");
exit();
?>
