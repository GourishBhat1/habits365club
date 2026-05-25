<?php
session_start();

if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$error = '';
$success = '';

$database = new Database();
$db = $database->getConnection();

// Fetch enabled centers for quality role checkboxes
$centers_list = [];
$cstmt = $db->prepare("SELECT location FROM centers WHERE status = 'enabled' ORDER BY location ASC");
$cstmt->execute();
$cres = $cstmt->get_result();
while ($crow = $cres->fetch_assoc()) {
    $centers_list[] = $crow['location'];
}
$cstmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = !empty($_POST['email']) ? trim($_POST['email']) : NULL;
    $phone = trim($_POST['phone'] ?? '');
    $standard = !empty($_POST['standard']) ? trim($_POST['standard']) : NULL;
    $course_name = !empty($_POST['course_name']) ? trim($_POST['course_name']) : NULL;
    $role = trim($_POST['role'] ?? '');

    // Handle center_name: single text for non-quality, comma-separated checkboxes for quality
    if ($role === 'quality') {
        $selected = $_POST['centers'] ?? [];
        $center_name = !empty($selected) ? implode(',', array_map('strtoupper', $selected)) : NULL;
    } else {
        $center_name = !empty($_POST['center_name']) ? strtoupper(trim($_POST['center_name'])) : NULL;
    }

    if (empty($full_name) || empty($username) || empty($password) || empty($phone) || empty($role)) {
        $error = "Please fill in all required fields.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!in_array($role, ['parent', 'teacher', 'admin', 'incharge', 'sales', 'quality'])) {
        $error = "Invalid role selected.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $checkQuery = "SELECT id FROM users WHERE username = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "This username is already taken.";
        } else {
            $query = "INSERT INTO users (full_name, username, password, email, phone, standard, location, course_name, role, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            if ($stmt) {
                $stmt->bind_param("sssssssss", $full_name, $username, $hashed_password, $email, $phone, $standard, $center_name, $course_name, $role);
                if ($stmt->execute()) {
                    $success = "User added successfully.";
                    $_POST = [];
                } else {
                    $error = "Error adding user. Please try again.";
                }
                $stmt->close();
            } else {
                $error = "Database error: Unable to prepare statement.";
            }
        }
        $checkStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Add New User - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/select2.min.css">
    <style>
        .alert { padding:15px; margin-bottom:20px; border:1px solid transparent; border-radius:4px; }
        .alert-danger { color:#a94442; background-color:#f2dede; border-color:#ebccd1; }
        .alert-success { color:#3c763d; background-color:#dff0d8; border-color:#d6e9c6; }
        .center-checkbox-group { max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px; border-radius:4px; }
        .center-checkbox-group label { display:block; font-weight:normal; margin-bottom:4px; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Add New User</h2>
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

                    <form action="add-user.php" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="full_name">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password <span class="text-danger">*</span></label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email (Optional)</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone <span class="text-danger">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="standard">Standard</label>
                            <input type="text" id="standard" name="standard" class="form-control">
                        </div>

                        <!-- Single center text input (non-quality roles) -->
                        <div class="form-group" id="single_center_wrapper">
                            <label for="center_name">Center Name</label>
                            <input type="text" id="center_name" name="center_name" class="form-control">
                        </div>

                        <!-- Multi-center checkboxes (quality role) -->
                        <div class="form-group" id="multi_center_wrapper" style="display:none;">
                            <label>Centers (select one or more)</label>
                            <div class="center-checkbox-group">
                                <?php foreach ($centers_list as $c): ?>
                                <label>
                                    <input type="checkbox" name="centers[]" value="<?= htmlspecialchars($c) ?>">
                                    <?= htmlspecialchars($c) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="course_name">Course Name</label>
                            <input type="text" id="course_name" name="course_name" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="role">Role <span class="text-danger">*</span></label>
                            <select id="role" name="role" class="form-control select2" required onchange="toggleCenterField(this.value)">
                                <option value="">Select a role</option>
                                <option value="parent">Parent</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                                <option value="incharge">Incharge</option>
                                <option value="sales">Sales</option>
                                <option value="quality">Quality</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Add User</button>
                        <a href="user-management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="js/select2.min.js"></script>
<script>
$(document).ready(function () {
    $('.select2').select2({ theme: 'bootstrap4', placeholder: "Select a role" });
});

function toggleCenterField(role) {
    var single = document.getElementById('single_center_wrapper');
    var multi = document.getElementById('multi_center_wrapper');
    if (role === 'quality') {
        single.style.display = 'none';
        multi.style.display = 'block';
    } else {
        single.style.display = 'block';
        multi.style.display = 'none';
    }
}

(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>
</body>
</html>
