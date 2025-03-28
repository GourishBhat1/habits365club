<?php
?>
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
                <span class="avatar avatar-sm mt-2">
                    <?php
                    // Fetch teacher profile picture
                    $profile_pic = 'assets/images/user.png'; // Default picture
                    if (isset($_SESSION['teacher_id'])) {
                        $teacher_id = $_SESSION['teacher_id'];
                        $stmt = $db->prepare("SELECT profile_picture FROM users WHERE id = ? AND role = 'teacher'");
                        $stmt->bind_param("i", $teacher_id);
                        $stmt->execute();
                        $stmt->bind_result($profile_picture);
                        if ($stmt->fetch() && !empty($profile_picture)) {
                            $profile_pic = $profile_picture;
                        }
                        $stmt->close();
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="avatar-img rounded-circle">
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownMenuLink">
                <a class="dropdown-item" href="#">Profile</a>
                <a class="dropdown-item" href="#">Settings</a>
                <a class="dropdown-item" href="logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>
