<?php
// incharge/includes/navbar.php
?>
<!-- Top Navbar -->
<nav class="topnav navbar navbar-light">
    <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar">
        <i class="fe fe-menu navbar-toggler-icon"></i>
    </button>
    
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link text-muted" href="messages.php">
                <span style="position: relative; display: inline-block;">
                    <i class="fe fe-mail"></i>
                    <?php
                    $incharge_id = $_SESSION['incharge_id'] ?? 0;
                    $unread_count = 0;

                    if ($incharge_id) {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM internal_message_recipients WHERE recipient_id = ? AND recipient_role = 'incharge' AND is_read = 0");
                        $stmt->bind_param("i", $incharge_id);
                        $stmt->execute();
                        $stmt->bind_result($unread_count);
                        $stmt->fetch();
                        $stmt->close();
                    }

                    if ($unread_count > 0): ?>
                        <span class="badge badge-danger" style="position: absolute; top: -8px; right: -10px; font-size: 10px;">
                            <?php echo $unread_count; ?>
                        </span>
                    <?php endif; ?>
                </span>
            </a>
        </li>
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
