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
