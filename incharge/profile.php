<?php
// incharge/profile.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Retrieve incharge username
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch incharge details
$stmt = $conn->prepare("SELECT id, full_name, username, email, profile_picture FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$result = $stmt->get_result();
$incharge = $result->fetch_assoc();
$incharge_id = $incharge['id'] ?? null;
$incharge_name = $incharge['username'] ?? '';
$incharge_email = $incharge['email'] ?? '';
$incharge_pic = $incharge['profile_picture'] ?? 'assets/images/user.png';
$stmt->close();

// Validate if incharge exists
if (!$incharge_id) {
    die("Incharge not found.");
}

// Handle profile update
$update_success = "";
$error_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_email = trim($_POST['incharge_email']);
    $new_password = trim($_POST['incharge_password']);

    // Check if email is already taken (excluding current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $new_email, $incharge_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $error_message = "❌ Email already in use by another account.";
    } else {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profile_pictures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = time() . '_' . basename($_FILES['profile_picture']['name']);
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                $incharge_pic = $targetPath;
            }
        }

        // Update user details
        if (!empty($new_password)) {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET email = ?, password = ?, profile_picture = ? WHERE id = ?");
            $stmt->bind_param("sssi", $new_email, $hashed_password, $incharge_pic, $incharge_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ?, profile_picture = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_email, $incharge_pic, $incharge_id);
        }

        if ($stmt->execute()) {
            $update_success = "✅ Profile updated successfully!";
            // Update session/cookie if email was changed
            $_SESSION['incharge_username'] = $incharge_username;
            setcookie("incharge_username", $incharge_username, time() + (30 * 24 * 60 * 60), "/", "", false, true);
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
  <?php include 'includes/header.php'; ?>
  <title>Incharge Profile - Habits365Club</title>

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
                            <form action="" method="POST" enctype="multipart/form-data">
                                <!-- Username (Read-Only) -->
                                <div class="form-group">
                                    <label for="incharge_name">Username</label>
                                    <input type="text" name="incharge_name" id="incharge_name" class="form-control" required readonly
                                           value="<?php echo htmlspecialchars($incharge_name); ?>">
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label for="incharge_email">Email</label>
                                    <input type="email" name="incharge_email" id="incharge_email" class="form-control" required
                                           value="<?php echo htmlspecialchars($incharge_email); ?>">
                                </div>

                                <!-- Password -->
                                <div class="form-group">
                                    <label for="incharge_password">New Password (Leave blank to keep current)</label>
                                    <input type="password" name="incharge_password" id="incharge_password" class="form-control"
                                           placeholder="Enter new password if changing">
                                </div>

                                <!-- Upload New Profile Picture -->
                                <div class="form-group">
                                    <label for="profile_picture">Upload New Profile Picture</label>
                                    <input type="file" name="profile_picture" id="profile_picture" class="form-control-file">
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
