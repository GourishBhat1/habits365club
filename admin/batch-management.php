<?php
// admin/batch-management.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once '../connection.php';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch all teachers for dropdown filter
$teachers = [];
$teacherQuery = "SELECT id, username FROM users WHERE role = 'teacher'";
$teacherStmt = $db->prepare($teacherQuery);
if ($teacherStmt) {
    $teacherStmt->execute();
    $teacherResult = $teacherStmt->get_result();
    while ($row = $teacherResult->fetch_assoc()) {
        $teachers[] = $row;
    }
    $teacherStmt->close();
}

// Get filter parameters
$selectedTeacher = $_GET['teacher_id'] ?? '';
$selectedBatch = $_GET['batch_name'] ?? '';

// Fetch all batches with optional filtering
$query = "SELECT batches.id, batches.name, users.username AS teacher, batches.created_at
          FROM batches LEFT JOIN users ON batches.teacher_id = users.id WHERE 1=1";

$params = [];
if (!empty($selectedTeacher)) {
    $query .= " AND batches.teacher_id = ?";
    $params[] = $selectedTeacher;
}
if (!empty($selectedBatch)) {
    $query .= " AND batches.name LIKE ?";
    $params[] = "%$selectedBatch%";
}

$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch batch statistics
$totalBatchesQuery = "SELECT COUNT(*) AS total_batches FROM batches";
$unassignedBatchesQuery = "SELECT COUNT(*) AS unassigned_batches FROM batches WHERE teacher_id IS NULL";

$totalBatchesStmt = $db->prepare($totalBatchesQuery);
$unassignedBatchesStmt = $db->prepare($unassignedBatchesQuery);

$totalBatchesStmt->execute();
$unassignedBatchesStmt->execute();

$totalBatchesResult = $totalBatchesStmt->get_result()->fetch_assoc();
$unassignedBatchesResult = $unassignedBatchesStmt->get_result()->fetch_assoc();

$totalBatches = $totalBatchesResult['total_batches'];
$unassignedBatches = $unassignedBatchesResult['unassigned_batches'];

$totalBatchesStmt->close();
$unassignedBatchesStmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Batch Management - Habits Web App</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/select2.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Manage Batches</h2>

            <!-- Overview Cards -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5>Total Batches</h5>
                            <h3><?php echo $totalBatches; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5>Unassigned Batches</h5>
                            <h3><?php echo $unassignedBatches; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Options -->
            <form method="GET" class="mb-4">
                <div class="form-row">
                    <div class="col-md-4">
                        <label>Filter by Teacher</label>
                        <select name="teacher_id" class="form-control select2">
                            <option value="">All Teachers</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($selectedTeacher == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Search Batch Name</label>
                        <input type="text" name="batch_name" class="form-control" value="<?php echo htmlspecialchars($selectedBatch); ?>">
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                    </div>
                </div>
            </form>

            <div class="card shadow">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">All Batches</h5>
                    <a href="add-batch.php" class="btn btn-primary">Add New Batch</a>
                </div>
                <div class="card-body">
                    <table id="batchTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Batch Name</th>
                                <th>Assigned Teacher</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td>
                                        <?php if ($row['teacher']): ?>
                                            <?php echo htmlspecialchars($row['teacher']); ?>
                                        <?php else: ?>
                                            <span class="text-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td>
                                        <a href="edit-batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete-batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this batch?');">Delete</a>
                                        <a href="manage-parents.php?batch_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Manage Parents</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('#batchTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });

        $('.select2').select2({
            theme: 'bootstrap4'
        });
    });
</script>
</body>
</html>
