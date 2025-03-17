<?php
// incharge/edit_batch.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];
$stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$result = $stmt->get_result();
$incharge = $result->fetch_assoc();
$incharge_id = $incharge['id'] ?? null;
$stmt->close();

if (!$incharge_id) {
    die("Incharge not found.");
}

// Get batch ID
$batch_id = $_GET['batch_id'] ?? '';

// Fetch batch details
$stmt = $db->prepare("SELECT id, name, teacher_id FROM batches WHERE id = ? AND incharge_id = ?");
$stmt->bind_param("ii", $batch_id, $incharge_id);
$stmt->execute();
$result = $stmt->get_result();
$batch = $result->fetch_assoc();
$stmt->close();

if (!$batch) {
    die("Batch not found or unauthorized.");
}

// Fetch available teachers
$teachers = [];
$teacherQuery = "SELECT id, full_name FROM users WHERE role = 'teacher'";
$teacherStmt = $db->prepare($teacherQuery);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
while ($row = $teacherRes->fetch_assoc()) {
    $teachers[] = $row;
}
$teacherStmt->close();

// Fetch currently assigned parents
$assigned_parents = [];
$assignedQuery = "SELECT id, full_name FROM users WHERE role = 'parent' AND batch_id = ?";
$assignedStmt = $db->prepare($assignedQuery);
$assignedStmt->bind_param("i", $batch_id);
$assignedStmt->execute();
$assignedRes = $assignedStmt->get_result();
while ($row = $assignedRes->fetch_assoc()) {
    $assigned_parents[] = $row;
}
$assignedStmt->close();

// Fetch unassigned parents
$unassigned_parents = [];
$unassignedQuery = "SELECT id, full_name FROM users WHERE role = 'parent' AND batch_id IS NULL";
$unassignedStmt = $db->prepare($unassignedQuery);
$unassignedStmt->execute();
$unassignedRes = $unassignedStmt->get_result();
while ($row = $unassignedRes->fetch_assoc()) {
    $unassigned_parents[] = $row;
}
$unassignedStmt->close();

// Handle batch update
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_name = trim($_POST['batch_name']);
    $teacher_id = $_POST['teacher_id'] ?? null;
    $selected_parents = $_POST['parents'] ?? [];

    if (!empty($batch_name)) {
        // Update batch name and teacher
        $stmt = $db->prepare("UPDATE batches SET name = ?, teacher_id = ? WHERE id = ? AND incharge_id = ?");
        $stmt->bind_param("siii", $batch_name, $teacher_id, $batch_id, $incharge_id);
        if ($stmt->execute()) {
            $success = "Batch updated successfully!";
            $batch['name'] = $batch_name;
            $batch['teacher_id'] = $teacher_id;
        } else {
            $error = "Failed to update batch.";
        }
        $stmt->close();

        // Remove all existing parent assignments for the batch
        $stmt = $db->prepare("UPDATE users SET batch_id = NULL WHERE batch_id = ?");
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $stmt->close();

        // Assign selected parents to the batch
        foreach ($selected_parents as $parent_id) {
            $stmt = $db->prepare("UPDATE users SET batch_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $batch_id, $parent_id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $error = "Batch name cannot be empty.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Edit Batch - Incharge</title>
    <link rel="stylesheet" href="css/select2.min.css">
    <script src="js/jquery.min.js"></script>
    <script src="js/select2.min.js"></script>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Edit Batch</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header"><strong>Update Batch Details</strong></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="batch_name">Batch Name</label>
                            <input type="text" name="batch_name" id="batch_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($batch['name']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="teacher_id">Assign Teacher</label>
                            <select name="teacher_id" id="teacher_id" class="form-control">
                                <option value="">Unassigned</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>"
                                        <?php echo ($teacher['id'] == $batch['teacher_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="parents">Assign Parents</label>
                            <select name="parents[]" id="parents" class="form-control select2" multiple="multiple">
                                <!-- Assigned Parents -->
                                <?php foreach ($assigned_parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>" selected>
                                        <?php echo htmlspecialchars($parent['full_name']); ?> (Assigned)
                                    </option>
                                <?php endforeach; ?>
                                <!-- Unassigned Parents -->
                                <?php foreach ($unassigned_parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['full_name']); ?> (Unassigned)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Batch</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%',
            placeholder: "Select Parents",
            allowClear: true
        });
    });
</script>
</body>
</html>