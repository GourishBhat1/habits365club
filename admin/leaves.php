<?php
session_start();
require_once '../connection.php';

// Admin auth
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   HANDLE APPROVE
------------------------------*/
if (isset($_GET['approve'])) {

    $id = (int)$_GET['approve'];

    // fetch leave
    $stmt = $db->prepare("SELECT * FROM leaves WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($leave && $leave['status'] === 'pending') {

        $user_id = $leave['user_id'];
        $days = $leave['total_days'];
        $type = $leave['leave_type'];

        // approve leave
        $stmt = $db->prepare("
            UPDATE leaves 
            SET status='approved', approved_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // update balance
        if ($type !== 'emergency') {
            $stmt = $db->prepare("
                UPDATE leave_balance 
                SET total_used = total_used + ?
                WHERE user_id=?
            ");
            $stmt->bind_param("ii", $days, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: leaves.php");
    exit();
}

/* -----------------------------
   HANDLE REJECT
------------------------------*/
if (isset($_GET['reject'])) {

    $id = (int)$_GET['reject'];

    $stmt = $db->prepare("
        UPDATE leaves 
        SET status='rejected', approved_at=NOW()
        WHERE id=?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: leaves.php");
    exit();
}

/* -----------------------------
   FETCH LEAVES
------------------------------*/
$stmt = $db->prepare("
    SELECT l.*, u.full_name 
    FROM leaves l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.id DESC
");
$stmt->execute();
$leaves = $stmt->get_result();
$stmt->close();
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Leave Management</title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Leave Approvals</h2>

<div class="card shadow mb-3">
<div class="card-body">

<form id="filterForm" class="form-row">

<div class="form-group col-md-2">
<label>Role</label>
<select id="filterRole" class="form-control">
    <option value="">All</option>
    <option value="incharge">Incharge</option>
    <option value="teacher">Teacher</option>
</select>
</div>

<div class="form-group col-md-2">
<label>Status</label>
<select id="filterStatus" class="form-control">
    <option value="">All</option>
    <option value="pending">Pending</option>
    <option value="approved">Approved</option>
    <option value="rejected">Rejected</option>
</select>
</div>

<div class="form-group col-md-3">
<label>From Date</label>
<input type="date" id="filterFrom" class="form-control">
</div>

<div class="form-group col-md-3">
<label>To Date</label>
<input type="date" id="filterTo" class="form-control">
</div>

</form>

</div>
</div>

<div class="card shadow">
<div class="card-body">

<table id="leaveTable" class="table table-bordered table-striped">

<thead>
<tr>
<th>Name</th>
<th>Role</th>
<th>Type</th>
<th>From</th>
<th>To</th>
<th>Days</th>
<th>Reason</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($l = $leaves->fetch_assoc()): ?>
<tr>

<td><?= htmlspecialchars($l['full_name']) ?></td>
<td><?= ucfirst($l['role']) ?></td>
<td><?= ucfirst($l['leave_type']) ?></td>
<td><?= $l['from_date'] ?></td>
<td><?= $l['to_date'] ?></td>
<td><?= $l['total_days'] ?></td>
<td><?= htmlspecialchars($l['reason']) ?></td>

<td>
<?php if ($l['status']=='approved'): ?>
<span class="badge badge-success">Approved</span>
<?php elseif ($l['status']=='rejected'): ?>
<span class="badge badge-danger">Rejected</span>
<?php else: ?>
<span class="badge badge-warning">Pending</span>
<?php endif; ?>
</td>

<td>

<?php if ($l['status']=='pending'): ?>
<a href="?approve=<?= $l['id'] ?>" 
   class="btn btn-sm btn-success"
   onclick="return confirm('Approve this leave?')">
   Approve
</a>

<a href="?reject=<?= $l['id'] ?>" 
   class="btn btn-sm btn-danger"
   onclick="return confirm('Reject this leave?')">
   Reject
</a>
<?php else: ?>
<span class="text-muted">No Action</span>
<?php endif; ?>

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

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
$(function(){

    var table = $('#leaveTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['excel', 'csv', 'pdf', 'print'],
        pageLength: 10,
        order: [[0, 'asc']]
    });

    // Role filter
    $('#filterRole').on('change', function() {
        table.column(1).search(this.value).draw();
    });

    // Status filter
    $('#filterStatus').on('change', function() {
        table.column(7).search(this.value).draw();
    });

    // Date range filter
    $.fn.dataTable.ext.search.push(function(settings, data) {
        let from = $('#filterFrom').val();
        let to = $('#filterTo').val();
        let rowDate = data[3]; // from_date column

        if (!from && !to) return true;

        let row = new Date(rowDate);
        if (from && row < new Date(from)) return false;
        if (to && row > new Date(to)) return false;

        return true;
    });

    $('#filterFrom, #filterTo').on('change', function(){
        table.draw();
    });

});
</script>

</body>
</html>