<?php
// admin/manage-parents.php

// Start session
session_start();

// Enable error reporting for debugging (Remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Get the batch ID from GET parameters
$batch_id = $_GET['batch_id'] ?? null;

// Validate batch ID
if (!$batch_id || !is_numeric($batch_id)) {
    header("Location: batch-management.php?message=invalid_batch_id");
    exit();
}

// Fetch batch details
$batchQuery = "SELECT id, name FROM batches WHERE id = ?";
$batchStmt = $db->prepare($batchQuery);
if ($batchStmt) {
    $batchStmt->bind_param("i", $batch_id);
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    if ($batchResult->num_rows !== 1) {
        header("Location: batch-management.php?message=batch_not_found");
        exit();
    }
    $batch = $batchResult->fetch_assoc();
    $batchStmt->close();
} else {
    $error = "Failed to retrieve batch details.";
    error_log("Prepare Statement Failed: " . $db->error);
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission for assigning parents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_parent'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $parent_ids = $_POST['parent_ids'] ?? [];

        // Validate parent IDs
        if (empty($parent_ids)) {
            $error = "Please select at least one parent.";
        } else {
            foreach ($parent_ids as $parent_id) {
                if (!is_numeric($parent_id)) {
                    $error = "Invalid parent selected.";
                    break;
                }

                // Check if the parent is already assigned to the batch
                $checkQuery = "SELECT id FROM batches_parents WHERE batch_id = ? AND parent_id = ?";
                $checkStmt = $db->prepare($checkQuery);
                if ($checkStmt) {
                    $checkStmt->bind_param("ii", $batch_id, $parent_id);
                    $checkStmt->execute();
                    $checkStmt->store_result();
                    if ($checkStmt->num_rows === 0) {
                        // Assign the parent to the batch
                        $assignQuery = "INSERT INTO batches_parents (batch_id, parent_id) VALUES (?, ?)";
                        $assignStmt = $db->prepare($assignQuery);
                        if ($assignStmt) {
                            $assignStmt->bind_param("ii", $batch_id, $parent_id);
                            if ($assignStmt->execute()) {
                                $success = "Parents assigned to the batch successfully.";
                            } else {
                                $error = "Failed to assign parent to the batch.";
                                error_log("Database Error: " . $db->error);
                            }
                            $assignStmt->close();
                        } else {
                            $error = "Failed to prepare assignment statement.";
                            error_log("Prepare Statement Failed: " . $db->error);
                        }
                    }
                    $checkStmt->close();
                } else {
                    $error = "Failed to check existing assignments.";
                    error_log("Prepare Statement Failed: " . $db->error);
                }
            }
        }
    }
}

// Handle form submission for deleting a parent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_parent'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $parent_id = $_POST['parent_id'] ?? null;

        // Validate parent ID
        if (!$parent_id || !is_numeric($parent_id)) {
            $error = "Invalid parent selected for deletion.";
        } else {
            // Delete the parent from the batch
            $deleteQuery = "DELETE FROM batches_parents WHERE batch_id = ? AND parent_id = ?";
            $deleteStmt = $db->prepare($deleteQuery);
            if ($deleteStmt) {
                $deleteStmt->bind_param("ii", $batch_id, $parent_id);
                if ($deleteStmt->execute()) {
                    if ($deleteStmt->affected_rows > 0) {
                        $success = "Parent removed from the batch successfully.";
                    } else {
                        $error = "Parent not found in this batch.";
                    }
                } else {
                    $error = "Failed to remove parent from the batch.";
                    error_log("Database Error: " . $db->error);
                }
                $deleteStmt->close();
            } else {
                $error = "Failed to prepare deletion statement.";
                error_log("Prepare Statement Failed: " . $db->error);
            }
        }
    }
}

// Fetch all parents not yet assigned to this batch for the assign dropdown
$availableParentsQuery = "SELECT id, username, email FROM users WHERE role = 'parent' AND id NOT IN (
                            SELECT parent_id FROM batches_parents WHERE batch_id = ?
                          )";
$availableParentsStmt = $db->prepare($availableParentsQuery);
$availableParents = [];
if ($availableParentsStmt) {
    $availableParentsStmt->bind_param("i", $batch_id);
    $availableParentsStmt->execute();
    $availableParentsResult = $availableParentsStmt->get_result();
    while ($parent = $availableParentsResult->fetch_assoc()) {
        $availableParents[] = $parent;
    }
    $availableParentsStmt->close();
} else {
    $error = "Failed to retrieve available parents.";
    error_log("Prepare Statement Failed: " . $db->error);
}

// Fetch all parents currently assigned to this batch
$assignedParentsQuery = "SELECT users.id, users.username, users.email FROM users
                         JOIN batches_parents ON users.id = batches_parents.parent_id
                         WHERE batches_parents.batch_id = ?";
$assignedParentsStmt = $db->prepare($assignedParentsQuery);
$assignedParents = [];
if ($assignedParentsStmt) {
    $assignedParentsStmt->bind_param("i", $batch_id);
    $assignedParentsStmt->execute();
    $assignedParentsResult = $assignedParentsStmt->get_result();
    while ($parent = $assignedParentsResult->fetch_assoc()) {
        $assignedParents[] = $parent;
    }
    $assignedParentsStmt->close();
} else {
    $error = "Failed to retrieve assigned parents.";
    error_log("Prepare Statement Failed: " . $db->error);
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Manage Parents - <?php echo htmlspecialchars($batch['name']); ?> - Habits Web App</title>
    <!-- DataTables CSS (if needed) -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <!-- Select2 CSS for enhanced select boxes -->
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
        .parent-card {
            margin-bottom: 15px;
        }
        .parent-details {
            margin-left: 10px;
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
            <h2 class="page-title">Manage Parents for Batch: <?php echo htmlspecialchars($batch['name']); ?></h2>
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

            <!-- Assign Parents Form -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h5 class="card-title">Assign New Parents</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($availableParents)): ?>
                        <form action="manage-parents.php?batch_id=<?php echo $batch_id; ?>" method="POST" class="form-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group mb-2">
                                <label for="parent_ids" class="sr-only">Select Parents</label>
                                <select id="parent_ids" name="parent_ids[]" class="form-control select2" multiple="multiple" required>
                                    <?php foreach ($availableParents as $parent): ?>
                                        <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['username'] . " (" . $parent['email'] . ")"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="assign_parent" class="btn btn-primary mb-2 ml-2">Assign Parents</button>
                        </form>
                    <?php else: ?>
                        <p>No available parents to assign. All parents are already assigned to this batch.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Parents List -->
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Assigned Parents</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($assignedParents)): ?>
                        <table id="assignedParentsTable" class="table table-hover datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Parent Name</th>
                                    <th>Email</th>
                                    <th>Assigned At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedParents as $parent): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($parent['id']); ?></td>
                                        <td><?php echo htmlspecialchars($parent['username']); ?></td>
                                        <td><?php echo htmlspecialchars($parent['email']); ?></td>
                                        <td>
                                            <?php
                                            // Fetch assigned_at timestamp
                                            $assignedAtQuery = "SELECT assigned_at FROM batches_parents WHERE batch_id = ? AND parent_id = ?";
                                            $assignedAtStmt = $db->prepare($assignedAtQuery);
                                            if ($assignedAtStmt) {
                                                $assignedAtStmt->bind_param("ii", $batch_id, $parent['id']);
                                                $assignedAtStmt->execute();
                                                $assignedAtStmt->bind_result($assigned_at);
                                                $assignedAtStmt->fetch();
                                                echo htmlspecialchars($assigned_at);
                                                $assignedAtStmt->close();
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <form action="manage-parents.php?batch_id=<?php echo $batch_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to remove this parent from the batch?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="parent_id" value="<?php echo $parent['id']; ?>">
                                                <button type="submit" name="delete_parent" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No parents are currently assigned to this batch.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- DataTables JS -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<!-- Select2 JS for enhanced select boxes -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('#assignedParentsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });

        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select parents"
        });
    });
</script>
</body>
</html>
