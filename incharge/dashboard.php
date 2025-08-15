<?php
// incharge/dashboard.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the incharge is authenticated via session or cookie
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID from session or cookie
$incharge_id = $_SESSION['incharge_id'] ?? null;

if (!$incharge_id && isset($_COOKIE['incharge_username'])) {
    $incharge_username = $_COOKIE['incharge_username'];

    // Fetch incharge ID using username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
    if (!$stmt) {
        die("❌ SQL Error: " . $db->error);
    }
    $stmt->bind_param("s", $incharge_username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($incharge_id);
        $stmt->fetch();
        $_SESSION['incharge_id'] = $incharge_id;
    } else {
        header("Location: index.php?message=invalid_cookie");
        exit();
    }
    $stmt->close();
}

// Fetch assigned batches for the incharge
$batches = [];
$stmt = $db->prepare("SELECT id, name, created_at FROM batches WHERE incharge_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($batch = $result->fetch_assoc()) {
        $batches[] = $batch;
    }
    $stmt->close();
} else {
    $error = "Failed to retrieve batches.";
}

// Fetch total active students (parents) assigned under this incharge's batches
$total_students = 0;
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM users 
    WHERE role = 'parent' AND status = 'active' AND batch_id IN (SELECT id FROM batches WHERE incharge_id = ?)
");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $stmt->bind_result($total_students);
    $stmt->fetch();
    $stmt->close();
}

// Fetch total **habits submitted today** for batches assigned to this incharge
$today_habits = 0;
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT id)
    FROM evidence_uploads
    WHERE parent_id IN (SELECT id FROM users WHERE role = 'parent' AND batch_id IN 
    (SELECT id FROM batches WHERE incharge_id = ?))
    AND DATE(uploaded_at) = CURDATE()  -- ✅ Count only today's habit submissions
");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $stmt->bind_result($today_habits);
    $stmt->fetch();
    $stmt->close();
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Incharge Dashboard - Habits365Club</title>
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
            padding: 15px;
        }
        .batch-icon {
            font-size: 40px;
            color: #007bff;
            margin-bottom: 15px;
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
            <h2 class="page-title">Incharge Dashboard</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Total Students -->
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <h6 class="mb-0">Total Students</h6>
                            <h3><?php echo $total_students; ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Total Batches -->
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <h6 class="mb-0">Total Batches</h6>
                            <h3><?php echo count($batches); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Today's Habits -->
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <h6 class="mb-0">Today's Habits</h6>
                            <h3><?php echo $today_habits; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assigned Batches -->
            <div class="row mt-4">
                <?php if (!empty($batches)): ?>
                    <?php foreach ($batches as $batch): ?>
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
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">You have no batches assigned. Please contact the admin.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>