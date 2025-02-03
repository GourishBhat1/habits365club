<?php
// admin/index.php

// Start PHP session
session_start();

// Include the database connection file
require_once '../connection.php';

// Initialize error message
$error = '';

// Check if the admin is already logged in via cookie
if (isset($_COOKIE['admin_email'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize form inputs
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Instantiate the Database class and get the connection
        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            die("❌ Database connection failed!");
        }

        // Prepare a SQL statement to retrieve user by username
        $stmt = $db->prepare("SELECT id, email, password FROM users WHERE username = ? AND role = 'admin'");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($admin_id, $admin_email, $hashed_password);
                $stmt->fetch();

                // Verify the password
                if (password_verify($password, $hashed_password)) {
                    // Set authentication cookie for 30 days
                    setcookie("admin_email", $admin_email, time() + (30 * 24 * 60 * 60), "/", "", false, true);

                    // Store session variables
                    $_SESSION['admin_email'] = $admin_email;
                    $_SESSION['admin_id'] = $admin_id;

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "❌ Invalid username or password.";
                }
            } else {
                $error = "❌ Invalid username or password.";
            }
            $stmt->close();
        } else {
            $error = "❌ SQL Error: Unable to prepare statement.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Login - Habits365Club</title>
    <link rel="stylesheet" href="css/app-light.css">
</head>
<body class="light">
    <div class="wrapper vh-100">
        <div class="row align-items-center h-100">
            <form class="col-lg-3 col-md-4 col-10 mx-auto text-center" method="POST" action="index.php">
                <h1 class="h6 mb-3">Admin Sign in</h1>

                <!-- Display error messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="inputUsername">Username</label>
                    <input type="text" id="inputUsername" name="username" class="form-control form-control-lg" required autofocus>
                </div>
                <div class="form-group">
                    <label for="inputPassword">Password</label>
                    <input type="password" id="inputPassword" name="password" class="form-control form-control-lg" required>
                </div>
                
                <button class="btn btn-lg btn-primary btn-block" type="submit">Let me in</button>
            </form>
        </div>
    </div>
</body>
</html>
