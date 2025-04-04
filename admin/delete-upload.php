<?php
// admin/delete-upload.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Get upload ID from GET parameter
$upload_id = $_GET['id'] ?? '';

if (empty($upload_id)) {
    header("Location: upload-management.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch upload details to delete the file from the server
$query = "SELECT file_path FROM uploads WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $upload_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: upload-management.php?error=Upload not found.");
    exit();
}

$upload = $result->fetch_assoc();

// Delete the file from the server
if (file_exists($upload['file_path'])) {
    unlink($upload['file_path']);
}

// Redirect after file deletion
header("Location: upload-management.php?msg=Upload file deleted from server, record retained.");
?>
