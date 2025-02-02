<?php
// Start PHP session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Initialize error message
$error = '';

// Check if the parent is already logged in via cookie
if (isset($_COOKIE['parent_email'])) {
    echo "<p>✅ Cookie detected! Redirecting to dashboard...</p>";
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<p>🔍 Debug: Form submitted.</p>";

    // Retrieve and sanitize form inputs
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    echo "<p>🔍 Debug: Username - {$username}</p>";
    echo "<p>🔍 Debug: Password - {$password}</p>";

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "❌ Please fill in both username and password.";
    } else {
        // Instantiate the Database class and get the connection
        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            die("❌ Database connection failed: " . mysqli_connect_error());
        }

        echo "<p>✅ Debug: Database connected successfully.</p>";

        // Prepare a SQL statement to retrieve user by username
        $stmt = $db->prepare("SELECT id, email, password FROM users WHERE username = ? AND role = 'parent'");

        if ($stmt) {
            echo "<p>✅ Debug: SQL Statement Prepared.</p>";

            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            echo "<p>✅ Debug: SQL Executed. Found rows: " . $stmt->num_rows . "</p>";

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($parent_id, $parent_email, $hashed_password);
                $stmt->fetch();

                echo "<p>✅ Debug: Parent ID - {$parent_id}, Email - {$parent_email}</p>";

                // Verify the password
                if (password_verify($password, $hashed_password)) {
                    echo "<p>✅ Debug: Password verified successfully.</p>";

                    // Set a **default** cookie for authentication (valid for 30 days)
                    setcookie("parent_email", $parent_email, time() + (30 * 24 * 60 * 60), "/", "", false, true);

                    // Also store session variables
                    $_SESSION['parent_email'] = $parent_email;
                    $_SESSION['parent_id'] = $parent_id;

                    echo "<p>✅ Debug: Session set. Redirecting to dashboard...</p>";

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "❌ Invalid password.";
                    echo "<p>❌ Debug: Password mismatch.</p>";
                }
            } else {
                $error = "❌ Invalid username or user not found.";
                echo "<p>❌ Debug: No matching user found.</p>";
            }
            $stmt->close();
        } else {
            $error = "❌ SQL Error: Unable to prepare statement.";
            echo "<p>❌ Debug: SQL Error - " . $db->error . "</p>";
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
    <link rel="stylesheet" href="css/app-light.css">
</head>
<body class="light">
    <div class="wrapper vh-100">
        <div class="row align-items-center h-100">
            <form class="col-lg-3 col-md-4 col-10 mx-auto text-center" method="POST" action="index.php">
                <h1 class="h6 mb-3">Parent Sign in</h1>

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