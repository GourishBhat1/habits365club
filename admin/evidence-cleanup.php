<?php
// admin/evidence-cleanup.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// require_once '../connection.php';

$error = '';
$success = '';

// If 'Clean Up' is clicked
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup'])) {
    // Example logic:
    // 1. Identify old or orphaned files in your uploads directory
    // 2. Delete them from the server
    // 3. Possibly remove references from the DB
    
    // Placeholder logic:
    $filesRemoved = 5; // Example
    $success = "$filesRemoved old evidence files have been removed (placeholder).";
}

// Optionally, you can list files that would be cleaned up before removing them
$sampleOrphanedFiles = [
    'uploads/old_file1.jpg',
    'uploads/old_file2.mp4',
    // ...
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evidence Cleanup - Admin</title>

    <!-- CSS includes from admin/dashboard.php -->
    <link rel="stylesheet" href="css/simplebar.css">
    <link rel="stylesheet" href="css/feather.css">
    <link rel="stylesheet" href="css/select2.css">
    <link rel="stylesheet" href="css/dropzone.css">
    <link rel="stylesheet" href="css/uppy.min.css">
    <link rel="stylesheet" href="css/jquery.steps.css">
    <link rel="stylesheet" href="css/jquery.timepicker.css">
    <link rel="stylesheet" href="css/quill.snow.css">
    <link rel="stylesheet" href="css/daterangepicker.css">
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Navbar & Sidebar -->
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Evidence Cleanup</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header">
                    <strong>Manual Cleanup</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted">Below are sample old/orphaned files identified for removal (placeholder).</p>
                    <ul>
                        <?php foreach ($sampleOrphanedFiles as $file): ?>
                            <li><?php echo htmlspecialchars($file); ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete these files?');">
                        <button type="submit" name="cleanup" class="btn btn-danger">Clean Up Files</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div><!-- End wrapper -->

<?php include 'includes/footer.php'; ?>
</body>
</html>
