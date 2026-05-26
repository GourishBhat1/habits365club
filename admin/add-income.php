

<?php
session_start();
require_once '../connection.php';

if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   FETCH ADMIN DETAILS
------------------------------*/
$admin_email = $_SESSION['admin_email'] ?? $_COOKIE['admin_email'];

$stmt = $db->prepare("
    SELECT id, full_name, location
    FROM users
    WHERE email = ? AND role = 'admin'
    LIMIT 1
");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Unauthorized");
}

$admin_id = (int)$admin['id'];
$location = $admin['location'] ?? '';

$successMsg = $errorMsg = null;

/* -----------------------------
   HANDLE FORM SUBMIT
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = (float)($_POST['amount'] ?? 0);
    $payment_mode = $_POST['payment_mode'] ?? '';
    $remark = trim($_POST['remark'] ?? '');
    $income_date = $_POST['income_date'] ?? date('Y-m-d');

    if ($amount <= 0) {
        $errorMsg = "Enter valid amount.";
    }

    if (!$errorMsg) {

        /* INSERT INTO ADMIN INCOME */
        $stmt = $db->prepare("
            INSERT INTO admin_income
            (amount, payment_mode, remark, received_by, location, income_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("Prepare failed: " . $db->error);
        }

        $created_at = date('Y-m-d H:i:s');

        $stmt->bind_param(
            "dssisss",
            $amount,
            $payment_mode,
            $remark,
            $admin_id,
            $location,
            $income_date,
            $created_at
        );

        $stmt->execute();
        $stmt->close();

        /* CASH LEDGER ENTRY IF CASH */
        if ($payment_mode === 'Cash') {

            $reason = "Admin Income";

            if (!empty($remark)) {
                $reason .= " - " . substr($remark, 0, 100);
            }

            $stmt = $db->prepare("
                INSERT INTO cash_ledger
                (from_user_id, to_user_id, amount, reason, created_by)
                VALUES (NULL, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "idsi",
                $admin_id,
                $amount,
                $reason,
                $admin_id
            );

            $stmt->execute();
            $stmt->close();
        }

        $successMsg = "Income added successfully.";
    }
}

/* -----------------------------
   DELETE INCOME
-------------------------------*/
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    $stmt = $db->prepare("DELETE FROM admin_income WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: add-income.php");
    exit();
}

/* -----------------------------
   FETCH INCOME ENTRIES
-------------------------------*/
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$mode_filter = $_GET['mode'] ?? '';

$query = "
    SELECT ai.*, u.full_name AS received_by_name
    FROM admin_income ai
    LEFT JOIN users u ON ai.received_by = u.id
    WHERE 1
";
$params = [];
$types = "";

if (!empty($from_date)) {
    $query .= " AND ai.income_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $query .= " AND ai.income_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

if (!empty($mode_filter)) {
    $query .= " AND ai.payment_mode = ?";
    $params[] = $mode_filter;
    $types .= "s";
}

$query .= " ORDER BY ai.created_at DESC";

$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$incomeEntries = $stmt->get_result();
$stmt->close();
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Add Income</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Add Income</h2>

<?php if ($successMsg): ?>
<div class="alert alert-success"><?= $successMsg ?></div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger"><?= $errorMsg ?></div>
<?php endif; ?>

<div class="card shadow">
<div class="card-body">

<form method="POST">

<div class="form-group">
<label>Amount *</label>
<input type="number" step="0.01" name="amount" class="form-control" required>
</div>

<div class="form-group">
<label>Payment Mode *</label>
<select name="payment_mode" class="form-control" required>
<option value="Cash">Cash</option>
<option value="Online">Online</option>
</select>
</div>

<div class="form-group">
<label>Remark</label>
<textarea name="remark" class="form-control"></textarea>
</div>

<div class="form-group">
<label>Date *</label>
<input type="date" name="income_date"
value="<?= date('Y-m-d') ?>"
class="form-control" required>
</div>

<button class="btn btn-success">Save Income</button>

</form>

</div>
</div>

<div class="card shadow mt-4">
<div class="card-body">

<form method="GET" class="form-inline">
<label class="mr-2">From</label>
<input type="date" name="from_date" value="<?= $from_date ?>" class="form-control mr-3">

<label class="mr-2">To</label>
<input type="date" name="to_date" value="<?= $to_date ?>" class="form-control mr-3">

<label class="mr-2">Mode</label>
<select name="mode" class="form-control mr-3">
<option value="">All</option>
<option value="Cash" <?= $mode_filter=='Cash'?'selected':'' ?>>Cash</option>
<option value="Online" <?= $mode_filter=='Online'?'selected':'' ?>>Online</option>
</select>

<button class="btn btn-primary mr-2">Filter</button>
<a href="add-income.php" class="btn btn-secondary">Reset</a>
</form>

</div>
</div>

<div class="card shadow mt-3">
<div class="card-body">

<table id="incomeTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Amount</th>
<th>Mode</th>
<th>Remark</th>
<th>Received By</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php while ($row = $incomeEntries->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row['income_date']) ?></td>
<td>₹<?= number_format($row['amount'], 2) ?></td>
<td><?= htmlspecialchars($row['payment_mode']) ?></td>
<td><?= htmlspecialchars($row['remark']) ?></td>
<td><?= htmlspecialchars($row['received_by_name'] ?? '-') ?></td>
<td>
<a href="?delete_id=<?= $row['id'] ?>"
   class="btn btn-sm btn-danger"
   onclick="return confirm('Delete this income entry?');">
   Delete
</a>
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

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
$(document).ready(function () {
    $('#incomeTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['excel', 'csv', 'pdf', 'print'],
        order: [[0, 'desc']]
    });
});
</script>

</body>
</html>