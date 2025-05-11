<?php
// incharge/notices.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php");
    exit();
}

// Retrieve incharge username
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch incharge ID & Location
$stmt = $conn->prepare("SELECT id, location FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$result = $stmt->get_result();
$incharge = $result->fetch_assoc();
$incharge_id = $incharge['id'] ?? null;
$incharge_location = $incharge['location'] ?? null;
$stmt->close();

// Validate if incharge exists
if (!$incharge_id) {
    die("Incharge not found.");
}

// Handle notice creation
$notice_success = "";
$notice_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_notice'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if (empty($title) || empty($message)) {
        $notice_error = "❌ Title and message are required.";
    } else {
        // Insert new notice with `location`
        $stmt = $conn->prepare("INSERT INTO notices (title, message, created_by, location) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $title, $message, $incharge_id, $incharge_location);

        if ($stmt->execute()) {
            $notice_success = "✅ Notice created successfully!";
        } else {
            $notice_error = "❌ Failed to create notice.";
        }
        $stmt->close();
    }
}

// Handle notice deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_notice'])) {
    $notice_id = $_POST['notice_id'];

    $stmt = $conn->prepare("DELETE FROM notices WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $notice_id, $incharge_id);

    if ($stmt->execute()) {
        $notice_success = "✅ Notice deleted successfully!";
    } else {
        $notice_error = "❌ Failed to delete notice.";
    }
    $stmt->close();
}

// Fetch all notices created by this incharge
$notices = [];
$stmt = $conn->prepare("SELECT * FROM notices WHERE created_by = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $incharge_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notices[] = $row;
}
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Incharge Dashboard - Notices</title>

    <link rel="stylesheet" href="css/app-light.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">

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
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Notices (<?php echo htmlspecialchars($incharge_location); ?>)</h2>

            <!-- Success/Error Messages -->
            <?php if ($notice_success): ?>
                <div class="alert alert-success"><?php echo $notice_success; ?></div>
            <?php endif; ?>
            <?php if ($notice_error): ?>
                <div class="alert alert-danger"><?php echo $notice_error; ?></div>
            <?php endif; ?>

            <!-- Notice Creation Form -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <strong>Create New Notice</strong>
                </div>
                <div class="card-body">
                    <form action="" method="POST" id="noticeForm">
                        <div class="form-group">
                            <label for="title">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="message">Message <span class="text-danger">*</span></label>
                            <textarea name="message" id="message" class="form-control summernote"></textarea>
                        </div>

                        <button type="submit" name="create_notice" class="btn btn-primary">Create Notice</button>
                    </form>
                </div>
            </div>

            <!-- Notices List -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>My Notices</strong>
                </div>
                <div class="card-body">
                    <?php if (count($notices) > 0): ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Location</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notices as $notice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($notice['title']); ?></td>
                                        <td><?php echo $notice['message']; ?></td>
                                        <td><?php echo htmlspecialchars($notice['location']); ?></td>
                                        <td><?php echo date("d M Y, h:i A", strtotime($notice['created_at'])); ?></td>
                                        <td>
                                            <form action="" method="POST" onsubmit="return confirm('Are you sure you want to delete this notice?');">
                                                <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                                <button type="submit" name="delete_notice" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            No notices available.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

</php><?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<script>
  $(document).ready(function() {
    $('.summernote').summernote({
      height: 300,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'clear']],
        ['fontname', ['fontname']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'picture']],
        ['view', ['fullscreen', 'codeview', 'help']]
      ]
    });
  });
</script>
</body>
</html>