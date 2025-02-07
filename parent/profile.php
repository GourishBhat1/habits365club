<?php
// profile.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the parent is authenticated
if (!isset($_SESSION['parent_email']) && !isset($_COOKIE['parent_email'])) {
    header("Location: index.php");
    exit();
}

// Retrieve parent email
$parent_email = $_SESSION['parent_email'] ?? $_COOKIE['parent_email'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Validate database connection
if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}

// Fetch parent details
$stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_email);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$parent_name = $parent['username'] ?? '';
$parent_email = $parent['email'] ?? '';
$stmt->close();

// Validate if parent exists
if (!$parent_id) {
    die("Parent not found.");
}

// Handle profile update
$update_success = "";
$error_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name = trim($_POST['parent_name']);
    $new_email = trim($_POST['parent_email']);
    $new_password = trim($_POST['parent_password']);

    // Check if email is already taken (excluding current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $new_email, $parent_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $error_message = "❌ Email already in use by another account.";
    } else {
        // Update user details
        if (!empty($new_password)) {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssi", $new_name, $new_email, $hashed_password, $parent_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_name, $new_email, $parent_id);
        }

        if ($stmt->execute()) {
            $update_success = "✅ Profile updated successfully!";
            // Update session/cookie if email was changed
            $_SESSION['parent_email'] = $new_email;
            setcookie("parent_email", $new_email, time() + (30 * 24 * 60 * 60), "/", "", false, true);
        } else {
            $error_message = "❌ Error updating profile. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Parent Dashboard - Profile</title>

  <!-- Including all CSS files -->
  <link rel="stylesheet" href="css/simplebar.css">
  <link rel="stylesheet" href="css/feather.css">
  <link rel="stylesheet" href="css/select2.css">
  <link rel="stylesheet" href="css/dropzone.css">
  <link rel="stylesheet" href="css/uppy.min.css">
  <link rel="stylesheet" href="css/jquery.steps.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">
  <link rel="stylesheet" href="css/quill.snow.css">
  <link rel="stylesheet" href="css/daterangepicker.css">
  <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
  <style>
    .alert {
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
    }
    .alert-success {
      background-color: #d4edda;
      color: #155724;
    }
    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
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
            <div class="row justify-content-center">
                <div class="col-12 col-md-8">
                    <h2 class="page-title">Profile</h2>
                    
                    <!-- Success/Error Messages -->
                    <?php if ($update_success): ?>
                        <div class="alert alert-success"><?php echo $update_success; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong>Update Your Details</strong>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <!-- Name -->
                                <div class="form-group">
                                    <label for="parent_name">Name</label>
                                    <input type="text" name="parent_name" id="parent_name" class="form-control" required readonly
                                           value="<?php echo htmlspecialchars($parent_name); ?>">
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label for="parent_email">Email</label>
                                    <input type="email" name="parent_email" id="parent_email" class="form-control" required
                                           value="<?php echo htmlspecialchars($parent_email); ?>">
                                </div>

                                <!-- Password -->
                                <div class="form-group">
                                    <label for="parent_password">New Password (Leave blank to keep current)</label>
                                    <input type="password" name="parent_password" id="parent_password" class="form-control"
                                           placeholder="Enter new password if changing">
                                </div>

                                <!-- Submit button -->
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div> <!-- .row -->
        </div> <!-- .container-fluid -->
    </main>
</div> <!-- .wrapper -->

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>