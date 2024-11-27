<?php
// admin/delete-user.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Get user ID from GET parameter
$user_id = $_GET['id'] ?? '';

if (empty($user_id)) {
    header("Location: user-management.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Prepare delete statement
$query = "DELETE FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    header("Location: user-management.php?msg=User deleted successfully.");
} else {
    header("Location: user-management.php?error=Unable to delete user.");
}

$stmt->close();
?>
