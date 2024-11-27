<?php
// admin/delete-certificate.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include the database connection
require_once __DIR__ . '/../connection.php';

// Get certificate ID from GET parameter
$certificate_id = $_GET['id'] ?? '';

if (empty($certificate_id)) {
    header("Location: certificate-management.php?error=Certificate ID missing.");
    exit();
}

// Fetch certificate details to get the image path
$query = "SELECT certificate_path FROM certificates WHERE id = ?";

$stmt = $db->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $certificate = $result->fetch_assoc();
    } else {
        header("Location: certificate-management.php?error=Certificate not found.");
        exit();
    }
    $stmt->close();
} else {
    header("Location: certificate-management.php?error=Database error.");
    exit();
}

// Delete the certificate record from the database
$deleteQuery = "DELETE FROM certificates WHERE id = ?";
$deleteStmt = $db->prepare($deleteQuery);
if ($deleteStmt) {
    $deleteStmt->bind_param("i", $certificate_id);
    if ($deleteStmt->execute()) {
        // Delete the image file
        $imagePath = __DIR__ . '/../' . $certificate['certificate_path'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        header("Location: certificate-management.php?msg=Certificate deleted successfully.");
    } else {
        header("Location: certificate-management.php?error=Failed to delete certificate.");
    }
    $deleteStmt->close();
} else {
    header("Location: certificate-management.php?error=Database error.");
    exit();
}
?>
