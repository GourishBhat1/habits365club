<?php
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

// Check if `batches` table exists
$batchesTableExists = $db->query("SHOW TABLES LIKE 'batches'")->num_rows > 0;

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

// Fetch all incharges for dropdown filter
$incharges = [];
$inchargeQuery = "SELECT id, username FROM users WHERE role = 'incharge'";
$inchargeStmt = $db->prepare($inchargeQuery);
if ($inchargeStmt) {
    $inchargeStmt->execute();
    $inchargeResult = $inchargeStmt->get_result();
    while ($row = $inchargeResult->fetch_assoc()) {
        $incharges[] = $row;
    }
    $inchargeStmt->close();
}

// Get filter parameters
$selectedTeacher = $_GET['teacher_id'] ?? '';
$selectedIncharge = $_GET['incharge_id'] ?? '';
$selectedBatch = $_GET['batch_name'] ?? '';

$batchData = [];
$totalBatches = 0;
$unassignedBatches = 0;

if ($batchesTableExists) {
    // Fetch batch data
    $query = "SELECT b.id, b.name, 
                     i.username AS incharge, 
                     b.created_at
              FROM batches b 
              LEFT JOIN users i ON b.incharge_id = i.id
              WHERE 1=1";

    $params = [];
    $paramTypes = "";
    
    if (!empty($selectedTeacher)) {
        $query .= " AND b.teacher_id = ?";
        $params[] = $selectedTeacher;
        $paramTypes .= "i";
    }
    if (!empty($selectedIncharge)) {
        $query .= " AND b.incharge_id = ?";
        $params[] = $selectedIncharge;
        $paramTypes .= "i";
    }
    if (!empty($selectedBatch)) {
        $query .= " AND b.name LIKE ?";
        $params[] = "%$selectedBatch%";
        $paramTypes .= "s";
    }

    $stmt = $db->prepare($query);
    if ($params) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $batchData[] = $row;
    }

    $stmt->close();

    // Fetch batch statistics separately
    $totalBatchesQuery = "SELECT COUNT(*) AS total_batches FROM batches";
    $totalBatchesStmt = $db->prepare($totalBatchesQuery);
    $totalBatchesStmt->execute();
    $totalBatchesResult = $totalBatchesStmt->get_result()->fetch_assoc();
    $totalBatchesStmt->close();

    $unassignedBatchesQuery = "SELECT COUNT(*) AS unassigned_batches FROM batches WHERE teacher_id IS NULL";
    $unassignedBatchesStmt = $db->prepare($unassignedBatchesQuery);
    $unassignedBatchesStmt->execute();
    $unassignedBatchesResult = $unassignedBatchesStmt->get_result()->fetch_assoc();
    $unassignedBatchesStmt->close();

    $totalBatches = $totalBatchesResult['total_batches'] ?? 0;
    $unassignedBatches = $unassignedBatchesResult['unassigned_batches'] ?? 0;
}

// Handle disabling of batch parents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_batch_parents'], $_POST['batch_id'])) {
    $batch_id = (int)$_POST['batch_id'];
    // Get all parent IDs in this batch
    $parentStmt = $db->prepare("SELECT u.id FROM users u JOIN batch_parents bp ON bp.parent_id = u.id WHERE bp.batch_id = ?");
    $parentStmt->bind_param("i", $batch_id);
    $parentStmt->execute();
    $parentResult = $parentStmt->get_result();
    $parentIds = [];
    while ($parent = $parentResult->fetch_assoc()) {
        $parentIds[] = $parent['id'];
    }
    $parentStmt->close();

    // Disable all parents
    if ($parentIds) {
        $ids = implode(',', array_map('intval', $parentIds));
        $db->query("UPDATE users SET status='inactive' WHERE id IN ($ids)");
        echo '<div class="alert alert-success">All parents in this batch have been disabled.</div>';
    } else {
        echo '<div class="alert alert-warning">No parents found in this batch.</div>';
    }
}
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

            <?php if ($batchesTableExists): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5>Total Batches</h5>
                            <h3><?php echo $totalBatches; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <br><br>

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
                        <label>Filter by Incharge</label>
                        <select name="incharge_id" class="form-control select2">
                            <option value="">All Incharges</option>
                            <?php foreach ($incharges as $incharge): ?>
                                <option value="<?php echo $incharge['id']; ?>" <?php echo ($selectedIncharge == $incharge['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($incharge['username']); ?>
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
                                <th>Assigned Incharge</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batchData as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <?php
                                    $teacherNames = [];
                                    $teacherStmt = $db->prepare("SELECT u.username FROM batch_teachers bt JOIN users u ON bt.teacher_id = u.id WHERE bt.batch_id = ?");
                                    $teacherStmt->bind_param("i", $row['id']);
                                    $teacherStmt->execute();
                                    $teacherResult = $teacherStmt->get_result();
                                    while ($teacher = $teacherResult->fetch_assoc()) {
                                        $teacherNames[] = $teacher['username'];
                                    }
                                    $teacherStmt->close();
                                    ?>
                                    <td><?php echo $teacherNames ? htmlspecialchars(implode(', ', $teacherNames)) : '<span class="text-danger">Unassigned</span>'; ?></td>
                                    <td><?php echo $row['incharge'] ? htmlspecialchars($row['incharge']) : '<span class="text-danger">Unassigned</span>'; ?></td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td>
                                        <a href="edit-batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete-batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this batch?');">Delete</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to disable all parents in this batch?');">
                                            <input type="hidden" name="disable_batch_parents" value="1">
                                            <input type="hidden" name="batch_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">Disable All Parents</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
