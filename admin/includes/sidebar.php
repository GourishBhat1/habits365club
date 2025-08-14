<?php
// admin/includes/sidebar.php
?>
<!-- Sidebar -->
<aside class="sidebar-left border-right bg-white shadow" id="leftSidebar" data-simplebar>
    <nav class="vertnav navbar navbar-light">
        <!-- Brand Logo -->
        <a class="navbar-brand" href="dashboard.php">
            <span class="avatar avatar-xl">
                <img src="../assets/images/habits_logo.png" alt="Logo" class="avatar-img rounded-circle">
            </span>
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
                <a class="nav-link" href="#userManagementSubmenu" data-toggle="collapse" class="dropdown-toggle">
                    <i class="fe fe-users fe-16"></i>
                    <span class="ml-3 item-text">Manage Users</span>
                </a>
                <ul class="collapse list-unstyled" id="userManagementSubmenu">
                    <li>
                        <a class="nav-link pl-4" href="user-management.php">
                            <i class="fe fe-user fe-12"></i>
                            <span class="ml-1 item-text">All Users</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link pl-4" href="bulk-upload-parents.php">
                            <i class="fe fe-upload-cloud fe-12"></i>
                            <span class="ml-1 item-text">Bulk Parent Upload</span>
                        </a>
                    </li>
                </ul>
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

            <li class="nav-item">
                <a class="nav-link" href="manage-centers.php">
                    <i class="fe fe-map fe-16"></i>
                    <span class="ml-3 item-text">Manage Centers</span>
                </a>
            </li>

            <!-- Notices -->
<li class="nav-item">
    <a class="nav-link" href="notices.php">
        <i class="fe fe-bell fe-16"></i>
        <span class="ml-3 item-text">View Notices</span>
    </a>
</li>

            <!-- Certificates & Rewards -->
            <li class="nav-item">
                <a class="nav-link" href="#certificatesRewardsSubmenu" data-toggle="collapse" class="dropdown-toggle">
                    <i class="fe fe-award fe-16"></i>
                    <span class="ml-3 item-text">Certificates & Rewards</span>
                </a>
                <ul class="collapse list-unstyled" id="certificatesRewardsSubmenu">
                    <li>
                        <a class="nav-link pl-4" href="certificate-management.php">
                            <i class="fe fe-file-text fe-12"></i>
                            <span class="ml-1 item-text">Manage Certificates</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Upload & Evidence Management -->
            <li class="nav-item">
                <a class="nav-link" href="upload-management.php">
                    <i class="fe fe-upload fe-16"></i>
                    <span class="ml-3 item-text">Manage Uploads</span>
                </a>
            </li>

            <!-- Leaderboard Management -->
            <li class="nav-item">
                <a class="nav-link" href="leaderboard-management.php">
                    <i class="fe fe-bar-chart-2 fe-16"></i>
                    <span class="ml-3 item-text">Monthly Score Masterboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="weekly_masterboard.php">
                    <i class="fe fe-bar-chart-2 fe-16"></i>
                    <span class="ml-3 item-text">Weekly Score Masterboard</span>
                </a>
            </li>

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link" href="report.php">
                    <i class="fe fe-pie-chart fe-16"></i>
                    <span class="ml-3 item-text">Reports</span>
                </a>
            </li>

            <!-- Evidence Cleanup (CRON Job) -->
            <li class="nav-item">
                <a class="nav-link" href="evidence-cleanup.php">
                    <i class="fe fe-trash-2 fe-16"></i>
                    <span class="ml-3 item-text">Evidence Cleanup</span>
                </a>
            </li>

            <!-- Internal Messages -->
            <li class="nav-item">
                <a class="nav-link" href="messages.php">
                    <i class="fe fe-message-circle fe-16"></i>
                    <span class="ml-3 item-text">Internal Messages</span>
                </a>
            </li>

            <!-- Readmissions Management -->
            <li class="nav-item">
                <a class="nav-link" href="readmission.php">
                    <i class="fe fe-refresh-cw fe-16"></i>
                    <span class="ml-3 item-text">Readmissions</span>
                </a>
            </li>

            <!-- Approve Parents -->
            <li class="nav-item">
                <a class="nav-link" href="approve_parents.php">
                    <i class="fe fe-user-check fe-16"></i>
                    <span class="ml-3 item-text">Approve Parents</span>
                </a>
            </li>

            <!-- Logout -->
            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fe fe-log-out fe-16"></i>
                    <span class="ml-3 item-text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
