<?php
// teacher/index.php

// Start PHP session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection file
require_once '../connection.php';

// Initialize variables for error messages
$error = '';

// Check if the teacher is already logged in via cookie
if (isset($_COOKIE['teacher_email'])) {
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
        $error = "Please fill in both username and password.";
    } else {
        // Instantiate the Database class and get the connection
        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            die("❌ Database connection failed: " . mysqli_connect_error());
        }

        // Prepare a SQL statement to retrieve user by username
        $stmt = $db->prepare("SELECT id, email, password FROM users WHERE username = ? AND role = 'teacher'");
        if (!$stmt) {
            die("❌ SQL Error: " . $db->error);
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        // Check if user exists
        if ($stmt->num_rows == 1) {
            // Bind the result variables
            $stmt->bind_result($teacher_id, $teacher_email, $hashed_password);
            $stmt->fetch();

            // Verify the password
            if (password_verify($password, $hashed_password)) {
                // Set a **default** cookie for authentication (valid for 30 days)
                setcookie("teacher_email", $teacher_email, time() + (30 * 24 * 60 * 60), "/", "", false, true);

                // Store session variables
                $_SESSION['teacher_email'] = $teacher_email;
                $_SESSION['teacher_id'] = $teacher_id;

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "❌ Invalid username or password.";
            }
        } else {
            $error = "❌ Invalid username or password.";
        }

        // Close the statement
        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Teacher Login - Habits365Club</title>
    <!-- Including CSS files -->
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- PWA Installation Prompt -->
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js')
          .then(reg => console.log('✅ Service Worker Registered', reg))
          .catch(err => console.log('❌ Service Worker Registration Failed', err));
      }
    </script>
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
    </style>
</head>
<body class="light">
    <div class="wrapper vh-100">
        <div class="row align-items-center h-100">
            <div class="col-lg-3 col-md-4 col-10 mx-auto text-center">
                <div class="logo-container">
                    <img src="../assets/images/habits_logo.png" alt="Habits 365 Club">
                </div>
                <form method="POST" action="index.php">
                    <h1 class="h6 mb-3">Teacher Sign in</h1>

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
    </div>
</body>
</html>
