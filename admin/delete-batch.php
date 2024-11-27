<?php
// admin/delete-batch.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Get batch ID from GET parameter
$batch_id = $_GET['id'] ?? '';

if (empty($batch_id)) {
    header("Location: batch-management.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Delete batch
$query = "DELETE FROM batches WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $batch_id);

if ($stmt->execute()) {
    header("Location: batch-management.php?msg=Batch deleted successfully.");
} else {
    header("Location: batch-management.php?error=Unable to delete batch.");
}

$stmt->close();
?>
