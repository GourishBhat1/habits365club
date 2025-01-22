<?php
// upload_habits.php

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
  <title>Parent Dashboard - Upload Habits</title>

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
                    <h2 class="page-title">Upload Habits</h2>
                    <!-- You can fetch and display the global habits from your database here -->
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong>Assigned Habits</strong>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <table class="table table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Habit</th>
                                            <th>Description</th>
                                            <th>Evidence Upload</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Sample placeholder data. Replace with dynamic content. -->
                                        <tr>
                                            <td>Daily Reading</td>
                                            <td>Read 20 pages of a book</td>
                                            <td>
                                                <input type="file" name="evidence_reading" accept="image/*,video/*" class="form-control-file">
                                            </td>
                                            <td>
                                                <span class="badge badge-warning">Pending</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Morning Exercise</td>
                                            <td>15 minutes of exercise</td>
                                            <td>
                                                <input type="file" name="evidence_exercise" accept="image/*,video/*" class="form-control-file">
                                            </td>
                                            <td>
                                                <span class="badge badge-success">Approved</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Study Hour</td>
                                            <td>1 hour of focused study</td>
                                            <td>
                                                <input type="file" name="evidence_study" accept="image/*,video/*" class="form-control-file">
                                            </td>
                                            <td>
                                                <span class="badge badge-danger">Rejected</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <!-- Submit button for uploading all evidence at once -->
                                <button type="submit" class="btn btn-primary mt-3">Submit Evidence</button>
                            </form>
                            <!-- On form submission, handle file uploads and database updates accordingly -->
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
