<?php
// admin/includes/sidebar.php
?>
<!-- Sidebar -->
<aside class="sidebar-left border-right bg-white shadow" id="leftSidebar" data-simplebar>
    <nav class="vertnav navbar navbar-light">
        <!-- Brand Logo -->
        <a class="navbar-brand" href="dashboard.php">
            <span class="avatar avatar-sm">
                <img src="assets/logo.png" alt="Logo" class="avatar-img rounded-circle">
            </span>
            <span class="ml-2">Habits365</span>
        </a>
        <!-- Sidebar Menu -->
        <ul class="navbar-nav flex-fill w-100 mb-2">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fe fe-home fe-16"></i>
                    <span class="ml-3 item-text">Dashboard</span>
                </a>
            </li>
            <!-- User Management -->
            <li class="nav-item">
                <a class="nav-link" href="user-management.php">
                    <i class="fe fe-users fe-16"></i>
                    <span class="ml-3 item-text">Manage Users</span>
                </a>
            </li>
            <!-- Batch Management -->
            <li class="nav-item">
                <a class="nav-link" href="batch-management.php">
                    <i class="fe fe-folder fe-16"></i>
                    <span class="ml-3 item-text">Manage Batches</span>
                </a>
            </li>
            <!-- Habit Management -->
            <li class="nav-item">
                <a class="nav-link" href="habit-management.php">
                    <i class="fe fe-list fe-16"></i>
                    <span class="ml-3 item-text">Manage Habits</span>
                </a>
            </li>
            <!-- Reward Management -->
            <li class="nav-item">
                <a class="nav-link" href="reward-management.php">
                    <i class="fe fe-award fe-16"></i>
                    <span class="ml-3 item-text">Manage Rewards</span>
                </a>
            </li>
            <!-- Upload Management -->
            <li class="nav-item">
                <a class="nav-link" href="upload-management.php">
                    <i class="fe fe-upload fe-16"></i>
                    <span class="ml-3 item-text">Manage Uploads</span>
                </a>
            </li>
            <!-- Certificate Management -->
            <li class="nav-item">
                <a class="nav-link" href="certificate-management.php">
                    <i class="fe fe-file-text fe-16"></i>
                    <span class="ml-3 item-text">Manage Certificates</span>
                </a>
            </li>
            <!-- Add more navigation items as needed -->
        </ul>
    </nav>
</aside>
