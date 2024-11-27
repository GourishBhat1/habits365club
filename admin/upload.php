<?php
// admin/upload.php

// Start session
session_start();

// Check if the user is authenticated and is a parent
if (!isset($_SESSION['user_email']) && !isset($_COOKIE['user_email'])) {
    header("Location: ../index.php");
    exit();
}

// Assuming 'role' is stored in session
if ($_SESSION['role'] !== 'parent') {
    header("Location: ../index.php");
    exit();
}

require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../connection.php';

    $user_id = $_SESSION['user_id']; // Assuming user ID is stored in session
    $habit_id = trim($_POST['habit_id'] ?? '');

    // Check if file was uploaded without errors
    if (isset($_FILES['upload']) && $_FILES['upload']['error'] == 0) {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'mp4' => 'video/mp4'];
        $filename = $_FILES['upload']['name'];
        $filetype = $_FILES['upload']['type'];
        $filesize = $_FILES['upload']['size'];

        // Verify file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists(strtolower($ext), $allowed)) {
            $error = "Error: Please select a valid file format.";
        }

        // Verify file size - 5MB maximum
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $error = "Error: File size is larger than the allowed limit.";
        }

        // Verify MIME type
        if (!in_array($filetype, $allowed)) {
            $error = "Error: Please select a valid file format.";
        }

        if (empty($error)) {
            // Create a unique file name
            $new_filename = uniqid() . "." . $ext;
            $upload_dir = "../uploads/";
            $upload_path = $upload_dir . $new_filename;

            // Move the file to the upload directory
            if (move_uploaded_file($_FILES['upload']['tmp_name'], $upload_path)) {
                // Determine file type
                $uploaded_type = strpos($filetype, 'image') !== false ? 'image' : (strpos($filetype, 'video') !== false ? 'video' : 'other');

                // Insert into uploads table
                $insertQuery = "INSERT INTO uploads (user_id, habit_id, file_path, file_type, uploaded_at) VALUES (?, ?, ?, ?, NOW())";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bind_param("iiss", $user_id, $habit_id, $upload_path, $uploaded_type);

                if ($insertStmt->execute()) {
                    $success = "File uploaded successfully.";

                    // Automatically assign rewards
                    assignRewards($db, $user_id, $uploaded_type);
                } else {
                    $error = "Error: Could not insert upload into database.";
                }
                $insertStmt->close();
            } else {
                $error = "Error: There was a problem uploading your file. Please try again.";
            }
        }
    } else {
        $error = "Error: " . $_FILES['upload']['error'];
    }
}

// Function to assign rewards
function assignRewards($db, $user_id, $uploaded_type) {
    // Define reward logic
    // Example:
    // - Uploading an image grants 10 points and 1 badge
    // - Uploading a video grants 20 points and 2 badges
    // You can customize this logic as per your requirements

    if ($uploaded_type === 'image') {
        $points = 10;
        $badges = 1;
        $certificates = 0;
    } elseif ($uploaded_type === 'video') {
        $points = 20;
        $badges = 2;
        $certificates = 0;
    } else {
        // No rewards for other types
        return;
    }

    // Insert into rewards table
    $rewardQuery = "INSERT INTO rewards (user_id, points, badges, certificates, created_at) VALUES (?, ?, ?, ?, NOW())";
    $rewardStmt = $db->prepare($rewardQuery);
    $rewardStmt->bind_param("iiii", $user_id, $points, $badges, $certificates);
    $rewardStmt->execute();
    $rewardStmt->close();

    // Optionally, you can implement notifications or logs here
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Upload Habit - Habits Web App</title>
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="css/select2.min.css">
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
<body class="vertical light">
<div class="wrapper">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Upload Habit</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Upload Progress</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    <form action="upload.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="habit_id">Select Habit <span class="text-danger">*</span></label>
                            <select id="habit_id" name="habit_id" class="form-control select2" required>
                                <option value="">Select a Habit</option>
                                <?php
                                // Fetch habits for the logged-in parent
                                $habitQuery = "SELECT id, title FROM habits WHERE user_id = ?";
                                $habitStmt = $db->prepare($habitQuery);
                                $user_id = $_SESSION['user_id'];
                                $habitStmt->bind_param("i", $user_id);
                                $habitStmt->execute();
                                $habits = $habitStmt->get_result();

                                while($habit = $habits->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $habit['id']; ?>"><?php echo htmlspecialchars($habit['title']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a habit.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="upload">Upload File <span class="text-danger">*</span></label>
                            <input type="file" id="upload" name="upload" class="form-control-file" required>
                            <div class="invalid-feedback">
                                Please upload a file.
                            </div>
                            <small class="form-text text-muted">Allowed formats: JPG, JPEG, PNG, GIF, MP4. Max size: 5MB.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select a habit"
        });

        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    });
</script>
</body>
</html>
