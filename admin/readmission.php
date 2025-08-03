<?php
session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}
require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

// --- Filters ---
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// --- Query ---
$query = "SELECT r.*, u.full_name, u.username, u.created_at AS date_of_joining, u.status AS user_status
          FROM readmissions r
          JOIN users u ON r.user_id = u.id
          WHERE 1";
$params = [];
$types = "";

if ($status) {
    $query .= " AND r.status = ?";
    $params[] = $status;
    $types .= "s";
}
if ($date_from) {
    $query .= " AND r.due_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to) {
    $query .= " AND r.due_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}
$query .= " ORDER BY r.due_date DESC";

$stmt = $db->prepare($query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$readmissions = [];
while ($row = $result->fetch_assoc()) {
    $readmissions[] = $row;
}
$stmt->close();

// --- Analytics ---
$analytics = [
    'done' => 0,
    'pending' => 0,
    'dropped' => 0
];
foreach ($readmissions as $r) {
    $analytics[$r['status']]++;
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Readmissions - Admin</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Readmissions Management</h2>

            <!-- Analytics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="info-card bg-light">
                        <h5>Done</h5>
                        <h3><?php echo $analytics['done']; ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card bg-light">
                        <h5>Pending</h5>
                        <h3><?php echo $analytics['pending']; ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card bg-light">
                        <h5>Dropped</h5>
                        <h3><?php echo $analytics['dropped']; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-header">Filter Readmissions</div>
                <div class="card-body">
                    <form method="GET" class="form-inline">
                        <label for="status" class="mr-2">Status</label>
                        <select name="status" id="status" class="form-control mr-4">
                            <option value="">All</option>
                            <option value="pending" <?php if($status=='pending') echo 'selected'; ?>>Pending</option>
                            <option value="done" <?php if($status=='done') echo 'selected'; ?>>Done</option>
                            <option value="dropped" <?php if($status=='dropped') echo 'selected'; ?>>Dropped</option>
                        </select>
                        <label for="date_from" class="mr-2">From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control mr-2" value="<?php echo htmlspecialchars($date_from); ?>">
                        <label for="date_to" class="mr-2">To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control mr-2" value="<?php echo htmlspecialchars($date_to); ?>">
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
                                <th>Date of Joining</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Remark</th>
                                <th>Marked By</th>
                                <th>Marked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readmissions as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['username']); ?></td>
                                    <td><?php echo !empty($r['date_of_joining']) ? date('d M Y', strtotime($r['date_of_joining'])) : 'N/A'; ?></td>
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
                                    <td><?php echo $r['marked_by'] ? htmlspecialchars($r['marked_by']) : '-'; ?></td>
                                    <td><?php echo $r['marked_at'] ? date('d M Y H:i', strtotime($r['marked_at'])) : '-'; ?></td>
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

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script>
$(document).ready(function() {
    $('#readmissionsTable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'B>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-sm btn-info mr-1',
                title: 'Readmissions',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-success mr-1',
                title: 'Readmissions',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-danger',
                title: 'Readmissions',
                exportOptions: { columns: ':visible' }
            }
        ],
        order: [[3, 'desc']]
    });
});
</script>
</body>
</html>