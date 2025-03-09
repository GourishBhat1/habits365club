<?php
// teacher/includes/sidebar.php
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
            <!-- Assigned Students -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="assigned-students.php">
                    <i class="fe fe-users fe-16"></i>
                    <span class="ml-3 item-text">Assigned Students</span>
                </a>
            </li> -->
            <!-- View Students in Batches -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="view_students.php">
                    <i class="fe fe-user-check fe-16"></i>
                    <span class="ml-3 item-text">View Students</span>
                </a>
            </li> -->
            <!-- Habit Assessments -->
            <!-- <li class="nav-item">
                <a class="nav-link collapsed" href="#habitAssessmentsSubmenu" data-toggle="collapse">
                    <i class="fe fe-check-square fe-16"></i>
                    <span class="ml-3 item-text">Habit Assessments</span>
                </a>
                <ul class="collapse list-unstyled" id="habitAssessmentsSubmenu">
                    <li>
                        <a class="nav-link pl-4" href="assessments.php">
                            <i class="fe fe-list fe-12"></i>
                            <span class="ml-1 item-text">Manage Assessments</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link pl-4" href="assess-habit.php">
                            <i class="fe fe-plus-circle fe-12"></i>
                            <span class="ml-1 item-text">Add Assessment</span>
                        </a>
                    </li>
                </ul>
            </li> -->
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
                        <a class="nav-link pl-4" href="batch_leaderboard.php">
                            <i class="fe fe-bar-chart-2 fe-12"></i>
                            <span class="ml-1 item-text">Batch Masterboard</span>
                        </a>
                    </li>
                    <!-- <li>
                        <a class="nav-link pl-4" href="habit_details.php">
                            <i class="fe fe-info fe-12"></i>
                            <span class="ml-1 item-text">Habit Details</span>
                        </a>
                    </li> -->
                </ul>
            </li>
            <!-- Batch Habits -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="batch_habits.php">
                    <i class="fe fe-list fe-16"></i>
                    <span class="ml-3 item-text">Batch Habits</span>
                </a>
            </li> -->
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
