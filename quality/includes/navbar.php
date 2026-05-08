<?php
// quality/includes/navbar.php
?>
<!-- Top Navbar -->
<nav class="topnav navbar navbar-light">
    <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar">
        <i class="fe fe-menu navbar-toggler-icon"></i>
    </button>
    
    <ul class="nav">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-muted pr-0" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown">
                <span class="avatar avatar-sm mt-2">
                    <?php
                    $quality_username = $_SESSION['quality_username'] ?? $_COOKIE['quality_username'] ?? '';
                    $profile_pic = 'assets/images/user.png';

                    if (!empty($quality_username)) {
                        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE username = ? AND role = 'quality'");
                        $stmt->bind_param("s", $quality_username);
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
                <a class="dropdown-item" href="profile.php">Profile</a>
                <a class="dropdown-item" href="logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>
