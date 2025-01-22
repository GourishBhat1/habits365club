<?php
// notifications.php

// Start session
session_start();

// Check if the parent is authenticated
if (!isset($_SESSION['parent_email']) && !isset($_COOKIE['parent_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection or any other required files here
// require 'config.php'; // Example only, adjust to your setup

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Parent Dashboard - Notifications</title>

  <!-- Including all CSS files (same as in dashboard.php) -->
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
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12">
                    <h2 class="page-title">Notifications</h2>
                    <div class="card shadow">
                        <div class="card-header">
                            <strong>Your Notifications</strong>
                        </div>
                        <div class="card-body" data-simplebar style="max-height: 400px;">
                            <ul class="list-group list-group-flush">
                                <!-- Sample placeholder notifications. Replace with dynamic content. -->
                                <li class="list-group-item">
                                    <span class="badge badge-success">Approved</span> Your submission for "Daily Reading" was approved!
                                    <small class="text-muted d-block">2025-01-19 10:15 AM</small>
                                </li>
                                <li class="list-group-item">
                                    <span class="badge badge-danger">Rejected</span> Your submission for "Morning Exercise" was rejected.  
                                    <small class="text-muted d-block">2025-01-18 09:02 AM</small>
                                </li>
                                <li class="list-group-item">
                                    <span class="badge badge-info">Info</span> Reminder: Parent-teacher meeting on Jan 25.  
                                    <small class="text-muted d-block">2025-01-17 03:30 PM</small>
                                </li>
                                <li class="list-group-item">
                                    <span class="badge badge-warning">Update</span> Your batch's weekly challenge starts tomorrow!  
                                    <small class="text-muted d-block">2025-01-16 07:45 PM</small>
                                </li>
                                <!-- etc. -->
                            </ul>
                        </div>
                    </div>
                </div> <!-- .col-12 -->
            </div> <!-- .row -->
        </div> <!-- .container-fluid -->
    </main>
</div> <!-- .wrapper -->

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
