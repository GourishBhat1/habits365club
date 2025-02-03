<?php
// admin/edit-batch.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Get batch ID from GET parameter
$batch_id = $_GET['id'] ?? '';

if (empty($batch_id)) {
    header("Location: batch-management.php");
    exit();
}

require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Fetch batch details
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, name, teacher_id FROM batches WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: batch-management.php");
    exit();
}

$batch = $result->fetch_assoc();

// Fetch all teachers for assignment
$teacherQuery = "SELECT id, username FROM users WHERE role = 'teacher'";
$teacherStmt = $db->prepare($teacherQuery);
$teacherStmt->execute();
$teachers = $teacherStmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_name = trim($_POST['batch_name'] ?? '');
    $teacher_id = trim($_POST['teacher_id'] ?? '');

    // Basic validation
    if (empty($batch_name)) {
        $error = "Please enter a batch name.";
    } elseif (empty($teacher_id)) {
        $error = "Please select a teacher.";
    } else {
        // Check if batch name already exists (excluding current batch)
        $checkQuery = "SELECT id FROM batches WHERE name = ? AND id != ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bind_param("si", $batch_name, $batch_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "Batch name already exists.";
        } else {
            // Update in database
            $updateQuery = "UPDATE batches SET name = ?, teacher_id = ? WHERE id = ?";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bind_param("sii", $batch_name, $teacher_id, $batch_id);

            if ($updateStmt->execute()) {
                header("Location: batch-management.php?success=Batch updated successfully.");
                exit();
            } else {
                $error = "An error occurred. Please try again.";
            }
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Edit Batch - Habits Web App</title>
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
            <h2 class="page-title">Edit Batch</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Batch Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form action="edit-batch.php?id=<?php echo $batch_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="batch_name">Batch Name <span class="text-danger">*</span></label>
                            <input type="text" id="batch_name" name="batch_name" class="form-control" value="<?php echo htmlspecialchars($batch['name']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a batch name.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="teacher_id">Assign Teacher <span class="text-danger">*</span></label>
                            <select id="teacher_id" name="teacher_id" class="form-control select2" required>
                                <option value="">Select a Teacher</option>
                                <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($batch['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a teacher.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Batch</button>
                        <a href="batch-management.php" class="btn btn-secondary">Cancel</a>
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
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select a teacher"
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
