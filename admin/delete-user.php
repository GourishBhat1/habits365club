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
    header("Location: user-management.php?error=Invalid user ID.");
    exit();
}

require_once '../connection.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Prevent Admin Self-Deletion
$admin_email = $_SESSION['admin_email'] ?? $_COOKIE['admin_email'];
$checkQuery = "SELECT id, email, role FROM users WHERE id = ?";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bind_param("i", $user_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows !== 1) {
    header("Location: user-management.php?error=User not found.");
    exit();
}

$user = $checkResult->fetch_assoc();

// Prevent Deleting Super Admin (Optional)
if ($user['role'] === 'admin' && $user['email'] === $admin_email) {
    header("Location: user-management.php?error=Cannot delete your own account.");
    exit();
}

// Proceed with Deletion
$query = "DELETE FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    header("Location: user-management.php?success=User deleted successfully.");
} else {
    header("Location: user-management.php?error=Unable to delete user.");
}

$stmt->close();
$checkStmt->close();
?>
