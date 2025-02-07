<?php
// teacher/delete-meet.php

// Start session
session_start();

// Check if the teacher is authenticated via session or cookie
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Include the database connection
require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch teacher ID from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;

if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];

    // Prepare statement to get teacher ID based on email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher'");
    if ($stmt) {
        $stmt->bind_param("s", $teacher_email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($teacher_id);
            $stmt->fetch();
            $_SESSION['teacher_id'] = $teacher_id;
        } else {
            // Invalid cookie, redirect to login
            header("Location: index.php?message=invalid_cookie");
            exit();
        }
        $stmt->close();
    } else {
        // SQL prepare failed
        $error = "An error occurred. Please try again later.";
        error_log("Database query failed: " . $db->error);
    }
}

// Get meet ID from GET parameter
$meet_id = $_GET['meet_id'] ?? null;

if (!$meet_id || !is_numeric($meet_id)) {
    header("Location: meets.php?message=invalid_meet_id");
    exit();
}

// Delete the meet link from the database
$deleteQuery = "DELETE FROM batch_meet_links WHERE id = ? AND batch_id IN (SELECT id FROM batches WHERE teacher_id = ?)";
$deleteStmt = $db->prepare($deleteQuery);
if ($deleteStmt) {
    $deleteStmt->bind_param("ii", $meet_id, $teacher_id);
    if ($deleteStmt->execute()) {
        if ($deleteStmt->affected_rows > 0) {
            $success = "Google Meet link deleted successfully.";
        } else {
            $error = "Meet link not found or you are not authorized to delete it.";
        }
    } else {
        $error = "An error occurred. Please try again.";
    }
    $deleteStmt->close();
} else {
    $error = "Failed to prepare the delete statement.";
    error_log("Prepare failed: " . $db->error);
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Delete Meet Link - Habits365Club</title>
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
            <h2 class="page-title">Delete Meet Link</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Delete Meet Link Confirmation</h5>
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
                        <a href="meets.php" class="btn btn-primary">Back to Meet Links</a>
                    <?php else: ?>
                        <p>Are you sure you want to delete this Google Meet link?</p>
                        <form action="delete-meet.php?meet_id=<?php echo $meet_id; ?>" method="POST">
                            <button type="submit" class="btn btn-danger">Yes, Delete</button>
                            <a href="meets.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
