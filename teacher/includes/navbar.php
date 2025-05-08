<?php
?>
<!-- Top Navbar -->
<nav class="topnav navbar navbar-light">
    <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar">
        <i class="fe fe-menu navbar-toggler-icon"></i>
    </button>
    <ul class="nav">
        <?php
        // Count unread messages
        $unread_count = 0;
        if (isset($_SESSION['teacher_id'])) {
            $teacher_id = $_SESSION['teacher_id'];
            $stmt = $db->prepare("SELECT COUNT(*) FROM internal_message_recipients WHERE recipient_id = ? AND recipient_role = 'teacher' AND is_read = 0");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $stmt->bind_result($unread_count);
            $stmt->fetch();
            $stmt->close();
        }
        ?>
        <li class="nav-item">
            <a class="nav-link text-muted" href="messages.php">
                <span style="position: relative; display: inline-block;">
                    <i class="fe fe-mail fe-24"></i>
                    <?php if ($unread_count > 0): ?>
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
