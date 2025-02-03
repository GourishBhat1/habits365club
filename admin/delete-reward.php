<?php
// admin/delete-reward.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Get reward ID from GET parameter
$reward_id = $_GET['id'] ?? '';

if (empty($reward_id)) {
    header("Location: reward-management.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Delete the reward
$deleteQuery = "DELETE FROM rewards WHERE id = ?";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bind_param("i", $reward_id);

if ($deleteStmt->execute()) {
    header("Location: reward-management.php?msg=Reward deleted successfully.");
} else {
    header("Location: reward-management.php?error=Unable to delete reward.");
}

$deleteStmt->close();
?>
