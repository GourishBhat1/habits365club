<?php
// admin/dashboard.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Dashboard - Habits Web App</title>

    <!-- Including all CSS files -->
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
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled">
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
                    <h2 class="page-title">Admin Dashboard</h2>
                    <div class="row">
                        <!-- Stat Cards -->
                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <h6 class="mb-0">Balance</h6>
                                    <h3>$12,600</h3>
                                    <span class="text-muted">+20% from last month</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <h6 class="mb-0">Revenue</h6>
                                    <h3>$8,300</h3>
                                    <span class="text-muted">+15% from last month</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <h6 class="mb-0">Active Users</h6>
                                    <h3>540</h3>
                                    <span class="text-muted">5% increase</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <h6 class="mb-0">Habits Tracked</h6>
                                    <h3>1,245</h3>
                                    <span class="text-muted">8% increase</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities & Summary Table -->
                    <div class="row">
                        <!-- Recent Activities -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Activities</h5>
                                </div>
                                <div class="card-body" data-simplebar style="max-height: 300px;">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item">User John completed "Daily Reading"</li>
                                        <li class="list-group-item">User Sarah earned a "Habit Master" badge</li>
                                        <li class="list-group-item">User Tom uploaded progress for "Morning Exercise"</li>
                                        <li class="list-group-item">User Alice reached "30-day Streak"</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Table -->
                        <div class="col-lg-6">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Habits Summary</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Habit</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>John Doe</td>
                                            <td>Daily Reading</td>
                                            <td><span class="badge badge-success">Completed</span></td>
                                            <td>11/05/2024</td>
                                        </tr>
                                        <tr>
                                            <td>Sarah Lee</td>
                                            <td>Exercise</td>
                                            <td><span class="badge badge-warning">In Progress</span></td>
                                            <td>11/04/2024</td>
                                        </tr>
                                        <tr>
                                            <td>Tom Wilson</td>
                                            <td>Study Hour</td>
                                            <td><span class="badge badge-danger">Missed</span></td>
                                            <td>11/03/2024</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div> <!-- End of Recent Activities & Summary Table -->
                </div> <!-- End of col-12 -->
            </div> <!-- End of row -->
        </div> <!-- End of container-fluid -->
    </main>
</div> <!-- End of wrapper -->

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
