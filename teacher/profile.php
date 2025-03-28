<?php
// teacher/profile.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Fetch teacher ID from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher'");
    if ($stmt) {
        $stmt->bind_param("s", $teacher_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($teacher_id);
            $stmt->fetch();
            $_SESSION['teacher_id'] = $teacher_id;
        } else {
            header("Location: index.php?message=invalid_cookie");
            exit();
        }
        $stmt->close();
    }
}

if (!$teacher_id) {
    die("❌ Invalid session. Please log in again.");
}

// Fetch teacher details
$teacher = null;
$stmt = $db->prepare("SELECT id, username, email, profile_picture FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($email)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Update database
        if (!empty($password)) {
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $updateQuery = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
            $stmt = $db->prepare($updateQuery);
            $stmt->bind_param("sssi", $username, $email, $hashed_password, $teacher_id);
        } else {
            $updateQuery = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            $stmt = $db->prepare($updateQuery);
            $stmt->bind_param("ssi", $username, $email, $teacher_id);
        }

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profile_pictures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
            $fileName = basename($_FILES['profile_picture']['name']);
            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = 'teacher_' . $teacher_id . '_' . time() . '.' . $fileExt;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Update profile picture in DB
                $updatePicQuery = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $picStmt = $db->prepare($updatePicQuery);
                if ($picStmt) {
                    $relativePath = 'uploads/profile_pictures/' . $newFileName;
                    $picStmt->bind_param("si", $relativePath, $teacher_id);
                    $picStmt->execute();
                    $picStmt->close();

                    // Update local variable
                    $teacher['profile_picture'] = $relativePath;
                }
            }
        }

        if ($stmt->execute()) {
            $success = "✅ Profile updated successfully.";
            // Refresh teacher details
            $teacher['username'] = $username;
            $teacher['email'] = $email;
        } else {
            $error = "❌ An error occurred. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Teacher Profile - Habits365Club</title>
    <link rel="stylesheet" href="css/select2.min.css">
    <style>
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-danger { color: #a94442; background-color: #f2dede; border-color: #ebccd1; }
        .alert-success { color: #3c763d; background-color: #dff0d8; border-color: #d6e9c6; }
        .profile-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #fff; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Teacher Profile</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card profile-card">
                <div class="card-header">
                    <h5 class="card-title">Update Profile</h5>
                </div>
                <div class="card-body">
                    <form action="profile.php" method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="username">Name <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($teacher['username']); ?>" required readonly>
                            <div class="invalid-feedback">Please enter your name.</div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                            <div class="invalid-feedback">Please enter a valid email.</div>
                        </div>
                        <div class="form-group">
                            <label for="password">New Password (Optional)</label>
                            <input type="password" id="password" name="password" class="form-control">
                            <small class="text-muted">Leave blank if you don't want to change your password.</small>
                        </div>
                        <div class="form-group">
                            <label for="profile_picture">Profile Picture</label>
                            <input type="file" name="profile_picture" id="profile_picture" class="form-control-file">
                            <?php if (!empty($teacher['profile_picture'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($teacher['profile_picture']); ?>" alt="Current Picture" width="80" height="80" class="rounded-circle border">
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
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
        $('.select2').select2({ theme: 'bootstrap4' });

        // Bootstrap form validation
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
    });
</script>
</body>
</html>
