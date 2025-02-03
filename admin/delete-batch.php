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
    header("Location: batch-management.php?error=Invalid batch ID.");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Check if the batch has active students or linked data
$checkQuery = "
    SELECT COUNT(*) FROM batches_students WHERE batch_id = ? 
    UNION ALL
    SELECT COUNT(*) FROM user_habits WHERE batch_id = ?
";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bind_param("ii", $batch_id, $batch_id);
$checkStmt->execute();
$checkStmt->bind_result($count);
$checkStmt->fetch();
$checkStmt->close();

if ($count > 0) {
    header("Location: batch-management.php?error=Cannot delete batch. It has associated users or data.");
    exit();
}

// Delete batch (ensure cascading deletion where applicable)
$deleteQuery = "DELETE FROM batches WHERE id = ?";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bind_param("i", $batch_id);

if ($deleteStmt->execute()) {
    header("Location: batch-management.php?success=Batch deleted successfully.");
} else {
    error_log("Batch Deletion Failed: " . $db->error);
    header("Location: batch-management.php?error=Unable to delete batch.");
}

$deleteStmt->close();
?>
