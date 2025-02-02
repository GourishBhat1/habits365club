<?php
// upload_habits.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the parent is authenticated
if (!isset($_SESSION['parent_email']) && !isset($_COOKIE['parent_email'])) {
    echo "<p>❌ Debug: No authentication found. Redirecting...</p>";
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

// Fetch parent ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_email);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$stmt->close();

// Validate if parent exists
if (!$parent_id) {
    die("Parent not found.");
}

// Fetch all available habits
$query = "SELECT h.id, h.title, h.description, 
                 (SELECT status FROM evidence_uploads eu WHERE eu.habit_id = h.id AND eu.parent_id = ?) AS status 
          FROM habits h";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$habits = $stmt->get_result();
$stmt->close();

// **Check if there are any habits available**
$habit_count = $habits->num_rows;

// Handle file uploads
$upload_success = "";
$error_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['evidence'])) {
    foreach ($_FILES['evidence']['name'] as $habit_id => $file_name) {
        if (!empty($file_name)) {
            $file_tmp = $_FILES['evidence']['tmp_name'][$habit_id];
            $file_type = $_FILES['evidence']['type'][$habit_id];

            // Debugging
            echo "<p>📂 Processing file for Habit ID: $habit_id</p>";

            // Ensure upload directory exists
            $upload_dir = "uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate unique file name
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = "evidence_{$parent_id}_{$habit_id}_" . time() . "." . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            // Move the uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                echo "<p>✅ File moved successfully: $file_path</p>";

                // Insert into database
                $stmt = $conn->prepare("INSERT INTO evidence_uploads (parent_id, habit_id, file_path, file_type, status) VALUES (?, ?, ?, ?, 'pending')");
                $file_type_enum = (strpos($file_type, "image") !== false) ? "image" : "video";
                $stmt->bind_param("iiss", $parent_id, $habit_id, $file_path, $file_type_enum);

                if ($stmt->execute()) {
                    $upload_success = "Evidence uploaded successfully!";
                } else {
                    $error_message = "Database error: Unable to save the evidence.";
                }
                $stmt->close();
            } else {
                $error_message = "Error uploading file. Please try again.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Parent Dashboard - Upload Habits</title>

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
    .file-input-label {
      display: inline-block;
      padding: 10px 14px;
      background-color: #007bff;
      color: white;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 5px;
      text-align: center;
    }
    .file-input {
      display: none;
    }
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
                <div class="col-12">
                    <h2 class="page-title">Upload Habits</h2>

                    <!-- Success/Error Message -->
                    <?php if ($upload_success): ?>
                        <div class="alert alert-success"><?php echo $upload_success; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong>Available Habits</strong>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <?php if ($habit_count > 0): ?>
                                    <table class="table table-hover table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Habit</th>
                                                <th>Description</th>
                                                <th>Evidence Upload</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($habit = $habits->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($habit['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($habit['description']); ?></td>
                                                    <td>
                                                        <label class="file-input-label">
                                                            Capture Photo/Video 📷
                                                            <input type="file" name="evidence[<?php echo $habit['id']; ?>]" class="file-input" accept="image/*,video/*" capture="environment">
                                                        </label>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status = $habit['status'] ?? 'pending';
                                                        $badge_class = ($status == 'approved') ? 'badge-success' :
                                                                       (($status == 'rejected') ? 'badge-danger' : 'badge-warning');
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo ucfirst($status); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                    <button type="submit" class="btn btn-primary mt-3">Submit Evidence</button>
                                <?php else: ?>
                                    <p class="text-muted text-center">No habits found.</p>
                                <?php endif; ?>
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