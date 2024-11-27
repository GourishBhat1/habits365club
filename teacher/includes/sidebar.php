<?php
// teacher/includes/sidebar.php
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
            <!-- Assigned Students -->
            <li class="nav-item">
                <a class="nav-link" href="assigned-students.php">
                    <i class="fe fe-users fe-16"></i>
                    <span class="ml-3 item-text">Assigned Students</span>
                </a>
            </li>
            <!-- Online Classes -->
            <li class="nav-item">
                <a class="nav-link" href="#onlineClassesSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fe fe-video fe-16"></i>
                    <span class="ml-3 item-text">Online Classes</span>
                </a>
                <ul class="collapse list-unstyled" id="onlineClassesSubmenu">
                    <li>
                        <a class="nav-link pl-4" href="meets.php">
                            <i class="fe fe-list fe-12"></i>
                            <span class="ml-1 item-text">Manage Meet Links</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link pl-4" href="add-meet.php">
                            <i class="fe fe-plus-circle fe-12"></i>
                            <span class="ml-1 item-text">Add Meet Link</span>
                        </a>
                    </li>
                </ul>
            </li>
            <!-- Habit Assessments -->
            <li class="nav-item">
                <a class="nav-link" href="#habitAssessmentsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
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
            </li>
            <!-- Add more navigation items as needed -->
        </ul>
    </nav>
</aside>
