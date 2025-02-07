<?php
// teacher/assessments.php

session_start();

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

require_once '../connection.php';

$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch teacher ID from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;

if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];

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
            header("Location: index.php?message=invalid_cookie");
            exit();
        }
        $stmt->close();
    } else {
        $error = "An error occurred. Please try again later.";
    }
}

if ($teacher_id) {
    // Fetch assessments from habit_tracking instead of a separate table
    $assessmentsQuery = "
        SELECT ht.id, u.username AS parent_name, h.title AS habit_name, 
               ht.status AS assessment_text, ht.updated_at AS assessed_at
        FROM habit_tracking ht
        JOIN users u ON ht.user_id = u.id
        JOIN habits h ON ht.habit_id = h.id
        JOIN batches b ON u.batch_id = b.id
        WHERE b.teacher_id = ?
        ORDER BY ht.updated_at DESC
    ";

    $assessmentsStmt = $db->prepare($assessmentsQuery);
    if ($assessmentsStmt) {
        $assessmentsStmt->bind_param("i", $teacher_id);
        $assessmentsStmt->execute();
        $assessmentsResult = $assessmentsStmt->get_result();
        $assessmentsStmt->close();
    } else {
        $error = "Failed to retrieve assessments.";
    }
} else {
    $error = "Invalid session. Please log in again.";
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Manage Assessments - Habits365Club</title>
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
        .assessment-text {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Manage Assessments</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($assessmentsResult) && $assessmentsResult->num_rows > 0): ?>
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title">List of Assessments</h5>
                    </div>
                    <div class="card-body">
                        <table id="assessmentsTable" class="table table-hover datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Parent</th>
                                    <th>Habit</th>
                                    <th>Assessment</th>
                                    <th>Assessed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assessment = $assessmentsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assessment['id']); ?></td>
                                        <td><?php echo htmlspecialchars($assessment['parent_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assessment['habit_name']); ?></td>
                                        <td class="assessment-text"><?php echo htmlspecialchars($assessment['assessment_text']); ?></td>
                                        <td><?php echo htmlspecialchars($assessment['assessed_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p>No assessments found.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DataTables -->
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#assessmentsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
