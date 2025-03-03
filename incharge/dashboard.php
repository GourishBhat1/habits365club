<?php
// incharge/dashboard.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the incharge is authenticated via session or cookie
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID from session or cookie
$incharge_id = $_SESSION['incharge_id'] ?? null;

if (!$incharge_id && isset($_COOKIE['incharge_username'])) {
    $incharge_username = $_COOKIE['incharge_username'];

    // Fetch incharge ID using username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
    if (!$stmt) {
        die("❌ SQL Error: " . $db->error);
    }
    $stmt->bind_param("s", $incharge_username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($incharge_id);
        $stmt->fetch();
        $_SESSION['incharge_id'] = $incharge_id;
    } else {
        header("Location: index.php?message=invalid_cookie");
        exit();
    }
    $stmt->close();
}

// Fetch assigned batches for the incharge
$batches = [];
$stmt = $db->prepare("SELECT id, name, created_at FROM batches WHERE incharge_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($batch = $result->fetch_assoc()) {
        $batches[] = $batch;
    }
    $stmt->close();
} else {
    $error = "Failed to retrieve batches.";
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Incharge Dashboard - Habits365Club</title>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <main role="main" class="main-content">
            <div class="container-fluid">
                <h2 class="page-title">Incharge Dashboard</h2>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <h5>Assigned Batches</h5>
                        <?php foreach ($batches as $batch): ?>
                            <p><?php echo htmlspecialchars($batch['name']); ?> (Created on: <?php echo $batch['created_at']; ?>)</p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
