<?php
// teacher/edit-meet.php

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

// Fetch the existing meet link details
$meetQuery = "SELECT batch_id, meet_link, scheduled_at FROM batch_meet_links WHERE id = ? AND batch_id IN (SELECT id FROM batches WHERE teacher_id = ?)";
$meetStmt = $db->prepare($meetQuery);
if ($meetStmt) {
    $meetStmt->bind_param("ii", $meet_id, $teacher_id);
    $meetStmt->execute();
    $meetStmt->store_result();

    if ($meetStmt->num_rows == 1) {
        $meetStmt->bind_result($batch_id, $meet_link, $scheduled_at);
        $meetStmt->fetch();
        list($class_date, $class_time) = explode(' ', $scheduled_at);
    } else {
        header("Location: meets.php?message=meet_not_found");
        exit();
    }
    $meetStmt->close();
} else {
    $error = "Failed to retrieve meet details.";
    error_log("Prepare failed: " . $db->error);
}

// Handle form submission for updating the meet link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = $_POST['batch_id'] ?? null;
    $meet_link = trim($_POST['meet_link'] ?? '');
    $class_date = trim($_POST['class_date'] ?? '');
    $class_time = trim($_POST['class_time'] ?? '');

    // Basic validation
    if (empty($batch_id)) {
        $error = "Please select a batch.";
    } elseif (empty($meet_link)) {
        $error = "Please enter the Google Meet link.";
    } elseif (empty($class_date)) {
        $error = "Please select the class date.";
    } elseif (empty($class_time)) {
        $error = "Please select the class time.";
    } elseif (!filter_var($meet_link, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL for the Google Meet link.";
    } else {
        // Update meet link in database
        $updateQuery = "UPDATE batch_meet_links SET batch_id = ?, meet_link = ?, scheduled_at = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        if ($updateStmt) {
            $scheduled_at = $class_date . ' ' . $class_time;
            $updateStmt->bind_param("issi", $batch_id, $meet_link, $scheduled_at, $meet_id);

            if ($updateStmt->execute()) {
                $success = "Google Meet link updated successfully.";
            } else {
                $error = "An error occurred. Please try again.";
            }
            $updateStmt->close();
        } else {
            $error = "Failed to prepare the update statement.";
            error_log("Prepare failed: " . $db->error);
        }
    }
}

// Fetch batches assigned to this teacher
$batchesQuery = "SELECT id, name FROM batches WHERE teacher_id = ?";
$batchesStmt = $db->prepare($batchesQuery);
if ($batchesStmt) {
    $batchesStmt->bind_param("i", $teacher_id);
    $batchesStmt->execute();
    $batchesResult = $batchesStmt->get_result();
} else {
    $error = "Failed to retrieve batches.";
    error_log("Prepare failed: " . $db->error);
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Edit Meet Link - Habits365Club</title>
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
            <h2 class="page-title">Edit Meet Link</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Meet Link Details</h5>
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
                    <form action="edit-meet.php?meet_id=<?php echo $meet_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="batch_id">Batch <span class="text-danger">*</span></label>
                            <select id="batch_id" name="batch_id" class="form-control select2" required>
                                <option value="">Select a Batch</option>
                                <?php while ($batch = $batchesResult->fetch_assoc()): ?>
                                    <option value="<?php echo $batch['id']; ?>" <?php echo ($batch['id'] == $batch_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($batch['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a batch.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="meet_link">Google Meet Link <span class="text-danger">*</span></label>
                            <input type="url" id="meet_link" name="meet_link" class="form-control" value="<?php echo htmlspecialchars($meet_link); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid Google Meet link.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="class_date">Class Date <span class="text-danger">*</span></label>
                            <input type="date" id="class_date" name="class_date" class="form-control" value="<?php echo htmlspecialchars($class_date); ?>" required>
                            <div class="invalid-feedback">
                                Please select a class date.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="class_time">Class Time <span class="text-danger">*</span></label>
                            <input type="time" id="class_time" name="class_time" class="form-control" value="<?php echo htmlspecialchars($class_time); ?>" required>
                            <div class="invalid-feedback">
                                Please select a class time.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Meet Link</button>
                        <a href="meets.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select a batch"
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
