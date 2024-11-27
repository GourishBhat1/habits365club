<?php
// teacher/index.php

// Start PHP session
session_start();

// Include the database connection file
require_once '../connection.php';

// Initialize variables for error and success messages
$error = '';
$success = '';

// Check if the teacher is already logged in via cookie
if (isset($_COOKIE['teacher_email'])) {
    // Redirect to dashboard if already logged in
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize form inputs
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember_me = isset($_POST['remember_me']) ? true : false;

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "Please fill in both username and password.";
    } else {
        // Instantiate the Database class and get the connection
        $database = new Database();
        $db = $database->getConnection();

        // Prepare a SQL statement to retrieve user by username
        $stmt = $db->prepare("SELECT id, email, password FROM users WHERE username = ? AND role = 'teacher'");
        if ($stmt) {
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
                    // Password is correct
                    if ($remember_me) {
                        // Set a secure cookie with the teacher's email, expires in 30 days
                        setcookie("teacher_email", $teacher_email, [
                            'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                            'path' => '/',
                            'domain' => '', // Set to your domain name if needed
                            'secure' => isset($_SERVER['HTTPS']), // True if using HTTPS
                            'httponly' => true, // Prevents JavaScript access
                            'samesite' => 'Strict' // Can be 'Lax' or 'Strict'
                        ]);
                    } else {
                        // Set a session variable
                        $_SESSION['teacher_email'] = $teacher_email;
                        $_SESSION['teacher_id'] = $teacher_id;
                    }

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Invalid password
                    $error = "Invalid username or password.";
                }
            } else {
                // User not found
                $error = "Invalid username or password.";
            }

            // Close the statement
            $stmt->close();
        } else {
            // SQL statement preparation failed
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Teacher Login - Habits365Club</title>
    <!-- Simple bar CSS -->
    <link rel="stylesheet" href="css/simplebar.css">
    <!-- Fonts CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Overpass:wght@400;600&display=swap" rel="stylesheet">
    <!-- Icons CSS -->
    <link rel="stylesheet" href="css/feather.css">
    <!-- Date Range Picker CSS -->
    <link rel="stylesheet" href="css/daterangepicker.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
    <!-- Custom CSS for error/success messages -->
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
<body class="light">
    <div class="wrapper vh-100">
        <div class="row align-items-center h-100">
            <form class="col-lg-3 col-md-4 col-10 mx-auto text-center" method="POST" action="index.php">
                <a class="navbar-brand mx-auto mt-2 flex-fill text-center" href="#">
                    <svg version="1.1" id="logo" class="navbar-brand-img brand-md" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120">
                        <g>
                            <polygon class="st0" points="78,105 15,105 24,87 87,87" />
                            <polygon class="st0" points="96,69 33,69 42,51 105,51" />
                            <polygon class="st0" points="78,33 15,33 24,15 87,15" />
                        </g>
                    </svg>
                </a>
                <h1 class="h6 mb-3">Teacher Sign in</h1>

                <!-- Display error or success messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="inputUsername" class="sr-only">Username</label>
                    <input type="text" id="inputUsername" name="username" class="form-control form-control-lg" placeholder="Username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="inputPassword" class="sr-only">Password</label>
                    <input type="password" id="inputPassword" name="password" class="form-control form-control-lg" placeholder="Password" required>
                </div>
                <div class="checkbox mb-3">
                    <label>
                        <input type="checkbox" name="remember_me" value="1"> Stay logged in
                    </label>
                </div>
                <button class="btn btn-lg btn-primary btn-block" type="submit">Let me in</button>
                <p class="mt-5 mb-3 text-muted">© 2024</p>
            </form>
        </div>
    </div>
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/moment.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/simplebar.min.js"></script>
    <script src='js/daterangepicker.js'></script>
    <script src='js/jquery.stickOnScroll.js'></script>
    <script src="js/tinycolor-min.js"></script>
    <script src="js/config.js"></script>
    <script src="js/apps.js"></script>
</body>
</html>
