<?php
// habit_history.php

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
  <title>Parent Dashboard - Habit History</title>

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
                    <h2 class="page-title">Habit History</h2>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong>Your Past Habit Submissions</strong>
                        </div>
                        <div class="card-body" data-simplebar style="max-height:400px;">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Habit</th>
                                        <th>Status</th>
                                        <th>Feedback (if rejected)</th>
                                        <th>Score</th>
                                        <th>Current Streak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Sample placeholder data. Replace with dynamic content. -->
                                    <tr>
                                        <td>2025-01-18</td>
                                        <td>Daily Reading</td>
                                        <td><span class="badge badge-success">Approved</span></td>
                                        <td>-</td>
                                        <td>10</td>
                                        <td>5 days</td>
                                    </tr>
                                    <tr>
                                        <td>2025-01-17</td>
                                        <td>Morning Exercise</td>
                                        <td><span class="badge badge-danger">Rejected</span></td>
                                        <td>Video too short</td>
                                        <td>0</td>
                                        <td>0 days</td>
                                    </tr>
                                    <tr>
                                        <td>2025-01-16</td>
                                        <td>Daily Reading</td>
                                        <td><span class="badge badge-warning">Pending</span></td>
                                        <td>-</td>
                                        <td>--</td>
                                        <td>--</td>
                                    </tr>
                                    <!-- etc. -->
                                </tbody>
                            </table>
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
