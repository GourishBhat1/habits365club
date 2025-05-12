<?php
// admin/delete-certificate.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$certificate_id = $_GET['id'] ?? '';
if (empty($certificate_id)) {
    header("Location: certificate-management.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get the certificate file path
$pathQuery = "SELECT certificate_path FROM certificates WHERE id = ?";
$pathStmt = $db->prepare($pathQuery);
$pathStmt->bind_param("i", $certificate_id);
$pathStmt->execute();
$pathStmt->bind_result($certificate_path);
$pathStmt->fetch();
$pathStmt->close();

$deleted_file = false;
if ($certificate_path) {
    $file = dirname(__DIR__) . '/certificates/' . $certificate_path;
    if (file_exists($file)) {
        $deleted_file = unlink($file);
    }
}

// Delete the DB record
$deleteQuery = "DELETE FROM certificates WHERE id = ?";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bind_param("i", $certificate_id);
$success = $deleteStmt->execute();
$deleteStmt->close();

if ($success) {
    $msg = 'Certificate record deleted.';
    if ($deleted_file) {
        $msg .= ' File deleted.';
    } else {
        $msg .= ' File not found or could not be deleted.';
    }
    header("Location: certificate-management.php?success=" . urlencode($msg));
} else {
    header("Location: certificate-management.php?error=Unable to delete certificate.");
}
