<?php
// admin/upload-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Initialize variables
$error = '';
$success = '';

// Handle Approve/Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id']) && isset($_POST['action'])) {
    $submissionId = $_POST['submission_id'];
    $action = $_POST['action']; // 'approved' or 'rejected'
    $feedback = $_POST['feedback'] ?? '';

    // Update evidence status
    $stmt = $db->prepare("UPDATE evidence_uploads SET status = ?, feedback = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssi", $action, $feedback, $submissionId);
        if ($stmt->execute()) {
            $success = "Submission has been $action successfully.";
        } else {
            $error = "Failed to update submission.";
        }
        $stmt->close();
    } else {
        $error = "Error preparing statement.";
    }
}

// Fetch Centers
$centers = [];
$centerQuery = "SELECT DISTINCT location FROM users WHERE role = 'parent'";
$centerStmt = $db->prepare($centerQuery);
if ($centerStmt) {
    $centerStmt->execute();
    $centerRes = $centerStmt->get_result();
    while ($row = $centerRes->fetch_assoc()) {
        $centers[] = $row['location'];
    }
    $centerStmt->close();
}

// Fetch Batches
$batches = [];
$batchQuery = "SELECT id, name FROM batches";
$batchStmt = $db->prepare($batchQuery);
if ($batchStmt) {
    $batchStmt->execute();
    $batchRes = $batchStmt->get_result();
    while ($row = $batchRes->fetch_assoc()) {
        $batches[] = $row;
    }
    $batchStmt->close();
}

// Apply filters
$selectedCenter = $_GET['center'] ?? '';
$selectedBatch = $_GET['batch_id'] ?? '';

// Fetch all habit evidence submissions with filtering
$submissions = [];
$query = "
    SELECT 
        e.id, 
        u.full_name AS user_name, 
        u.username,
        h.title AS habit_name, 
        e.file_path, 
        e.status, 
        e.feedback,
        u.location AS center_name,
        b.name AS batch_name,
        e.uploaded_at
    FROM evidence_uploads e
    JOIN users u ON e.parent_id = u.id
    JOIN habits h ON e.habit_id = h.id
    LEFT JOIN batches b ON u.batch_id = b.id
    WHERE 1=1
";

// Apply Center filter
if (!empty($selectedCenter)) {
    $query .= " AND u.location = ?";
}

// Apply Batch filter
if (!empty($selectedBatch)) {
    $query .= " AND b.id = ?";
}

$query .= " ORDER BY e.uploaded_at DESC"; // ✅ Sorting by latest first

$stmt = $db->prepare($query);

if (!empty($selectedCenter) && !empty($selectedBatch)) {
    $stmt->bind_param("si", $selectedCenter, $selectedBatch);
} elseif (!empty($selectedCenter)) {
    $stmt->bind_param("s", $selectedCenter);
} elseif (!empty($selectedBatch)) {
    $stmt->bind_param("i", $selectedBatch);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $stmt->close();
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Upload Management - Admin</title>

    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/buttons.bootstrap4.min.css">
    <style>
        .badge-pending { background-color: #ffc107; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Upload Management</h2>

            <!-- Success/Error Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <form method="GET" class="mb-4">
                <div class="form-row align-items-end">
                    <div class="col-md-4">
                        <label for="center">Center</label>
                        <select name="center" id="center" class="form-control">
                            <option value="">All Centers</option>
                            <?php foreach ($centers as $center): ?>
                                <option value="<?php echo $center; ?>" <?php echo ($selectedCenter == $center) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($center); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="batch">Batch</label>
                        <select name="batch_id" id="batch" class="form-control">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>" <?php echo ($selectedBatch == $batch['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                    </div>
                </div>
            </form>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Habit Evidence Submissions</strong>
                </div>
                <div class="card-body table-responsive">
                    <table id="submissionsTable" class="table datatable">
                        <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Center</th>
                            <th>Batch</th>
                            <th>Habit</th>
                            <th>Evidence</th>
                            <th>Status</th>
                            <th>Feedback</th>
                            <th>Uploaded At</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sub['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['username']); ?></td>
                                <td><?php echo htmlspecialchars($sub['center_name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['batch_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($sub['habit_name']); ?></td>
                                <td>
                                    <?php if (!empty($sub['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?php echo 'badge-' . $sub['status']; ?>"><?php echo ucfirst($sub['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($sub['feedback'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($sub['uploaded_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- End card -->
        </div><!-- End container-fluid -->
    </main>
</div><!-- End wrapper -->


<?php include 'includes/footer.php'; ?>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script src="js/dataTables.responsive.min.js"></script>
<script src="js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#submissionsTable').DataTable({
            "order": [[8, "desc"]], // Sort by 'Uploaded At' in descending order
            "responsive": true,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "pageLength": 10,
            "columnDefs": [
                { "orderable": false, "targets": [5, 6, 7] } // Disable sorting on 'Evidence', 'Status', 'Feedback' columns
            ]
        });
    });
</script>
</body>
</html>