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

            <!-- Batch Management -->
            <li class="nav-item">
                <a class="nav-link" href="manage_batches.php">
                    <i class="fe fe-folder fe-16"></i>
                    <span class="ml-3 item-text">Manage Batches</span>
                </a>
            </li>

            <!-- Manual Score Entry -->
<li class="nav-item">
    <a class="nav-link" href="manual_score.php">
        <i class="fe fe-edit fe-16"></i>
        <span class="ml-3 item-text">Manual Score Entry</span>
    </a>
</li>

            <!-- Habit Evidence -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#habitEvidenceSubmenu" data-toggle="collapse">
                    <i class="fe fe-clipboard fe-16"></i>
                    <span class="ml-3 item-text">Habit Evidence</span>
                </a>
                <ul class="collapse list-unstyled" id="habitEvidenceSubmenu">
                    <li>
                        <a class="nav-link pl-4" href="review_habit_evidence.php">
                            <i class="fe fe-eye fe-12"></i>
                            <span class="ml-1 item-text">Review Evidence</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link pl-4" href="total_score_leaderboard.php">
                            <i class="fe fe-bar-chart-2 fe-12"></i>
                            <span class="ml-1 item-text">Monthly Score Masterboard</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link pl-4" href="batch_leaderboard.php">
                            <i class="fe fe-bar-chart-2 fe-12"></i>
                            <span class="ml-1 item-text">Weekly Score Masterboard</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Gallery Management -->
<li class="nav-item">
    <a class="nav-link" href="manage_gallery.php">
        <i class="fe fe-image fe-16"></i>
        <span class="ml-3 item-text">Manage Gallery</span>
    </a>
</li>

<li class="nav-item">
    <a class="nav-link" href="manage_parents.php">
        <i class="fe fe-users fe-16"></i>
        <span class="ml-3 item-text">Manage Parents</span>
    </a>
</li>
            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link" href="report.php">
                    <i class="fe fe-pie-chart fe-16"></i>
                    <span class="ml-3 item-text">Reports</span>
                </a>
            </li>

            <!-- 📌 Notices -->
            <li class="nav-item">
                <a class="nav-link" href="notices.php">
                    <i class="fe fe-bell fe-16"></i>
                    <span class="ml-3 item-text">Notices</span>
                </a>
            </li>

            <!-- Profile & Logout -->
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fe fe-user fe-16"></i>
                    <span class="ml-3 item-text">Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fe fe-log-out fe-16"></i>
                    <span class="ml-3 item-text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>