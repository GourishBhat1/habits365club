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
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Top Navbar -->
    <nav class="topnav navbar navbar-light">
        <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar">
            <i class="fe fe-menu navbar-toggler-icon"></i>
        </button>
        <form class="form-inline mr-auto searchform text-muted">
            <input class="form-control mr-sm-2 bg-transparent border-0 pl-4 text-muted" type="search" placeholder="Type something..." aria-label="Search">
        </form>
        <ul class="nav">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-muted pr-0" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown">
                    <span class="avatar avatar-sm mt-2"><img src="./assets/avatars/face-1.jpg" alt="..." class="avatar-img rounded-circle"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#">Profile</a>
                    <a class="dropdown-item" href="#">Settings</a>
                    <a class="dropdown-item" href="logout.php">Logout</a>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar-left border-right bg-white shadow" id="leftSidebar" data-simplebar>
        <nav class="vertnav navbar navbar-light">
            <ul class="navbar-nav flex-fill w-100 mb-2">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fe fe-home fe-16"></i><span class="ml-3 item-text">Dashboard</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="habit-management.php"><i class="fe fe-list fe-16"></i><span class="ml-3 item-text">Manage Habits</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="user-management.php"><i class="fe fe-users fe-16"></i><span class="ml-3 item-text">Manage Users</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php"><i class="fe fe-bar-chart-2 fe-16"></i><span class="ml-3 item-text">Reports</span></a>
                </li>
            </ul>
        </nav>
    </aside>

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
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- All Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/moment.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/simplebar.min.js"></script>
<script src="js/daterangepicker.js"></script>
<script src="js/jquery.stickOnScroll.js"></script>
<script src="js/tinycolor-min.js"></script>
<script src="js/config.js"></script>
<script src="js/d3.min.js"></script>
<script src="js/topojson.min.js"></script>
<script src="js/datamaps.all.min.js"></script>
<script src="js/datamaps-zoomto.js"></script>
<script src="js/datamaps.custom.js"></script>
<script src="js/Chart.min.js"></script>
<script>
    Chart.defaults.global.defaultFontFamily = base.defaultFontFamily;
    Chart.defaults.global.defaultFontColor = colors.mutedColor;
</script>
<script src="js/gauge.min.js"></script>
<script src="js/jquery.sparkline.min.js"></script>
<script src="js/apexcharts.min.js"></script>
<script src="js/apexcharts.custom.js"></script>
<script src="js/jquery.mask.min.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.steps.min.js"></script>
<script src="js/jquery.validate.min.js"></script>
<script src="js/jquery.timepicker.js"></script>
<script src="js/dropzone.min.js"></script>
<script src="js/uppy.min.js"></script>
<script src="js/quill.min.js"></script>
<script src="js/apps.js"></script>
</body>
</html>
