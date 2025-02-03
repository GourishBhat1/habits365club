<?php
// admin/add-habit.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $habit_title = trim($_POST['habit_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');

    // Basic validation
    if (empty($habit_title) || empty($frequency)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert into database
        $insertQuery = "INSERT INTO habits (title, description, frequency, created_at) VALUES (?, ?, ?, NOW())";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bind_param("sss", $habit_title, $description, $frequency);

        if ($insertStmt->execute()) {
            $success = "Habit added successfully.";
        } else {
            $error = "An error occurred. Please try again.";
        }
        $insertStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Add New Habit - Habits Web App</title>
    <link rel="stylesheet" href="css/select2.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Add New Habit</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Habit Details</h5>
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
                    <form action="add-habit.php" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="habit_title">Habit Title <span class="text-danger">*</span></label>
                            <input type="text" id="habit_title" name="habit_title" class="form-control" required>
                            <div class="invalid-feedback">
                                Please enter a habit title.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="frequency">Frequency <span class="text-danger">*</span></label>
                            <input type="text" id="frequency" name="frequency" class="form-control" required placeholder="e.g., Daily, Weekly">
                            <div class="invalid-feedback">
                                Please enter frequency.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Habit</button>
                        <a href="habit-management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
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
