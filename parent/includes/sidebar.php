<?php
// parent/includes/sidebar.php
?>
<!-- Sidebar -->
<aside class="sidebar-left border-right bg-white shadow" id="leftSidebar" data-simplebar>
    <nav class="vertnav navbar navbar-light">
        <!-- Brand / Logo -->
        <a class="navbar-brand" href="dashboard.php">
            <span class="avatar avatar-xl">
            <img src="../assets/images/habits_logo.png" alt="Logo" class="avatar-img rounded-circle">
            </span>
            <!-- <span class="ml-2">Habits365</span> -->
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
            <!-- Upload Habits -->
            <li class="nav-item">
                <a class="nav-link" href="upload_habits.php">
                    <i class="fe fe-upload fe-16"></i>
                    <span class="ml-3 item-text">Upload Habits</span>
                </a>
            </li>
            <!-- Leaderboard -->
            <li class="nav-item">
                <a class="nav-link" href="leaderboard.php">
                    <i class="fe fe-bar-chart-2 fe-16"></i>
                    <span class="ml-3 item-text">Leaderboard</span>
                </a>
            </li>
            <!-- Habit History -->
            <li class="nav-item">
                <a class="nav-link" href="habit_history.php">
                    <i class="fe fe-clock fe-16"></i>
                    <span class="ml-3 item-text">Habit History</span>
                </a>
            </li>
            <!-- Notifications -->
            <li class="nav-item">
                <a class="nav-link" href="notifications.php">
                    <i class="fe fe-bell fe-16"></i>
                    <span class="ml-3 item-text">Notifications</span>
                </a>
            </li>
            <!-- Profile -->
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fe fe-user fe-16"></i>
                    <span class="ml-3 item-text">Profile</span>
                </a>
            </li>
            <!-- Add more navigation items if needed -->
        </ul>
    </nav>
</aside>
