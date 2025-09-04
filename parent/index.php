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
    // reCAPTCHA backend check
    $recaptcha_secret = '6Lc9vbwrAAAAABf0gf2_Hlx32sL5kclS_3kYC_pn';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptcha_response)) {
        $error = "Please complete the reCAPTCHA.";
    } else {
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
        $captcha_success = json_decode($verify);

        if (!$captcha_success->success) {
            $error = "reCAPTCHA verification failed. Please try again.";
        }
    }

    // Only continue registration if no error
    if (empty($error)) {
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
                // Insert new user into the database with approval logic
                $insertStmt = $db->prepare("
                    INSERT INTO users (username, full_name, phone, standard, location, course_name, role, batch_id, status, approved, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'parent', ?, 'inactive', 0, NOW())
                ");
                $insertStmt->bind_param("ssssssi", $username, $full_name, $phone, $standard, $center_name, $course_name, $batch_id);

                if ($insertStmt->execute()) {
                    // Show message to user about approval
                    $success = "Registration successful! Your account is pending approval by the admin/incharge. You will be notified once approved.";

                    // Optionally, do NOT set cookie/session until approved
                    // Uncomment below if you want to auto-login only after approval
                    // setcookie("parent_username", $username, time() + (10 * 365 * 24 * 60 * 60), "/", "", false, true);
                    // $_SESSION['parent_username'] = $username;
                    // $_SESSION['parent_id'] = $insertStmt->insert_id;
                    // header("Location: dashboard.php");
                    // exit();
                } else {
                    $error = "Error registering. Please try again.";
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        }
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

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

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
            <div class="col-lg-4 col-md-6 col-10 mx-auto text-center">
                <div class="logo-container">
                    <img src="../assets/images/habits_logo.png" alt="Habits 365 Club">
                </div>
                <form method="POST">
                    <h1 class="h6 mb-3">Parent Registration</h1>

                    <!-- Display error messages -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="full_name">Child Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control form-control-lg" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Mobile Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control form-control-lg" required>
                    </div>
                    <div class="form-group">
                        <label for="standard">Standard</label>
                        <input type="text" id="standard" name="standard" class="form-control form-control-lg">
                    </div>
                    <div class="form-group">
                        <label for="center_name">Center Name</label>
                        <select id="center_name" name="center_name" class="form-control select2" required>
                            <option value="">Select a Center</option>
                            <?php
                            // Fetch only enabled centers
                            $query = "SELECT location FROM centers WHERE status = 'enabled' ORDER BY location ASC";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['location']) . '">' . htmlspecialchars($row['location']) . '</option>';
                            }

                            $stmt->close();
                            ?>
                        </select>
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
                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="6Lc9vbwrAAAAALpCBho3FVdv6QSFXd5VtUZc3gNZ"></div>
                    </div>
                    
                    <button class="btn btn-lg btn-primary btn-block" type="submit">Register</button>

                    <p class="mt-3 text-center">
                        Already registered? <a href="login.php" class="text-primary"><strong>Login here</strong></a>
                    </p>
                </form>
            </div>
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
<script>
    $(document).ready(function () {
        $('#center_name').select2({
            width: '100%',
            placeholder: "Select a Center",
            allowClear: true,
        });
    });
</script>
</body>
</html>
