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

// Check if batch has active users or associated data
$checkQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE batch_id = ?) AS parent_count,
        (SELECT COUNT(*) FROM habit_tracking WHERE user_id IN (SELECT id FROM users WHERE batch_id = ?)) AS habit_count
";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bind_param("ii", $batch_id, $batch_id);
$checkStmt->execute();
$checkStmt->bind_result($parent_count, $habit_count);
$checkStmt->fetch();
$checkStmt->close();

// Prevent deletion if batch has parents or habits assigned
if ($parent_count > 0 || $habit_count > 0) {
    header("Location: batch-management.php?error=Cannot delete batch. It has associated users or habit data.");
    exit();
}

// Remove parents from this batch before deletion
$clearParentsQuery = "UPDATE users SET batch_id = NULL WHERE batch_id = ?";
$clearParentsStmt = $db->prepare($clearParentsQuery);
$clearParentsStmt->bind_param("i", $batch_id);
$clearParentsStmt->execute();
$clearParentsStmt->close();

// Finally, delete the batch
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
