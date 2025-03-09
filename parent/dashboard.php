<?php
// parent/dashboard.php

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

// Fetch parent ID
$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE username = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_username);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$parent_full_name = $parent['full_name'] ?? "Parent";
$stmt->close();

if (!$parent_id) {
    die("Parent not found.");
}

// Get current date
$current_date = date('Y-m-d');

// Fetch all available habits and their **assessment status** & **upload status**
$query = "
    SELECT h.id, h.title, h.description,
           COALESCE((
               SELECT eu.status FROM evidence_uploads eu 
               WHERE eu.habit_id = h.id 
               AND eu.parent_id = ? 
               ORDER BY eu.uploaded_at DESC LIMIT 1
           ), 'pending') AS assessment_status, 
           (SELECT COUNT(*) FROM evidence_uploads eu 
            WHERE eu.habit_id = h.id 
            AND eu.parent_id = ? 
            AND DATE(eu.uploaded_at) = ?) AS upload_count
    FROM habits h
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $parent_id, $parent_id, $current_date);
$stmt->execute();
$habits = $stmt->get_result();
$stmt->close();

$habit_count = $habits->num_rows;

// Handle file uploads
$upload_success = "";
$error_message = "";
$upload_dir = "../admin/uploads/";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($_FILES as $input_name => $file_data) {
        if (strpos($input_name, 'evidence') !== false) {
            foreach ($file_data['name'] as $habit_id => $file_name) {
                if (!empty($file_name)) {
                    $file_tmp = $file_data['tmp_name'][$habit_id];
                    $file_type = $file_data['type'][$habit_id];

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_file_name = "evidence_{$parent_id}_{$habit_id}_" . time() . "." . $file_ext;
                    $file_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Determine if it's an image or video
                        $file_type_enum = (strpos($file_type, "image") !== false) ? "image" : "video";

                        // Insert new record with "pending" assessment status and `points = 1`
                        $stmt = $conn->prepare("
                            INSERT INTO evidence_uploads (parent_id, habit_id, file_path, file_type, status, points, uploaded_at) 
                            VALUES (?, ?, ?, ?, 'approved', 1, NOW())
                        ");
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
    }
    header('Location: dashboard.php');
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Parent Dashboard - Habits365Club</title>

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
        .badge-pending { background-color: #ffc107; }
        .badge-uploaded { background-color: #007bff; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }

        /* Card-based UI for mobile-friendly design */
        .habit-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
        }
        .habit-title {
            font-size: 16px;
            font-weight: bold;
        }
        .habit-desc {
            font-size: 14px;
            color: #666;
        }

        .welcome-text {
    font-size: 22px;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
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
        <h2 class="page-title">Welcome, <?php echo htmlspecialchars($parent_full_name); ?>!</h2>

        <?php if ($upload_success): ?>
            <div class="alert alert-success"><?php echo $upload_success; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header">
                <strong>Upload Habits</strong>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <?php if ($habit_count > 0): ?>
                        <div class="row">
                            <?php while ($habit = $habits->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card habit-card p-3 mb-3 shadow-sm">
                                        <div class="habit-title font-weight-bold"><?php echo htmlspecialchars($habit['title']); ?></div>
                                        <div class="habit-desc text-muted"><?php echo htmlspecialchars($habit['description']); ?></div>

                                        <!-- Image Upload Button -->
<label class="custom-file-upload mt-3">
    <input type="file" id="imageEvidence_<?php echo $habit['id']; ?>" 
           name="image_evidence[<?php echo $habit['id']; ?>]" 
           class="file-input" accept="image/*" capture="camera"
           onchange="handleFileSelection('<?php echo $habit['id']; ?>', 'image')">
    <span class="btn btn-outline-primary">📸 Capture Image</span>
    <span id="imageLabel_<?php echo $habit['id']; ?>" class="file-name text-muted ml-2"></span>
</label>

<!-- Video Upload Button -->
<label class="custom-file-upload mt-3">
    <input type="file" id="videoEvidence_<?php echo $habit['id']; ?>" 
           name="video_evidence[<?php echo $habit['id']; ?>]" 
           class="file-input" accept="video/*" capture="camcorder"
           onchange="handleFileSelection('<?php echo $habit['id']; ?>', 'video')">
    <span class="btn btn-outline-success">🎥 Capture Video</span>
    <span id="videoLabel_<?php echo $habit['id']; ?>" class="file-name text-muted ml-2"></span>
</label>

<script>
function handleFileSelection(habitId, type) {
    const imageInput = document.getElementById(`imageEvidence_${habitId}`);
    const videoInput = document.getElementById(`videoEvidence_${habitId}`);
    const imageLabel = document.getElementById(`imageLabel_${habitId}`);
    const videoLabel = document.getElementById(`videoLabel_${habitId}`);

    if (type === 'image') {
        if (imageInput.files.length > 0) {
            videoInput.disabled = true; // Disable video input
            imageLabel.textContent = "📸 Image Selected"; // Show text instead of filename
            videoLabel.textContent = ""; // Clear video label
        } else {
            videoInput.disabled = false; // Re-enable video input if deselected
            imageLabel.textContent = "";
        }
    } else if (type === 'video') {
        if (videoInput.files.length > 0) {
            imageInput.disabled = true; // Disable image input
            videoLabel.textContent = "🎥 Video Selected"; // Show text instead of filename
            imageLabel.textContent = ""; // Clear image label
        } else {
            imageInput.disabled = false; // Re-enable image input if deselected
            videoLabel.textContent = "";
        }
    }
}

</script>



                                        <div class="mt-3">
                                            <!-- Upload Status -->
                                            <?php 
                                            $upload_status = ($habit['upload_count'] > 0) ? 'Uploaded' : 'Upload Pending';
                                            $upload_badge_class = ($habit['upload_count'] > 0) ? 'badge-success' : 'badge-warning';
                                            ?>
                                            <span class="badge text-white <?php echo $upload_badge_class; ?>">
                                                <?php echo $upload_status; ?>
                                            </span>

                                            <!-- Assessment Status -->
                                            <?php 
                                            $assessment_status = ucfirst($habit['assessment_status']);
                                            $status_classes = [
                                                'approved'  => 'badge-success',
                                                'pending'   => 'badge-warning',
                                                'rejected'  => 'badge-danger'
                                            ];
                                            $assessment_badge_class = $status_classes[$habit['assessment_status']] ?? 'badge-warning';
                                            ?>
                                            <span class="badge text-white <?php echo $assessment_badge_class; ?>">
                                                Assessment: <?php echo $assessment_status; ?>
                                            </span>
                                        </div>

                                        <!-- Submit Button (Hidden if Uploaded) -->
                                        <?php if ($habit['upload_count'] == 0): ?>
                                            <button type="submit" class="btn btn-primary btn-sm mt-3 btn-block">Submit Evidence</button>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No habits found.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
