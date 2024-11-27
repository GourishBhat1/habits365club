<?php
// admin/add-user.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../connection.php';

    // Retrieve and sanitize form inputs
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!in_array($role, ['parent', 'teacher', 'admin'])) {
        $error = "Invalid role selected.";
    } else {
        // Hash the password securely
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Instantiate the Database class and get the connection
        $database = new Database();
        $db = $database->getConnection();

        // Prepare the INSERT statement with correct parameter types
        $query = "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($query);

        if ($stmt) {
            // Corrected bind_param: all parameters are strings
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $success = "User added successfully.";
                // Optionally, clear the form fields
                $username = $email = $password = $role = '';
            } else {
                // Handle duplicate entries or other SQL errors
                if ($db->errno === 1062) { // Duplicate entry
                    $error = "Username or email already exists.";
                } else {
                    $error = "An error occurred. Please try again.";
                }
                // Log the error for debugging purposes
                error_log("Database Error: " . $db->error);
            }

            $stmt->close();
        } else {
            $error = "Database error: Unable to prepare statement.";
            // Log the error for debugging purposes
            error_log("Prepare Statement Failed: " . $db->error);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Add New User - Habits Web App</title>
    <!-- DataTables CSS (if needed) -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <!-- Select2 CSS for enhanced select boxes -->
    <link rel="stylesheet" href="css/select2.min.css">
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Add New User</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">User Details</h5>
                </div>
                <div class="card-body">
                    <!-- Display Error Message -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Display Success Message -->
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <!-- User Creation Form -->
                    <form action="add-user.php" method="POST" class="needs-validation" novalidate>
                        <!-- Username Field -->
                        <div class="form-group">
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control"
                                value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                                required>
                            <div class="invalid-feedback">
                                Please enter a username.
                            </div>
                        </div>

                        <!-- Email Field -->
                        <div class="form-group">
                            <label for="email">Email address <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                                required>
                            <div class="invalid-feedback">
                                Please enter a valid email.
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="form-group">
                            <label for="password">Password <span class="text-danger">*</span></label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                required>
                            <div class="invalid-feedback">
                                Please enter a password.
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div class="form-group">
                            <label for="role">Role <span class="text-danger">*</span></label>
                            <select
                                id="role"
                                name="role"
                                class="form-control select2"
                                required>
                                <option value="">Select a role</option>
                                <option value="parent" <?php echo (isset($role) && $role === 'parent') ? 'selected' : ''; ?>>Parent</option>
                                <option value="teacher" <?php echo (isset($role) && $role === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a role.
                            </div>
                        </div>

                        <!-- Form Buttons -->
                        <button type="submit" class="btn btn-primary">Add User</button>
                        <a href="user-management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        // Initialize Select2 for enhanced select boxes
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select a role"
        });

        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                // Fetch all forms with the class 'needs-validation'
                var forms = document.getElementsByClassName('needs-validation');
                // Loop over them and prevent submission if invalid
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
