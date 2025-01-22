<?php
// leaderboard.php

// Start session
session_start();

// Check if the parent is authenticated
if (!isset($_SESSION['parent_email']) && !isset($_COOKIE['parent_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection or any other required files here
// require 'config.php'; // Example only, adjust to your setup

// Suppose we detect the parent's batch from session or DB
// $parent_batch = $_SESSION['batch'] ?? 'Batch A';  // Example

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Parent Dashboard - Leaderboard</title>

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
                    <h2 class="page-title">Leaderboard</h2>
                    <p class="text-muted">Showing top performers in your batch.</p>
                    <!-- Display top performers in a table -->
                    <div class="card shadow">
                        <div class="card-header">
                            <strong>Leaderboard - Batch 
                                <?php 
                                    // echo $parent_batch; 
                                    // For placeholder, just show 'Batch A'
                                    echo 'Batch A'; 
                                ?>
                            </strong>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Parent Name</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Sample placeholder data. Replace with dynamic content sorted by score. -->
                                    <tr>
                                        <td>1</td>
                                        <td>John Doe</td>
                                        <td>1200</td>
                                    </tr>
                                    <tr>
                                        <td>2</td>
                                        <td>Jane Smith</td>
                                        <td>1150</td>
                                    </tr>
                                    <tr>
                                        <td>3</td>
                                        <td>Michael Brown</td>
                                        <td>1100</td>
                                    </tr>
                                    <tr>
                                        <td>4</td>
                                        <td>Sarah Johnson</td>
                                        <td>1090</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div> <!-- .card -->
                </div> <!-- .col-12 -->
            </div> <!-- .row -->
        </div> <!-- .container-fluid -->
    </main>
</div> <!-- .wrapper -->

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
