<?php
// admin/edit-habit.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Get habit ID from GET parameter
$habit_id = $_GET['id'] ?? '';

if (empty($habit_id)) {
    header("Location: habit-management.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Fetch habit details
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, title, description, frequency FROM habits WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $habit_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: habit-management.php");
    exit();
}

$habit = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $habit_title = trim($_POST['habit_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');

    // Basic validation
    if (empty($habit_title) || empty($frequency)) {
        $error = "Please fill in all required fields.";
    } else {
        // Update in database
        $updateQuery = "UPDATE habits SET title = ?, description = ?, frequency = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("sssi", $habit_title, $description, $frequency, $habit_id);

        if ($updateStmt->execute()) {
            $success = "Habit updated successfully.";
            // Refresh habit details
            $habit['title'] = $habit_title;
            $habit['description'] = $description;
            $habit['frequency'] = $frequency;
        } else {
            $error = "An error occurred. Please try again.";
        }
        $updateStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Edit Habit - Habits Web App</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Edit Habit</h2>
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
                    <form action="edit-habit.php?id=<?php echo $habit_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="habit_title">Habit Title <span class="text-danger">*</span></label>
                            <input type="text" id="habit_title" name="habit_title" class="form-control" value="<?php echo htmlspecialchars($habit['title']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a habit title.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($habit['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="frequency">Frequency <span class="text-danger">*</span></label>
                            <input type="text" id="frequency" name="frequency" class="form-control" value="<?php echo htmlspecialchars($habit['frequency']); ?>" required placeholder="e.g., Daily, Weekly">
                            <div class="invalid-feedback">
                                Please enter frequency.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Habit</button>
                        <a href="habit-management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
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
</script>
</body>
</html>
