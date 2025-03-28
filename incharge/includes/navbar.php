<?php
// incharge/includes/navbar.php
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
                    // Fetch incharge profile picture

                    $incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'] ?? '';
                    $profile_pic = 'assets/images/user.png'; // default

                    if (!empty($incharge_username)) {
                        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE username = ? AND role = 'incharge'");
                        $stmt->bind_param("s", $incharge_username);
                        $stmt->execute();
                        $stmt->bind_result($pic);
                        if ($stmt->fetch() && !empty($pic)) {
                            $profile_pic = $pic;
                        }
                        $stmt->close();
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="..." class="avatar-img rounded-circle">
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
