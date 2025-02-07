<?php
// teacher/edit-assessment.php

session_start();

// Check if the teacher is authenticated
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

    // Get teacher ID based on email
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
        $error = "Database error. Please try again.";
        error_log("Database query failed: " . $db->error);
    }
}

// Validate assessment ID
$assessment_id = $_GET['id'] ?? null;
if (!$assessment_id) {
    die("Invalid assessment ID.");
}

// Fetch existing assessment details
$assessmentQuery = "SELECT a.id, a.habit_id, a.child_id, a.assessment_text, h.title AS habit_name, u.username AS student_name
                    FROM assessments a
                    JOIN habits h ON a.habit_id = h.id
                    JOIN users u ON a.child_id = u.id
                    WHERE a.id = ? AND h.teacher_id = ?";
$stmt = $db->prepare($assessmentQuery);
if ($stmt) {
    $stmt->bind_param("ii", $assessment_id, $teacher_id);
    $stmt->execute();
    $assessmentResult = $stmt->get_result();
    $assessment = $assessmentResult->fetch_assoc();
    $stmt->close();
} else {
    $error = "Failed to retrieve assessment.";
    error_log("Prepare failed: " . $db->error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_assessment_text = trim($_POST['assessment_text'] ?? '');

    if (empty($updated_assessment_text)) {
        $error = "Assessment text cannot be empty.";
    } else {
        // Update assessment in the database
        $updateQuery = "UPDATE assessments SET assessment_text = ?, assessed_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($updateQuery);
        if ($stmt) {
            $stmt->bind_param("si", $updated_assessment_text, $assessment_id);
            if ($stmt->execute()) {
                $success = "Assessment updated successfully.";
            } else {
                $error = "Failed to update assessment.";
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare update statement.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Edit Assessment - Habits365Club</title>
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
            <h2 class="page-title">Edit Assessment</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Update Assessment</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if ($assessment): ?>
                        <form action="edit-assessment.php?id=<?php echo $assessment_id; ?>" method="POST" class="needs-validation" novalidate>
                            <div class="form-group">
                                <label>Habit</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($assessment['habit_name']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Student</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($assessment['student_name']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="assessment_text">Assessment <span class="text-danger">*</span></label>
                                <textarea id="assessment_text" name="assessment_text" class="form-control" rows="5" required><?php echo htmlspecialchars($assessment['assessment_text']); ?></textarea>
                                <div class="invalid-feedback">Please enter an assessment.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Assessment</button>
                            <a href="assessments.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php else: ?>
                        <p>Assessment not found. <a href="assessments.php">Go back</a>.</p>
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
        $('.select2').select2({ theme: 'bootstrap4' });

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
