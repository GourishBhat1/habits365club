<?php
// admin/delete-habit.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Get habit ID from GET parameter
$habit_id = $_GET['id'] ?? '';

if (empty($habit_id)) {
    header("Location: habit-management.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Optional: Delete associated uploads and rewards if necessary
// Example: First delete uploads related to this habit
$deleteUploadsQuery = "DELETE FROM uploads WHERE habit_id = ?";
$deleteUploadsStmt = $db->prepare($deleteUploadsQuery);
$deleteUploadsStmt->bind_param("i", $habit_id);
$deleteUploadsStmt->execute();
$deleteUploadsStmt->close();

// Delete the habit
$deleteQuery = "DELETE FROM habits WHERE id = ?";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bind_param("i", $habit_id);

if ($deleteStmt->execute()) {
    header("Location: habit-management.php?msg=Habit deleted successfully.");
} else {
    header("Location: habit-management.php?error=Unable to delete habit.");
}

$deleteStmt->close();
?>
