<?php
// admin/edit-habit.php

// Start session
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

$query = "SELECT id, user_id, title, description, frequency FROM habits WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $habit_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: habit-management.php");
    exit();
}

$habit = $result->fetch_assoc();

// Fetch all users (parents) for assignment
$userQuery = "SELECT id, username FROM users WHERE role = 'parent'";
$userStmt = $db->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $habit_title = trim($_POST['habit_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');

    // Basic validation
    if (empty($user_id) || empty($habit_title) || empty($frequency)) {
        $error = "Please fill in all required fields.";
    } else {
        // Update in database
        $updateQuery = "UPDATE habits SET user_id = ?, title = ?, description = ?, frequency = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("isssi", $user_id, $habit_title, $description, $frequency, $habit_id);

        if ($updateStmt->execute()) {
            $success = "Habit updated successfully.";
            // Refresh habit details
            $habit['user_id'] = $user_id;
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
    <?php include 'includes/header.php'; // Optional ?>
    <title>Edit Habit - Habits Web App</title>
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
                            <label for="user_id">Assign to User <span class="text-danger">*</span></label>
                            <select id="user_id" name="user_id" class="form-control select2" required>
                                <option value="">Select a User</option>
                                <?php while($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($habit['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a user.
                            </div>
                        </div>
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
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select a user"
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
