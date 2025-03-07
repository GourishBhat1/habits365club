<?php
// parent/profile.php

session_start();
require_once '../connection.php';

// Check if the parent is authenticated
if (!isset($_SESSION['parent_username']) && !isset($_COOKIE['parent_username'])) {
    header("Location: index.php");
    exit();
}

// Retrieve parent username
$parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch parent details
$stmt = $conn->prepare("
    SELECT id, 
           COALESCE(full_name, '') AS full_name, 
           COALESCE(email, '') AS email, 
           COALESCE(phone, '') AS phone, 
           COALESCE(profile_picture, 'assets/images/default_profile.png') AS profile_picture 
    FROM users WHERE username = ? AND role = 'parent'
");
$stmt->bind_param("s", $parent_username);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$stmt->close();

// Assign values
$parent_id = $parent['id'] ?? null;
$parent_name = $parent['full_name'];
$parent_email = $parent['email'];
$parent_phone = $parent['phone'];
$parent_profile_pic = $parent['profile_picture']; // Default profile picture

// Validate if parent exists
if (!$parent_id) {
    die("Parent not found.");
}

// Handle profile update
$update_success = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_full_name = trim($_POST['full_name']);
    $new_email = trim($_POST['email']) ?: $parent_email;  // ✅ Email is optional now
    $new_phone = trim($_POST['phone']);
    $new_password = trim($_POST['password']);
    $profile_picture = $parent_profile_pic; // Default to current profile picture

    // Handle profile picture upload
    if (!empty($_FILES["profile_picture"]["name"])) {
        $upload_dir = "../uploads/profile_pictures/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
        $new_file_name = "profile_{$parent_id}_" . time() . "." . $file_ext;
        $file_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $file_path)) {
            $profile_picture = $file_path;
        } else {
            $error_message = "❌ Error uploading profile picture.";
        }
    }

    // Check if email is already taken (excluding current user)
    if (!empty($new_email) && $new_email !== $parent_email) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $parent_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_message = "❌ Email already in use by another account.";
        }
        $stmt->close();
    }

    if (!$error_message) {
        // ✅ Updating only full_name, email, phone, password, and profile_picture
        $fields_to_update = [];
        $params = [];
        $types = '';

        if (!empty($new_full_name)) {
            $fields_to_update[] = "full_name = ?";
            $params[] = $new_full_name;
            $types .= 's';
        }
        if (!empty($new_email) && $new_email !== $parent_email) {
            $fields_to_update[] = "email = ?";
            $params[] = $new_email;
            $types .= 's';
        }
        if (!empty($new_phone)) {
            $fields_to_update[] = "phone = ?";
            $params[] = $new_phone;
            $types .= 's';
        }
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $fields_to_update[] = "password = ?";
            $params[] = $hashed_password;
            $types .= 's';
        }
        if (!empty($_FILES["profile_picture"]["name"])) {
            $fields_to_update[] = "profile_picture = ?";
            $params[] = $profile_picture;
            $types .= 's';
        }

        if (!empty($fields_to_update)) {
            $query = "UPDATE users SET " . implode(", ", $fields_to_update) . " WHERE id = ?";
            $params[] = $parent_id;
            $types .= 'i';

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $update_success = "✅ Profile updated successfully!";
                $_SESSION['parent_username'] = $parent_username;
                setcookie("parent_username", $parent_username, time() + (30 * 24 * 60 * 60), "/", "", false, true);
            } else {
                $error_message = "❌ Error updating profile. Please try again.";
            }
            $stmt->close();
        }
    }
    header('Location:profile.php');
}
?>


<!doctype html>
<html lang="en">
<head>
  <?php include 'includes/header.php'; ?>
  <title>Parent Dashboard - Profile</title>

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
    .profile-img {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
    }
  </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8">
                    <h2 class="page-title">Profile</h2>

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
                        <div class="card-body text-center">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="form-group text-center">
                                    <img src="<?php echo htmlspecialchars($parent_profile_pic); ?>" alt="Profile Picture" class="profile-img">
                                </div>

                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($parent_name); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($parent_email); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($parent_phone); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Password (Leave blank to keep current)</label>
                                    <input type="password" name="password" class="form-control" placeholder="Enter new password if changing">
                                </div>

                                <div class="form-group">
                                    <label>Profile Picture</label>
                                    <input type="file" name="profile_picture" class="form-control">
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
