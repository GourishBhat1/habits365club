<?php
// teacher/assigned-students.php

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
    $batchesQuery = "
        SELECT b.id, b.name, b.created_at 
        FROM batches b 
        JOIN batch_teachers bt ON b.id = bt.batch_id 
        WHERE bt.teacher_id = ?
    ";
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
    <title>Assigned Students - Habits365Club</title>
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
        }
        .student-list {
            max-height: 250px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Assigned Students</h2>
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

            <?php if (isset($batchesResult) && $batchesResult->num_rows > 0): ?>
                <?php while ($batch = $batchesResult->fetch_assoc()): ?>
                    <div class="card batch-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><?php echo htmlspecialchars($batch['name']); ?></h5>
                            <span class="text-muted">Created on: <?php echo htmlspecialchars($batch['created_at']); ?></span>
                        </div>
                        <div class="card-body">
                            <?php
                            // Fetch students in this batch
                            $studentsQuery = "
                                SELECT u.id, u.username, u.email 
                                FROM users u
                                JOIN batches_students bs ON u.id = bs.student_id
                                WHERE bs.batch_id = ?
                            ";
                            $studentsStmt = $db->prepare($studentsQuery);
                            if ($studentsStmt) {
                                $studentsStmt->bind_param("i", $batch['id']);
                                $studentsStmt->execute();
                                $studentsResult = $studentsStmt->get_result();
                            ?>
                                <?php if ($studentsResult->num_rows > 0): ?>
                                    <ul class="list-group student-list">
                                        <?php while ($student = $studentsResult->fetch_assoc()): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <strong><?php echo htmlspecialchars($student['username']); ?></strong> 
                                                <span><?php echo htmlspecialchars($student['email']); ?></span>
                                                <a href="student_profile.php?student_id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">View Profile</a>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No students assigned to this batch.</p>
                                <?php endif; ?>
                            <?php
                                $studentsStmt->close();
                            } else {
                                echo '<p class="text-danger">Failed to retrieve students for this batch.</p>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>You have no batches assigned. Please contact the admin to assign batches to you.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>

<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('.student-list').each(function() {
            if ($(this).children().length === 0) {
                $(this).closest('.card-body').html('<p>No students assigned to this batch.</p>');
            }
        });
    });
</script>
</body>
</html>
