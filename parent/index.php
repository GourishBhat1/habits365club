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

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch batches for select dropdown
$batches = [];
$batchQuery = "SELECT id, name FROM batches ORDER BY name ASC";
$batchStmt = $db->prepare($batchQuery);
if ($batchStmt) {
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    while ($row = $batchResult->fetch_assoc()) {
        $batches[] = $row;
    }
    $batchStmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize form inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $standard = trim($_POST['standard'] ?? '');
    $center_name = strtoupper(trim($_POST['center_name'] ?? '')); // Capitalized
    $course_name = trim($_POST['course_name'] ?? '');
    $batch_id = $_POST['batch_id'] ?? '';

    // Use phone number as both username and phone
    $username = $phone;

    // Basic validation
    if (empty($full_name) || empty($phone) || empty($batch_id)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if phone (username) exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $phone);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "This phone number is already registered.";
        } else {
            // Insert new user into the database with NULL password
            $insertStmt = $db->prepare("
                INSERT INTO users (username, full_name, phone, standard, location, course_name, role, batch_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'parent', ?, 'active', NOW())
            ");
            $insertStmt->bind_param("ssssssi", $username, $full_name, $phone, $standard, $center_name, $course_name, $batch_id);

            if ($insertStmt->execute()) {
                // Set authentication cookie for **10 years**
                setcookie("parent_username", $username, time() + (10 * 365 * 24 * 60 * 60), "/", "", false, true);

                // Store session variables
                $_SESSION['parent_username'] = $username;
                $_SESSION['parent_id'] = $insertStmt->insert_id;

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Error registering. Please try again.";
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Parent Registration - Habits365Club</title>
    <link rel="stylesheet" href="css/app-light.css">
    <link rel="stylesheet" href="css/select2.css">

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
            margin-top: 10px;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            opacity: 0.8;
        }
        .install-btn:hover {
            opacity: 1;
        }
    </style>
</head>
<body class="light">
    <div class="wrapper vh-100">
        <div class="row align-items-center h-100">
            <form class="col-lg-4 col-md-6 col-10 mx-auto text-center" method="POST">
                <h1 class="h6 mb-3">Parent Registration</h1>

                <!-- Display error messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control form-control-lg" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (Used as Username)</label>
                    <input type="tel" id="phone" name="phone" class="form-control form-control-lg" required>
                </div>
                <div class="form-group">
                    <label for="standard">Standard</label>
                    <input type="text" id="standard" name="standard" class="form-control form-control-lg">
                </div>
                <div class="form-group">
                    <label for="center_name">Center Name</label>
                    <input type="text" id="center_name" name="center_name" class="form-control form-control-lg">
                </div>
                <div class="form-group">
                    <label for="course_name">Course Name</label>
                    <input type="text" id="course_name" name="course_name" class="form-control form-control-lg">
                </div>
                <div class="form-group">
                    <label for="batch_id">Batch Name</label>
                    <select id="batch_id" name="batch_id" class="form-control select2" required>
                        <option value="">Select a Batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button class="btn btn-lg btn-primary btn-block" type="submit">Register</button>

                <!-- PWA Install Button -->
                <button id="installAppBtn" class="install-btn" onclick="installApp()" type="button">📲 Install App</button>
            </form>
        </div>
    </div>

<script src="js/jquery.min.js"></script>
<script src="js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        $('#batch_id').select2({
            width: '100%',  // Ensure full width
            placeholder: "Select a Batch",  // Placeholder text
            allowClear: true,  // Allow clearing selection
        });
    });
</script>
</body>
</html>
