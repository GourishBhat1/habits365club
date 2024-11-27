<?php
// teacher/assess-habit.php

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

if ($teacher_id) {
    // Fetch habits assigned to the teacher's students
    $habitsQuery = "SELECT habits.id, habits.name, users.username FROM habits
                   JOIN users ON habits.student_id = users.id
                   WHERE habits.teacher_id = ?";
    $habitsStmt = $db->prepare($habitsQuery);
    if ($habitsStmt) {
        $habitsStmt->bind_param("i", $teacher_id);
        $habitsStmt->execute();
        $habitsResult = $habitsStmt->get_result();
        $habitsStmt->close();
    } else {
        $error = "Failed to retrieve habits.";
        error_log("Prepare failed: " . $db->error);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $habit_id = trim($_POST['habit_id'] ?? '');
    $child_id = trim($_POST['child_id'] ?? '');
    $assessment_text = trim($_POST['assessment_text'] ?? '');

    // Basic validation
    if (empty($habit_id)) {
        $error = "Please select a habit.";
    } elseif (empty($child_id)) {
        $error = "Please select a student.";
    } elseif (empty($assessment_text)) {
        $error = "Please enter your assessment.";
    } else {
        // Insert into database
        $insertQuery = "INSERT INTO assessments (teacher_id, habit_id, child_id, assessment_text, assessed_at) VALUES (?, ?, ?, ?, NOW())";
        $insertStmt = $db->prepare($insertQuery);
        if ($insertStmt) {
            $insertStmt->bind_param("iiis", $teacher_id, $habit_id, $child_id, $assessment_text);

            if ($insertStmt->execute()) {
                $success = "Assessment added successfully.";
            } else {
                if ($db->errno === 1062) { // Duplicate entry
                    $error = "Assessment already exists.";
                } else {
                    $error = "An error occurred. Please try again.";
                }
            }
            $insertStmt->close();
        } else {
            $error = "Failed to prepare the insert statement.";
            error_log("Prepare failed: " . $db->error);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Add Assessment - Habits365Club</title>
    <!-- Select2 CSS -->
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
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Add New Assessment</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Assessment Details</h5>
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
                    <?php endif; ?>
                    <?php if (isset($habitsResult) && $habitsResult->num_rows > 0): ?>
                        <form action="assess-habit.php" method="POST" class="needs-validation" novalidate>
                            <div class="form-group">
                                <label for="habit_id">Select Habit <span class="text-danger">*</span></label>
                                <select id="habit_id" name="habit_id" class="form-control select2" required>
                                    <option value="">Select a Habit</option>
                                    <?php while ($habit = $habitsResult->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($habit['id']); ?>">
                                            <?php echo htmlspecialchars($habit['name'] . " - " . $habit['username']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a habit.
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="child_id">Select Student <span class="text-danger">*</span></label>
                                <select id="child_id" name="child_id" class="form-control select2" required>
                                    <option value="">Select a Student</option>
                                    <!-- Students will be populated via AJAX based on selected habit -->
                                </select>
                                <div class="invalid-feedback">
                                    Please select a student.
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="assessment_text">Assessment <span class="text-danger">*</span></label>
                                <textarea id="assessment_text" name="assessment_text" class="form-control" rows="5" placeholder="Enter your assessment here..." required></textarea>
                                <div class="invalid-feedback">
                                    Please enter your assessment.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Assessment</button>
                            <a href="assessments.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php else: ?>
                        <p>No habits found. <a href="dashboard.php">Go back to Dashboard</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<!-- Optional: Include jQuery for AJAX -->
<script src="js/jquery.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select an option"
        });

        // Fetch students based on selected habit
        $('#habit_id').change(function () {
            var habitId = $(this).val();
            if (habitId) {
                $.ajax({
                    url: 'fetch_students.php',
                    type: 'POST',
                    data: { habit_id: habitId },
                    success: function (data) {
                        $('#child_id').html(data);
                        $('#child_id').prop('disabled', false);
                    },
                    error: function () {
                        alert('Failed to fetch students.');
                    }
                });
            } else {
                $('#child_id').html('<option value="">Select a Student</option>');
                $('#child_id').prop('disabled', true);
            }
        });

        // Bootstrap form validation
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
