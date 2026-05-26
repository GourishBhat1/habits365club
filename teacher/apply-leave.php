<?php
session_start();
require_once '../connection.php';

// Check if the teacher is authenticated via session or cookie
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   FETCH USER
------------------------------*/
$email = $_SESSION['teacher_email'] ?? $_COOKIE['teacher_email'];

$stmt = $db->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$user_id = $user['id'];

/* -----------------------------
   FETCH BALANCE
------------------------------*/
$stmt = $db->prepare("SELECT * FROM leave_balance WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$balance) {
    // initialize
    $stmt = $db->prepare("INSERT INTO leave_balance (user_id, total_earned, total_used) VALUES (?,0,0)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $balance = ['total_earned' => 0, 'total_used' => 0];
}

$available = $balance['total_earned'] - $balance['total_used'];

/* -----------------------------
   HANDLE APPLY
------------------------------*/
$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['leave_type'];
    $from = $_POST['from_date'];
    $to = $_POST['to_date'];
    $reason = trim($_POST['reason']);

    $days = (strtotime($to) - strtotime($from)) / (60*60*24) + 1;

    if ($days <= 0) {
        $error = "Invalid dates";
    } 
    elseif (strtotime($to) < strtotime($from)) {
        $error = "To Date cannot be before From Date";
    }
    else {

        $stmt = $db->prepare("
            INSERT INTO leaves 
            (user_id, role, leave_type, from_date, to_date, total_days, reason)
            VALUES (?, 'teacher', ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("isssis", $user_id, $type, $from, $to, $days, $reason);
        $stmt->execute();
        $stmt->close();

        $success = "Leave applied successfully";
    }
}

/* -----------------------------
   FETCH MY LEAVES
------------------------------*/
$stmt = $db->prepare("
    SELECT * FROM leaves
    WHERE user_id=?
    ORDER BY id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$leaves = $stmt->get_result();
$stmt->close();

?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Apply Leave</title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Leave Management</h2>

<!-- BALANCE CARDS -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card shadow p-3">
            <h5>Earned</h5>
            <h3><?= $balance['total_earned'] ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow p-3">
            <h5>Used</h5>
            <h3><?= $balance['total_used'] ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow p-3">
            <h5>Available</h5>
            <h3><?= $available ?></h3>
        </div>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- APPLY FORM -->
<div class="card shadow mb-4">
<div class="card-body">

<h5>Apply Leave</h5>

<form method="POST">

<div class="form-row">

<div class="form-group col-md-3">
<label>Leave Type</label>
<select name="leave_type" class="form-control" required>
    <option value="">Select</option>
    <option value="casual">Casual</option>
    <option value="sick">Sick</option>
    <option value="emergency">Emergency</option>
</select>
</div>

<div class="form-group col-md-3">
<label>From Date</label>
<input type="date" name="from_date" class="form-control" required>
</div>

<div class="form-group col-md-3">
<label>To Date</label>
<input type="date" name="to_date" class="form-control" required>
</div>

<div class="form-group col-md-3">
<label>Total Days</label>
<input type="text" id="total_days" class="form-control" readonly>
</div>

</div>

<div class="form-group">
<label>Reason</label>
<textarea name="reason" class="form-control" required></textarea>
</div>

<button class="btn btn-primary">Apply Leave</button>

</form>

</div>
</div>

<!-- LEAVE HISTORY -->
<div class="card shadow">
<div class="card-body">

<h5>My Leaves</h5>

<table id="leaveTable" class="table table-bordered">

<thead>
<tr>
<th>Type</th>
<th>From</th>
<th>To</th>
<th>Days</th>
<th>Reason</th>
<th>Status</th>
</tr>
</thead>

<tbody>
<?php while($l = $leaves->fetch_assoc()): ?>
<tr>

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
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(function(){
    $('#leaveTable').DataTable();

    // Auto calculate days
    $('input[name="from_date"], input[name="to_date"]').on('change', function() {
        let from = $('input[name="from_date"]').val();
        let to = $('input[name="to_date"]').val();

        if (from && to) {
            let fromDate = new Date(from);
            let toDate = new Date(to);

            let diffTime = toDate - fromDate;
            let days = (diffTime / (1000 * 60 * 60 * 24)) + 1;

            if (days > 0) {
                $('#total_days').val(days);
            } else {
                $('#total_days').val('Invalid');
            }
        }
    });
});
</script>

</body>
</html>