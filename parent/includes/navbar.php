<?php
// parent/includes/navbar.php

// Default profile picture
$user_profile_pic = "assets/images/user.png";

// Check if parent is logged in via session or cookie
if (isset($_SESSION['parent_username']) || isset($_COOKIE['parent_username'])) {
    $parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];

    // Establish database connection
    $database = new Database();
    $db = $database->getConnection();

    // Fetch profile picture
    $stmt = $db->prepare("SELECT COALESCE(profile_picture, 'assets/images/user.png') AS profile_picture FROM users WHERE username = ? AND role = 'parent'");
    $stmt->bind_param("s", $parent_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && !empty($user['profile_picture'])) {
        $user_profile_pic = $user['profile_picture'];
    }
}
?>
<!-- Top Navbar -->
<nav class="topnav navbar navbar-light">
    <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar">
        Menu<i class="fe fe-menu navbar-toggler-icon"></i>
    </button>

    <ul class="nav">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-muted pr-0" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown">
                <span class="avatar avatar-md mt-2">
                    <img src="<?php echo htmlspecialchars($user_profile_pic); ?>" alt="Profile" class="avatar-img rounded-circle">
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownMenuLink">
                <a class="dropdown-item" href="profile.php">Profile</a>
                <a class="dropdown-item" href="logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>
