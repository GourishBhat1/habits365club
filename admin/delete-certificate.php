<?php
// admin/delete-certificate.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Get certificate ID from GET parameter
$certificate_id = $_GET['id'] ?? '';

if (empty($certificate_id)) {
    header("Location: certificate-management.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch certificate details to delete the file from the server
$query = "SELECT certificate_path FROM certificates WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $certificate_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: certificate-management.php?error=Certificate not found.");
    exit();
}

$certificate = $result->fetch_assoc();

// Delete the file from the server
if (file_exists($certificate['certificate_path'])) {
    unlink($certificate['certificate_path']);
}

// Delete the record from the database
$deleteQuery = "DELETE FROM certificates WHERE id = ?";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bind_param("i", $certificate_id);

if ($deleteStmt->execute()) {
    header("Location: certificate-management.php?msg=Certificate deleted successfully.");
} else {
    header("Location: certificate-management.php?error=Unable to delete certificate.");
}

$deleteStmt->close();
?>
