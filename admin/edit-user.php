<?php
// admin/edit-user.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Get user ID from GET parameter
$user_id = $_GET['id'] ?? '';

if (empty($user_id)) {
    header("Location: user-management.php");
    exit();
}

require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Fetch user details
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, full_name, username, email, phone, standard, location AS center_name, course_name, role FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: user-management.php");
    exit();
}

$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = !empty($_POST['email']) ? trim($_POST['email']) : NULL;
    $phone = trim($_POST['phone'] ?? '');
    $standard = !empty($_POST['standard']) ? trim($_POST['standard']) : NULL;
    $center_name = !empty($_POST['center_name']) ? strtoupper(trim($_POST['center_name'])) : NULL; // Capitalizing input
    $course_name = !empty($_POST['course_name']) ? trim($_POST['course_name']) : NULL;
    $role = trim($_POST['role'] ?? 'parent');

    // Basic validation
    if (empty($full_name) || empty($username) || empty($phone) || empty($role)) {
        $error = "Please fill in all required fields.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if username is unique (excluding current user)
        $checkQuery = "SELECT id FROM users WHERE username = ? AND id != ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bind_param("si", $username, $user_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "This username is already taken.";
        } else {
            // Update user details
            if (!empty($password)) {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $update_query = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, standard = ?, location = ?, course_name = ?, role = ?, password = ? WHERE id = ?";
                $stmt = $db->prepare($update_query);
                $stmt->bind_param("sssssssssi", $full_name, $username, $email, $phone, $standard, $center_name, $course_name, $role, $hashed_password, $user_id);
            } else {
                $update_query = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, standard = ?, location = ?, course_name = ?, role = ? WHERE id = ?";
                $stmt = $db->prepare($update_query);
                $stmt->bind_param("ssssssssi", $full_name, $username, $email, $phone, $standard, $center_name, $course_name, $role, $user_id);
            }

            if ($stmt->execute()) {
                $success = "User updated successfully.";
                // Refresh user details
                $user['full_name'] = $full_name;
                $user['username'] = $username;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $user['standard'] = $standard;
                $user['center_name'] = $center_name;
                $user['course_name'] = $course_name;
                $user['role'] = $role;
            } else {
                $error = "An error occurred. Please try again.";
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Edit User - Habits365Club</title>
    <link rel="stylesheet" href="css/select2.min.css">
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger { color: #a94442; background-color: #f2dede; border-color: #ebccd1; }
        .alert-success { color: #3c763d; background-color: #dff0d8; border-color: #d6e9c6; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Edit User</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">User Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form action="edit-user.php?id=<?php echo $user_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="full_name">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password <small>(Leave blank to keep current password)</small></label>
                            <input type="password" id="password" name="password" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="email">Email (Optional)</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone <span class="text-danger">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="standard">Standard</label>
                            <input type="text" id="standard" name="standard" class="form-control" value="<?php echo htmlspecialchars($user['standard']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="center_name">Center Name</label>
                            <input type="text" id="center_name" name="center_name" class="form-control" value="<?php echo htmlspecialchars($user['center_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="course_name">Course Name</label>
                            <input type="text" id="course_name" name="course_name" class="form-control" value="<?php echo htmlspecialchars($user['course_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="role">Role <span class="text-danger">*</span></label>
                            <select id="role" name="role" class="form-control select2">
                                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="teacher" <?php echo ($user['role'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                <option value="parent" <?php echo ($user['role'] === 'parent') ? 'selected' : ''; ?>>Parent</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
