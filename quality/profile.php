

<?php
session_start();
require_once '../connection.php';

// AUTH
if (!isset($_SESSION['quality_username']) && !isset($_COOKIE['quality_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Resolve current user
$quality_username = $_SESSION['quality_username'] ?? $_COOKIE['quality_username'] ?? '';

$stmt = $db->prepare("
    SELECT id, username, full_name, phone, email, location, created_at 
    FROM users 
    WHERE username = ? AND role = 'quality'
    LIMIT 1
");
$stmt->bind_param("s", $quality_username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}

$successMsg = "";
$errorMsg = "";

/* -----------------------------
   HANDLE PASSWORD UPDATE
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errorMsg = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $errorMsg = "New password and confirm password do not match.";
    } elseif (strlen($new_password) < 6) {
        $errorMsg = "Password must be at least 6 characters.";
    } else {

        // Fetch hashed password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            $errorMsg = "Current password is incorrect.";
        } else {

            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_hashed, $user['id']);

            if ($stmt->execute()) {
                $successMsg = "Password updated successfully.";
            } else {
                $errorMsg = "Failed to update password.";
            }
            $stmt->close();
        }
    }
}
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Profile</title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">My Profile</h2>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="row">

<!-- PROFILE INFO -->
<div class="col-md-6">
<div class="card shadow mb-4">
<div class="card-body">

<h5 class="mb-3">Profile Details</h5>

<p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
<p><strong>Full Name:</strong> <?= htmlspecialchars($user['full_name']) ?></p>
<p><strong>Phone:</strong> <?= htmlspecialchars($user['phone']) ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
<p><strong>Location(s):</strong>
<?php
$loc_list = array_map('trim', explode(',', $user['location'] ?? ''));
$loc_list = array_filter($loc_list);
if (!empty($loc_list)):
    echo '<span class="badge badge-info mr-1">' . implode('</span><span class="badge badge-info mr-1">', array_map('htmlspecialchars', $loc_list)) . '</span>';
else:
    echo '<span class="text-muted">N/A</span>';
endif;
?></p>
<p><strong>Joined On:</strong> <?= htmlspecialchars($user['created_at']) ?></p>

</div>
</div>
</div>

<!-- CHANGE PASSWORD -->
<div class="col-md-6">
<div class="card shadow mb-4">
<div class="card-body">

<h5 class="mb-3">Change Password</h5>

<form method="POST">

<input type="hidden" name="update_password" value="1">

<div class="form-group">
<label>Current Password</label>
<input type="password" name="current_password" class="form-control" required>
</div>

<div class="form-group">
<label>New Password</label>
<input type="password" name="new_password" class="form-control" required>
</div>

<div class="form-group">
<label>Confirm Password</label>
<input type="password" name="confirm_password" class="form-control" required>
</div>

<button class="btn btn-primary">Update Password</button>

</form>

</div>
</div>
</div>

</div>

</div>
</main>

</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>