<?php
// incharge/manage_batches.php

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

// Validate if incharge exists
if (!$incharge_id) {
    die("Incharge not found.");
}

// Fetch batches assigned to the incharge
$batches = [];
$query = "
    SELECT b.id, b.name, b.created_at 
    FROM batches b
    WHERE b.incharge_id = ?
    ORDER BY b.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $incharge_id);
$stmt->execute();
$batchesResult = $stmt->get_result();
while ($row = $batchesResult->fetch_assoc()) {
    $teacherStmt = $db->prepare("
        SELECT u.full_name 
        FROM batch_teachers bt 
        JOIN users u ON bt.teacher_id = u.id 
        WHERE bt.batch_id = ?
    ");
    $teacherStmt->bind_param("i", $row['id']);
    $teacherStmt->execute();
    $teacherResult = $teacherStmt->get_result();
    $teacherNames = [];
    while ($t = $teacherResult->fetch_assoc()) {
        $teacherNames[] = $t['full_name'];
    }
    $row['teacher_name'] = implode(', ', $teacherNames);
    $teacherStmt->close();
    
    $batches[] = $row;
}
$stmt->close();

// Handle disabling of batch parents
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['batch_id'])) {
    $batch_id = (int)$_GET['batch_id'];
    // Disable all parents in this batch
    $result = $db->query("UPDATE users SET status='inactive' WHERE batch_id = $batch_id AND role = 'parent'");
    if ($db->affected_rows > 0) {
        echo "<script>alert('All parents in this batch have been disabled.'); window.location.href = 'manage_batches.php';</script>";
    } else {
        echo "<script>alert('No parents found in this batch.'); window.location.href = 'manage_batches.php';</script>";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Manage Batches - Incharge</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/dataTables.bootstrap4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Manage Batches</h2>

            <div class="card shadow">
                <div class="card-header"><strong>Assigned Batches</strong></div>
                <div class="card-body">
                    <table id="batchesTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Batch Name</th>
                                <th>Teacher</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($batch['name']); ?></td>
                                <td><?php echo htmlspecialchars($batch['teacher_name'] ?? "Unassigned"); ?></td>
                                <td><?php echo htmlspecialchars($batch['created_at']); ?></td>
                                <td>
                                    <a href="edit_batch.php?batch_id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <button type="submit" name="disable_batch_parents" value="<?php echo $batch['id']; ?>" class="btn btn-sm btn-danger" onclick="confirmDisableAllParents(<?php echo $batch['id']; ?>)">Disable All Parents</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script>
    $(document).ready(function () {
        $('#batchesTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });

    // Function to confirm before disabling all parents
    function confirmDisableAllParents(batchId) {
        if (confirm('Are you sure you want to disable all parents in this batch?')) {
            window.location.href = 'manage_batches.php?batch_id=' + batchId;
        }
    }
</script>
</body>
</html>