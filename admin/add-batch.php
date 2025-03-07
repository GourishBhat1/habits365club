<?php
// admin/add-batch.php

// Start session
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

// Establish database connection
$database = new Database();
$db = $database->getConnection();

// Fetch all teachers for batch assignment
$teachers = [];
$teacherQuery = "SELECT id, username FROM users WHERE role = 'teacher'";
$teacherStmt = $db->prepare($teacherQuery);
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
while ($row = $teacherResult->fetch_assoc()) {
    $teachers[] = $row;
}
$teacherStmt->close();

// Fetch all incharges for batch assignment
$incharges = [];
$inchargeQuery = "SELECT id, username FROM users WHERE role = 'incharge'";
$inchargeStmt = $db->prepare($inchargeQuery);
$inchargeStmt->execute();
$inchargeResult = $inchargeStmt->get_result();
while ($row = $inchargeResult->fetch_assoc()) {
    $incharges[] = $row;
}
$inchargeStmt->close();

// Fetch all parents that are not assigned to any batch
$parents = [];
$parentQuery = "SELECT id, username, full_name FROM users WHERE role = 'parent' AND batch_id IS NULL";
$parentStmt = $db->prepare($parentQuery);
$parentStmt->execute();
$parentResult = $parentStmt->get_result();
while ($row = $parentResult->fetch_assoc()) {
    $parents[] = $row;
}
$parentStmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_name = trim($_POST['batch_name'] ?? '');
    $teacher_id = $_POST['teacher_id'] ?? null;
    $incharge_id = $_POST['incharge_id'] ?? null;
    $parent_ids = $_POST['parent_ids'] ?? []; // Multiple parents selected
    $teacher_id = ($teacher_id === '') ? null : $teacher_id;
    $incharge_id = ($incharge_id === '') ? null : $incharge_id;

    // Validation
    if (empty($batch_name)) {
        $error = "Please enter a batch name.";
    } else {
        // Check if batch name already exists
        $checkQuery = "SELECT id FROM batches WHERE name = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bind_param("s", $batch_name);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "Batch name already exists.";
        } else {
            // Insert new batch with incharge
            $insertQuery = "INSERT INTO batches (name, teacher_id, incharge_id, created_at) VALUES (?, ?, ?, NOW())";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bind_param("sii", $batch_name, $teacher_id, $incharge_id);

            if ($insertStmt->execute()) {
                $batch_id = $insertStmt->insert_id; // Get new batch ID

                // Assign selected parents to the batch
                if (!empty($parent_ids)) {
                    foreach ($parent_ids as $parent_id) {
                        $assignQuery = "UPDATE users SET batch_id = ? WHERE id = ?";
                        $assignStmt = $db->prepare($assignQuery);
                        $assignStmt->bind_param("ii", $batch_id, $parent_id);
                        $assignStmt->execute();
                        $assignStmt->close();
                    }
                }

                $success = "Batch added successfully.";
                header("Location: batch-management.php?success=Batch added successfully.");
                exit();
            } else {
                $error = "An error occurred while adding the batch. Please try again.";
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Add New Batch - Habits Web App</title>
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
            <h2 class="page-title">Add New Batch</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Batch Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <form action="add-batch.php" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="batch_name">Batch Name <span class="text-danger">*</span></label>
                            <input type="text" id="batch_name" name="batch_name" class="form-control" required>
                            <div class="invalid-feedback">
                                Please enter a batch name.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="teacher_id">Assign Teacher (Optional)</label>
                            <select id="teacher_id" name="teacher_id" class="form-control select2">
                                <option value="">No Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="incharge_id">Assign Incharge (Optional)</label>
                            <select id="incharge_id" name="incharge_id" class="form-control select2">
                                <option value="">No Incharge</option>
                                <?php foreach ($incharges as $incharge): ?>
                                    <option value="<?php echo $incharge['id']; ?>"><?php echo htmlspecialchars($incharge['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="parent_ids">Assign Parents to Batch (Optional)</label>
                            <select id="parent_ids" name="parent_ids[]" class="form-control select2" multiple>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Batch</button>
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
        $('.select2').select2({ theme: 'bootstrap4' });
    });
</script>
</body>
</html>
