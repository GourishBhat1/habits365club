<?php
// admin/delete-habit.php

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

try {
    // Begin transaction for safe deletion
    $db->begin_transaction();

    // Delete any uploaded evidence related to this habit
    $deleteUploadsQuery = "DELETE FROM evidence_uploads WHERE habit_id = ?";
    $deleteUploadsStmt = $db->prepare($deleteUploadsQuery);
    $deleteUploadsStmt->bind_param("i", $habit_id);
    $deleteUploadsStmt->execute();
    $deleteUploadsStmt->close();

    // Delete rewards or any related records if applicable
    $deleteRewardsQuery = "DELETE FROM rewards WHERE habit_id = ?";
    $deleteRewardsStmt = $db->prepare($deleteRewardsQuery);
    $deleteRewardsStmt->bind_param("i", $habit_id);
    $deleteRewardsStmt->execute();
    $deleteRewardsStmt->close();

    // Delete the habit itself
    $deleteQuery = "DELETE FROM habits WHERE id = ?";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $habit_id);

    if ($deleteStmt->execute()) {
        $db->commit(); // Commit transaction
        header("Location: habit-management.php?msg=Habit deleted successfully.");
    } else {
        throw new Exception("Error deleting habit.");
    }

    $deleteStmt->close();
} catch (Exception $e) {
    $db->rollback(); // Rollback transaction on failure
    error_log("Habit Deletion Failed: " . $e->getMessage());
    header("Location: habit-management.php?error=Unable to delete habit.");
}
?>
