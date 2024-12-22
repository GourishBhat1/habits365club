<?php
// teacher/dashboard.php

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
        $error = "An error occurred. Please try again later.";
        error_log("Database query failed: " . $db->error);
    }
}

if ($teacher_id) {
    // Fetch batches assigned to this teacher
    $batchesQuery = "SELECT id, name, created_at FROM batches WHERE teacher_id = ?";
    $batchesStmt = $db->prepare($batchesQuery);
    if ($batchesStmt) {
        $batchesStmt->bind_param("i", $teacher_id);
        $batchesStmt->execute();
        $batchesResult = $batchesStmt->get_result();
        $batchesStmt->close();
    } else {
        $error = "Failed to retrieve batches.";
        error_log("Prepare failed: " . $db->error);
    }
} else {
    $error = "Invalid session. Please log in again.";
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Teacher Dashboard - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
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
        .batch-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .batch-icon {
            font-size: 50px;
            color: #007bff;
            margin-bottom: 15px;
        }
        .card-body .btn {
            margin-top: 10px;
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
            <h2 class="page-title">Dashboard</h2>
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

            <div class="row">
                <?php if (isset($batchesResult) && $batchesResult->num_rows > 0): ?>
                    <?php while ($batch = $batchesResult->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="card batch-card text-center">
                                <div class="card-header">
                                    <i class="fas fa-users batch-icon"></i>
                                    <h5 class="card-title"><?php echo htmlspecialchars($batch['name']); ?></h5>
                                    <span class="text-muted">Created on: <?php echo htmlspecialchars($batch['created_at']); ?></span>
                                </div>
                                <div class="card-body">
                                    <a href="view_students.php?batch_id=<?php echo $batch['id']; ?>" class="btn btn-primary">View Students</a>
                                    <a href="batch_habits.php?batch_id=<?php echo $batch['id']; ?>" class="btn btn-info">View Habits</a>
                                    <a href="manage_rewards.php?batch_id=<?php echo $batch['id']; ?>" class="btn btn-success">Manage Rewards</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>You have no batches assigned. Please contact the admin to assign batches to you.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
