<?php
// parent/upload_habits.php

session_start();
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

// Fetch parent ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_email);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$stmt->close();

if (!$parent_id) {
    die("Parent not found.");
}

// Fetch all available habits and their status
$query = "SELECT h.id, h.title, h.description, 
                 COALESCE((SELECT status FROM evidence_uploads eu 
                  WHERE eu.habit_id = h.id 
                  AND eu.parent_id = ? 
                  ORDER BY eu.uploaded_at DESC LIMIT 1), 'pending') AS status
          FROM habits h";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$habits = $stmt->get_result();
$stmt->close();

$habit_count = $habits->num_rows;

// Handle file uploads
$upload_success = "";
$error_message = "";
$upload_dir = "../admin/uploads/";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['evidence'])) {
    foreach ($_FILES['evidence']['name'] as $habit_id => $file_name) {
        if (!empty($file_name)) {
            $file_tmp = $_FILES['evidence']['tmp_name'][$habit_id];
            $file_type = $_FILES['evidence']['type'][$habit_id];

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = "evidence_{$parent_id}_{$habit_id}_" . time() . "." . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $file_path)) {
                // Update status to "uploaded" after file is successfully moved
                $stmt = $conn->prepare("
                    INSERT INTO evidence_uploads (parent_id, habit_id, file_path, file_type, status, uploaded_at) 
                    VALUES (?, ?, ?, ?, 'uploaded', NOW())
                    ON DUPLICATE KEY UPDATE status = 'uploaded', file_path = ?, uploaded_at = NOW()
                ");
                $file_type_enum = (strpos($file_type, "image") !== false) ? "image" : "video";
                $stmt->bind_param("iisss", $parent_id, $habit_id, $file_path, $file_type_enum, $file_path);

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
    <?php include 'includes/header.php'; ?>
    <title>Parent Dashboard - Upload Habits</title>
    <link rel="stylesheet" href="css/select2.min.css">
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
        .badge-pending { background-color: #ffc107; }
        .badge-uploaded { background-color: #007bff; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Upload Habits</h2>

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
    $status = $habit['status'] ?? 'pending';  // Default to 'pending' if null
    $status_classes = [
        'approved'  => 'badge-approved',
        'uploaded'  => 'badge-uploaded',
        'rejected'  => 'badge-rejected',
        'pending'   => 'badge-pending'
    ];
    $badge_class = $status_classes[$status] ?? 'badge-pending'; 
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
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({ theme: 'bootstrap4' });
    });
</script>
</body>
</html>
