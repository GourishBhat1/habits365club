<?php
session_start();
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php");
    exit();
}
require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

// Get incharge ID
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

// Handle marking readmission as done/dropped
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['due_date'])) {
    $status = $_POST['status'];
    $remark = $_POST['remark'] ?? '';
    $user_id = $_POST['user_id'];
    $due_date = $_POST['due_date'];
    $marked_by = $incharge_id;
    $marked_at = date('Y-m-d H:i:s');

    // Insert or update readmission record
    $stmt = $db->prepare("SELECT id FROM readmissions WHERE user_id = ? AND due_date = ?");
    $stmt->bind_param("is", $user_id, $due_date);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $stmt = $db->prepare("UPDATE readmissions SET status = ?, remark = ?, marked_by = ?, marked_at = ? WHERE user_id = ? AND due_date = ?");
        $stmt->bind_param("ssisss", $status, $remark, $marked_by, $marked_at, $user_id, $due_date);
    } else {
        $stmt->close();
        $stmt = $db->prepare("INSERT INTO readmissions (user_id, due_date, status, remark, marked_by, marked_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $user_id, $due_date, $status, $remark, $marked_by, $marked_at);
    }
    $stmt->execute();
    $stmt->close();
    $success = "Readmission updated successfully!";
}

// Fetch batches for filter
$batches = [];
$batchStmt = $db->prepare("SELECT id, name FROM batches WHERE incharge_id = ?");
$batchStmt->bind_param("i", $incharge_id);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();
while ($row = $batchResult->fetch_assoc()) {
    $batches[] = $row;
}
$batchStmt->close();

$selectedBatch = $_GET['batch_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Fetch all active students in incharge's batches
$query = "
    SELECT u.id, u.full_name, u.username, u.created_at AS date_of_joining, b.name AS batch_name
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    WHERE b.incharge_id = ?
      AND u.status = 'active'
";
$params = [$incharge_id];
$types = "i";
if (!empty($selectedBatch)) {
    $query .= " AND b.id = ?";
    $params[] = $selectedBatch;
    $types .= "i";
}
$query .= " ORDER BY u.full_name ASC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// For each student, calculate this month's readmission due date and fetch readmission status
$readmissions = [];
foreach ($students as $student) {
    $joinDate = new DateTime($student['date_of_joining']);
    $today = new DateTime();
    $monthStartObj = new DateTime($monthStart);

    // Calculate which readmission (first, second, etc.) this month is
    $monthsSinceJoin = $joinDate->diff($monthStartObj)->m + ($joinDate->diff($monthStartObj)->y * 12) + 1;
    $readmissionNumber = $monthsSinceJoin;

    // Calculate this month's readmission due date (4 days before monthly anniversary)
    $dueDateObj = (clone $joinDate)->modify('+' . $readmissionNumber . ' month')->modify('-4 days');
    $dueDate = $dueDateObj->format('Y-m-d');

    // Only show if due date is in this month
    if ($dueDate >= $monthStart && $dueDate <= $monthEnd) {
        // Fetch readmission record if exists
        $stmt = $db->prepare("SELECT * FROM readmissions WHERE user_id = ? AND due_date = ?");
        $stmt->bind_param("is", $student['id'], $dueDate);
        $stmt->execute();
        $readmissionResult = $stmt->get_result();
        $readmission = $readmissionResult->fetch_assoc();
        $stmt->close();

        $readmissions[] = [
            'user_id' => $student['id'],
            'full_name' => $student['full_name'],
            'username' => $student['username'],
            'batch_name' => $student['batch_name'],
            'date_of_joining' => $student['date_of_joining'],
            'due_date' => $dueDate,
            'readmission_number' => $readmissionNumber,
            'status' => $readmission['status'] ?? 'pending',
            'remark' => $readmission['remark'] ?? '',
        ];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Readmissions - Incharge</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Readmissions Due (<?php echo date('F Y'); ?>)</h2>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-header">Filter</div>
                <div class="card-body">
                    <form method="GET" class="form-inline">
                        <label for="batch_id" class="mr-2">Batch</label>
                        <select name="batch_id" id="batch_id" class="form-control mr-4">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php if($selectedBatch==$b['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    </form>
                </div>
            </div>
            <!-- Readmissions Table -->
            <div class="card shadow">
                <div class="card-header"><strong>Readmissions List</strong></div>
                <div class="card-body">
                    <table id="readmissionsTable" class="table table-striped table-bordered datatable" style="width:100%">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Batch</th>
                                <th>Date of Joining</th>
                                <th>Readmission No.</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Remark</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readmissions as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['username']); ?></td>
                                    <td><?php echo htmlspecialchars($r['batch_name']); ?></td>
                                    <td><?php echo !empty($r['date_of_joining']) ? date('d M Y', strtotime($r['date_of_joining'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php
                                            $suffix = 'th';
                                            if ($r['readmission_number'] == 1) $suffix = 'st';
                                            elseif ($r['readmission_number'] == 2) $suffix = 'nd';
                                            elseif ($r['readmission_number'] == 3) $suffix = 'rd';
                                            echo $r['readmission_number'] . $suffix;
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['due_date']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php
                                            if ($r['status'] == 'done') echo 'success';
                                            elseif ($r['status'] == 'pending') echo 'warning';
                                            else echo 'danger';
                                        ?>">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['remark']); ?></td>
                                    <td>
                                        <?php if ($r['status'] == 'pending'): ?>
                                            <form method="POST" class="form-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $r['user_id']; ?>">
                                                <input type="hidden" name="due_date" value="<?php echo $r['due_date']; ?>">
                                                <select name="status" class="form-control form-control-sm mr-2" required>
                                                    <option value="done">Done</option>
                                                    <option value="dropped">Dropped</option>
                                                </select>
                                                <input type="text" name="remark" class="form-control form-control-sm mr-2" placeholder="Remark" maxlength="255">
                                                <button type="submit" class="btn btn-success btn-sm">Update</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#readmissionsTable').DataTable({
        order: [[5, 'asc']]
    });
});
</script>
</body>
</html>