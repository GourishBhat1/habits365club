<?php
// teacher/assess-habit.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

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

// Fetch students and their habit tracking records under this teacher
$habitTracking = [];
$habitsQuery = "
    SELECT ht.id AS tracking_id, h.title AS habit_name, u.username AS student_name, ht.status
    FROM habit_tracking ht
    JOIN habits h ON ht.habit_id = h.id
    JOIN users u ON ht.user_id = u.id
    JOIN batches b ON u.batch_id = b.id
    WHERE b.teacher_id = ?
";
$habitsStmt = $db->prepare($habitsQuery);
if ($habitsStmt) {
    $habitsStmt->bind_param("i", $teacher_id);
    $habitsStmt->execute();
    $habitsResult = $habitsStmt->get_result();
    while ($row = $habitsResult->fetch_assoc()) {
        $habitTracking[] = $row;
    }
    $habitsStmt->close();
} else {
    $error = "Failed to retrieve habits.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tracking_id = trim($_POST['tracking_id'] ?? '');
    $assessment_status = trim($_POST['assessment_status'] ?? '');

    // Basic validation
    if (empty($tracking_id)) {
        $error = "Please select a student's habit to assess.";
    } elseif (empty($assessment_status)) {
        $error = "Please select an assessment status.";
    } else {
        // Update habit tracking status
        $updateQuery = "UPDATE habit_tracking SET status = ?, updated_at = NOW() WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        if ($updateStmt) {
            $updateStmt->bind_param("si", $assessment_status, $tracking_id);
            if ($updateStmt->execute()) {
                $success = "Assessment recorded successfully.";
            } else {
                $error = "An error occurred. Please try again.";
            }
            $updateStmt->close();
        } else {
            $error = "Failed to prepare the update statement.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Add Assessment - Habits365Club</title>
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
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Add New Assessment</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Assessment Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($habitTracking)): ?>
                        <form action="assess-habit.php" method="POST" class="needs-validation" novalidate>
                            <div class="form-group">
                                <label for="tracking_id">Select Student & Habit <span class="text-danger">*</span></label>
                                <select id="tracking_id" name="tracking_id" class="form-control select2" required>
                                    <option value="">Select a Student's Habit</option>
                                    <?php foreach ($habitTracking as $record): ?>
                                        <option value="<?php echo htmlspecialchars($record['tracking_id']); ?>">
                                            <?php echo htmlspecialchars($record['student_name'] . " - " . $record['habit_name'] . " (" . ucfirst($record['status']) . ")"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="assessment_status">Assessment Status <span class="text-danger">*</span></label>
                                <select id="assessment_status" name="assessment_status" class="form-control select2" required>
                                    <option value="">Select a Status</option>
                                    <option value="approved">✅ Approved</option>
                                    <option value="pending">⏳ Pending</option>
                                    <option value="rejected">❌ Rejected</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Record Assessment</button>
                            <a href="assessments.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php else: ?>
                        <p>No habit tracking records found. <a href="dashboard.php">Go back to Dashboard</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<script src="js/jquery.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select an option"
        });

        // Form validation
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
