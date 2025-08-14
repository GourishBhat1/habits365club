<?php
// parent/login.php

// Start PHP session
session_start();

// Include database connection
require_once '../connection.php';

// Initialize variables
$error = "";

// Check if the parent is already logged in via cookie
if (isset($_COOKIE['parent_username']) && !empty($_COOKIE['parent_username'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize input
    $username = trim($_POST['username'] ?? '');

    // Basic validation
    if (empty($username)) {
        $error = "❌ Please enter your username.";
    } else {
        // Instantiate the Database class and get the connection
        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            die("❌ Database connection failed.");
        }

        // Check if username exists in the database
        $stmt = $db->prepare("SELECT id, username, status, approved FROM users WHERE username = ? AND role = 'parent'");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($parent_id, $parent_username, $status, $approved);
                $stmt->fetch();

                if ($status === 'active' && $approved == 1) {
                    // Set authentication cookie for **10 years**
                    setcookie("parent_username", $parent_username, time() + (10 * 365 * 24 * 60 * 60), "/", "", false, true);

                    // Store session variables
                    $_SESSION['parent_username'] = $parent_username;
                    $_SESSION['parent_id'] = $parent_id;

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } elseif ($status === 'inactive' && $approved == 0) {
                    $error = "Your registration is pending approval. Please wait for admin/incharge to approve.";
                } elseif ($status === 'rejected') {
                    $error = "Your registration has been rejected. Please contact support.";
                } else {
                    $error = "Your account is not active. Please contact support.";
                }
            } else {
                $error = "❌ Username not found. Please register first.";
            }
            $stmt->close();
        } else {
            $error = "❌ SQL Error: Unable to process login.";
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Parent Login - Habits365Club</title>
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    
    <style>
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-container img {
            max-width: 180px;
            height: auto;
            border-radius: 8px;
        }
    </style>
</head>
<body class="light">
    <div class="wrapper vh-100">
        <div class="row align-items-center h-100">
            <div class="col-lg-3 col-md-4 col-10 mx-auto text-center">
                <div class="logo-container">
                    <img src="../assets/images/habits_logo.png" alt="Habits 365 Club">
                </div>
                <form method="POST">
                    <h1 class="h6 mb-3">Parent Sign in</h1>

                    <!-- Display error messages -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="inputUsername">Mobile Number</label>
                        <input type="text" id="inputUsername" name="username" class="form-control form-control-lg" required autofocus>
                    </div>

                    <button class="btn btn-lg btn-primary btn-block" type="submit">Let me in</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>