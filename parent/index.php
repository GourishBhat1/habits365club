<?php
// Start PHP session
session_start();

// Enable error reporting for debugging (Comment out in production)
error_reporting(0);
ini_set('display_errors', 0);

// Include database connection
require_once '../connection.php';

// Initialize error message
$error = '';

// Check if the parent is already logged in via cookie
if (isset($_COOKIE['parent_username']) && !empty($_COOKIE['parent_username'])) {
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
            die("Database connection failed.");
        }

        // Prepare a SQL statement to retrieve user by username
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ? AND role = 'parent'");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($parent_id, $parent_username, $hashed_password);
                $stmt->fetch();

                // Verify the password
                if (password_verify($password, $hashed_password)) {
                    // Set authentication cookie for 30 days using username instead of email
                    setcookie("parent_username", $parent_username, time() + (30 * 24 * 60 * 60), "/", "", false, true);

                    // Store session variables
                    $_SESSION['parent_username'] = $parent_username;
                    $_SESSION['parent_id'] = $parent_id;

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or user not found.";
            }
            $stmt->close();
        } else {
            $error = "SQL Error: Unable to process login.";
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

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- PWA Installation Logic -->
    <script>
      let deferredPrompt;

      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js')
          .then(() => console.log('✅ Service Worker Registered'))
          .catch(() => console.log('❌ Service Worker Registration Failed'));
      }

      window.addEventListener('beforeinstallprompt', (event) => {
          event.preventDefault(); 
          deferredPrompt = event;
      });

      function installApp() {
          if (deferredPrompt) {
              deferredPrompt.prompt();
              deferredPrompt.userChoice.then((choiceResult) => {
                  if (choiceResult.outcome === 'accepted') {
                      console.log('✅ User installed the app');
                  } else {
                      console.log('❌ User dismissed the install prompt');
                  }
                  deferredPrompt = null;
              });
          } else {
              alert("To install the app manually:\n- On Android: Tap 'Add to Home Screen' in the browser menu.\n- On iOS: Tap 'Share' and select 'Add to Home Screen'.");
          }
      }
    </script>

    <style>
        .install-btn {
            margin-top: 15px;
            padding: 10px 15px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        .install-btn:hover {
            background-color: #218838;
        }
    </style>
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

                <!-- PWA Install Button (Always Visible) -->
                <button id="installAppBtn" class="install-btn" onclick="installApp()" type="button">📲 Install App</button>
            </form>
        </div>
    </div>
</body>
</html>

