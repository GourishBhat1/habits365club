<?php
session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   CONFIG
------------------------------*/
$ownerEmails = [
    'prashant@example.com',
    'pallavi@example.com'
];

/* -----------------------------
   HELPER: GET CASH BALANCE
------------------------------*/
function getCashBalance(mysqli $db, int $user_id): float
{
    $stmt = $db->prepare("
        SELECT IFNULL(SUM(
            CASE
                WHEN to_user_id = ? THEN amount
                WHEN from_user_id = ? THEN -amount
                ELSE 0
            END
        ), 0) AS balance
        FROM cash_ledger
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
    $stmt->close();
    return $balance;
}

/* -----------------------------
   HELPER: INCHARGE CASH
------------------------------*/
function getInchargeCashCollected(mysqli $db, int $incharge_id): float
{
    $stmt = $db->prepare("
        SELECT IFNULL(SUM(i.payable_amount), 0) AS total
        FROM invoices i
        INNER JOIN transactions t ON t.invoice_id = i.id
        WHERE i.status = 'paid'
          AND i.created_by_role = 'incharge'
          AND i.created_by_id = ?
          AND t.remark LIKE '%Payment Mode: Cash%'
    ");
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $total = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $total;
}

function getInchargeCashTransferred(mysqli $db, int $incharge_id): float
{
    $stmt = $db->prepare("
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM cash_ledger
        WHERE from_user_id = ?
    ");
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $total = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $total;
}

/* -----------------------------
   FETCH ADMIN
------------------------------*/
$admin_email = $_SESSION['admin_email'] ?? $_COOKIE['admin_email'];

$stmt = $db->prepare("
    SELECT id, full_name, email
    FROM users
    WHERE email = ? AND role = 'admin'
    LIMIT 1
");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) die("Unauthorized");

$admin_id = (int)$admin['id'];

$isOwner = in_array($admin_email, $ownerEmails);

/* -----------------------------
   ERASE ALL DATA BEFORE DATE
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['erase_payments'])) {

    $before_date = $_POST['erase_before_date'] ?? '';
    if (!empty($before_date)) {
        $stmt = $db->prepare("DELETE FROM transactions WHERE created_at < ?");
        $stmt->bind_param("s", $before_date);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM admin_income WHERE created_at < ?");
        $stmt->bind_param("s", $before_date);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM expenses WHERE created_at < ?");
        $stmt->bind_param("s", $before_date);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM cash_ledger WHERE created_at < ?");
        $stmt->bind_param("s", $before_date);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM invoices WHERE invoice_date < ?");
        $stmt->bind_param("s", $before_date);
        $stmt->execute();
        $stmt->close();
    }
}

/* -----------------------------
   USERNAME ADMIN BALANCE
------------------------------*/
$usernameAdminBalance = null;

$stmt = $db->prepare("
    SELECT id FROM users 
    WHERE username = 'admin' AND role = 'admin'
    LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($res) {
    $usernameAdminBalance = getCashBalance($db, (int)$res['id']);
}

/* -----------------------------
   FILTERS (DEFAULT CURRENT MONTH)
------------------------------*/
$default_from = date('Y-m-01');
$default_to   = date('Y-m-t');

$from = $_GET['from'] ?? $default_from;
$to   = $_GET['to'] ?? $default_to;
$selectedCenter = $_GET['center'] ?? '';
$selectedMode = $_GET['mode'] ?? '';

/* FETCH DISTINCT CENTERS */
$centers = [];
$stmt = $db->prepare("
    SELECT DISTINCT location 
    FROM users 
    WHERE role = 'incharge' 
      AND location IS NOT NULL 
      AND location != ''
");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $centers[] = $row['location'];
}
$stmt->close();

/* -----------------------------
   FILTERED CASH FLOW DATA
------------------------------*/
$cashFlowRows = [];
$expenseRows = [];

/* INCOME */
$stmt = $db->prepare("
    SELECT 
        i.created_at AS txn_date,
        u.location,
        i.id AS ref_id,
        i.invoice_number,
        child_u.full_name AS child_name,
        i.payable_amount AS amount,
        t.remark
    FROM invoices i
    INNER JOIN transactions t ON t.invoice_id = i.id
    INNER JOIN users u ON i.created_by_id = u.id
    LEFT JOIN users child_u ON i.user_id = child_u.id
    WHERE i.status = 'paid'
      AND DATE(i.created_at) BETWEEN ? AND ?
      AND (? = '' OR u.location = ?)
      AND (? = '' OR t.remark LIKE CONCAT('%', ?, '%'))
");
$stmt->bind_param("ssssss", $from, $to, $selectedCenter, $selectedCenter, $selectedMode, $selectedMode);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $mode = (stripos($row['remark'], 'cash') !== false) ? 'Cash' : 'Online';
    $invoiceNo = $row['invoice_number'] ?? $row['ref_id'];
    $name = $row['child_name'] ?? '';
    $cashFlowRows[] = [
        'date' => $row['txn_date'],
        'center' => $row['location'],
        'type' => 'Income',
        'mode' => $mode,
        'amount' => $row['amount'],
        'ref' => 'Invoice #' . $invoiceNo,
        'child_name' => $name,
        'desc' => $row['remark']
    ];
}
$stmt->close();

/* ADMIN INCOME */
$stmt = $db->prepare("
    SELECT 
        ai.income_date AS txn_date,
        ai.location,
        ai.id AS ref_id,
        ai.amount,
        ai.remark,
        ai.payment_mode
    FROM admin_income ai
    WHERE DATE(ai.income_date) BETWEEN ? AND ?
      AND (? = '' OR ai.location = ?)
      AND (? = '' OR ai.payment_mode LIKE CONCAT('%', ?, '%'))
");

$stmt->bind_param("ssssss", $from, $to, $selectedCenter, $selectedCenter, $selectedMode, $selectedMode);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $mode = $row['payment_mode'] ?? 'Online';

    $cashFlowRows[] = [
        'date' => $row['txn_date'],
        'center' => $row['location'],
        'type' => 'Admin Income',
        'mode' => $mode,
        'amount' => $row['amount'],
        'ref' => 'Admin Income #' . $row['ref_id'],
        'child_name' => '',
        'desc' => $row['remark']
    ];
}
$stmt->close();

/* EXPENSE */
$stmt = $db->prepare("
    SELECT 
        e.expense_date AS txn_date,
        u.location,
        e.id AS ref_id,
        e.amount,
        e.description
    FROM expenses e
    INNER JOIN users u ON e.recorded_by_id = u.id
    WHERE DATE(e.expense_date) BETWEEN ? AND ?
      AND (? = '' OR u.location = ?)
      AND (? = '' OR e.description LIKE CONCAT('%', ?, '%'))
");
$stmt->bind_param("ssssss", $from, $to, $selectedCenter, $selectedCenter, $selectedMode, $selectedMode);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $mode = (stripos($row['description'], 'cash') !== false) ? 'Cash' : 'Online';
    $expenseRows[] = [
        'date' => $row['txn_date'],
        'center' => $row['location'],
        'mode' => $mode,
        'amount' => $row['amount'],
        'ref' => 'Expense #' . $row['ref_id'],
        'desc' => $row['description']
    ];

    $cashFlowRows[] = [
        'date' => $row['txn_date'],
        'center' => $row['location'],
        'type' => 'Expense',
        'mode' => $mode,
        'amount' => -1 * $row['amount'],
        'ref' => 'Expense #' . $row['ref_id'],
        'child_name' => '',
        'desc' => $row['description']
    ];
}
$stmt->close();

/* SORT */
usort($cashFlowRows, fn($a,$b) => strtotime($b['date']) <=> strtotime($a['date']));

/* CALCULATE TOTALS */
$totalIncome = 0;
$totalExpense = 0;

foreach ($cashFlowRows as $row) {
    if ($row['type'] === 'Income' || $row['type'] === 'Admin Income') {
        $totalIncome += $row['amount'];
    } else {
        $totalExpense += abs($row['amount']);
    }
}
$netProfit = $totalIncome - $totalExpense;

/* CENTER CASH BALANCE */
$centerBalances = [];
foreach ($cashFlowRows as $row) {
    $c = $row['center'] ?: 'Unknown';
    $centerBalances[$c] = ($centerBalances[$c] ?? 0) + $row['amount'];
}
$centerCashLabel = $selectedCenter ?: 'All Centers';
$centerCashValue = $selectedCenter
    ? ($centerBalances[$selectedCenter] ?? 0)
    : array_sum($centerBalances);

/* ADMIN BALANCE */
$myBalance = getCashBalance($db, $admin_id);

/* OWNER BALANCES */
$ownerBalances = [];
if ($isOwner) {
    $placeholders = implode(',', array_fill(0, count($ownerEmails), '?'));
    $types = str_repeat('s', count($ownerEmails));
    $stmt = $db->prepare("
        SELECT id, email FROM users
        WHERE role = 'admin'
        AND email IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$ownerEmails);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ownerBalances[] = [
            'email' => $row['email'],
            'balance' => getCashBalance($db, (int)$row['id'])
        ];
    }
    $stmt->close();
}

/* INCHARGE CASH */
$inchargeCashRows = [];
$stmt = $db->prepare("
    SELECT id, full_name, location
    FROM users
    WHERE role = 'incharge'
      AND status = 'active'
");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $collected = getInchargeCashCollected($db, (int)$row['id']);
    $transferred = getInchargeCashTransferred($db, (int)$row['id']);
    $current = $collected - $transferred;
    if ($collected <= 0 && $transferred <= 0) continue;
    $inchargeCashRows[] = [
        'name'=>$row['full_name'],
        'center'=>$row['location']?:'-',
        'collected'=>$collected,
        'transferred'=>$transferred,
        'current'=>$current
    ];
}
$stmt->close();

/* RECENT LEDGER */
$stmt = $db->prepare("
    SELECT cl.*, 
           u1.full_name AS from_name,
           u2.full_name AS to_name
    FROM cash_ledger cl
    LEFT JOIN users u1 ON cl.from_user_id = u1.id
    LEFT JOIN users u2 ON cl.to_user_id = u2.id
    WHERE cl.from_user_id = ? OR cl.to_user_id = ?
    ORDER BY cl.created_at DESC
");
$stmt->bind_param("ii", $admin_id, $admin_id);
$stmt->execute();
$ledger = $stmt->get_result();
$stmt->close();
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>My Cash</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
</head>

<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">My Cash</h2>

<!-- DANGER ZONE: ERASE -->
<div class="card shadow mb-4 border-danger">
<div class="card-header"><strong class="text-danger">Danger Zone</strong></div>
<div class="card-body">
<div class="alert alert-warning">
    <strong>Warning:</strong> This will permanently delete all invoices, transactions, expenses, admin income, and cash ledger entries before the selected date.
</div>
<form method="POST"
      onsubmit="return confirm('This will delete all data before the selected date. Continue?');">
    <div class="form-group mb-2">
        <label class="font-weight-bold">Delete Data Before Date</label>
        <input type="date" name="erase_before_date" class="form-control" required>
    </div>
    <button type="submit" name="erase_payments" class="btn btn-danger">
        DELETE BEFORE DATE
    </button>
</form>
</div>
</div>

<!-- ADMIN BALANCE -->
<div class="card shadow mb-4">
<div class="card-body">
<h5>My Current Cash Balance</h5>
<h2 class="text-primary">₹<?= number_format($myBalance,2) ?></h2>
</div>
</div>

<div class="card shadow mb-4 border-success">
<div class="card-body">
<h5>Amita Cash Balance</h5>
<h2 class="text-success">₹<?= number_format($usernameAdminBalance,2) ?></h2>
</div>
</div>

<!-- OWNER BALANCES -->
<?php if ($isOwner): ?>
<?php foreach ($ownerBalances as $owner): ?>
<div class="card shadow mb-3 border-success">
<div class="card-body">
<h5><?= ucfirst(explode('@',$owner['email'])[0]) ?> Cash Balance</h5>
<h2 class="text-success">₹<?= number_format($owner['balance'],2) ?></h2>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- INCHARGE CASH TABLE -->
<div class="card shadow mb-4">
<div class="card-header"><strong>Cash Currently With Incharges</strong></div>
<div class="card-body">
<table id="inchargeCashTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Incharge</th>
<th>Center</th>
<th>Cash Collected</th>
<th>Cash Transferred</th>
<th>Current Cash</th>
</tr>
</thead>
<tbody>
<?php foreach ($inchargeCashRows as $r): ?>
<tr>
<td><?= $r['name'] ?></td>
<td><?= $r['center'] ?></td>
<td><?= number_format($r['collected'],2) ?></td>
<td><?= number_format($r['transferred'],2) ?></td>
<td><strong><?= number_format($r['current'],2) ?></strong></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- RECENT LEDGER -->
<div class="card shadow mb-4">
<div class="card-header"><strong>Recent Cash Movements</strong></div>
<div class="card-body">
<table id="recentCashTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>From</th>
<th>To</th>
<th>Amount</th>
<th>Remark</th>
</tr>
</thead>
<tbody>
<?php while ($row = $ledger->fetch_assoc()): ?>
<tr>
<td><?= date('d M Y H:i',strtotime($row['created_at'])) ?></td>
<td><?= $row['from_name']??'-' ?></td>
<td><?= $row['to_name']??'-' ?></td>
<td><?= number_format($row['amount'],2) ?></td>
<td><?= $row['reason']??'-' ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- CASH FLOW (FILTERS + CARDS INSIDE THIS CONTAINER) -->
<div class="card shadow mb-4">
<div class="card-header"><strong>Cash Flow Statement</strong></div>
<div class="card-body">

<!-- FILTER -->
<form method="GET" class="form-row mb-4">
<div class="col-md-3">
<label>Center</label>
<select name="center" class="form-control">
<option value="">All Centers</option>
<?php foreach ($centers as $c): ?>
<option value="<?= $c ?>" <?= $selectedCenter==$c?'selected':'' ?>><?= $c ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<label>From</label>
<input type="date" name="from" value="<?= $from ?>" class="form-control">
</div>

<div class="col-md-3">
<label>To</label>
<input type="date" name="to" value="<?= $to ?>" class="form-control">
</div>

<div class="col-md-2">
<label>Mode</label>
<select name="mode" class="form-control">
<option value="">All</option>
<option value="Cash" <?= $selectedMode=='Cash'?'selected':'' ?>>Cash</option>
<option value="Online" <?= $selectedMode=='Online'?'selected':'' ?>>Online</option>
</select>
</div>

<div class="col-md-2">
<button class="btn btn-primary btn-block">Apply</button>
</div>
</form>

<!-- SUMMARY CARDS -->
<div class="row mb-4">
<div class="col-md-3">
<div class="card border-primary">
<div class="card-body">
<h6>Total Income</h6>
<h4 class="text-primary">₹<?= number_format($totalIncome,2) ?></h4>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card border-danger">
<div class="card-body">
<h6>Total Expense</h6>
<h4 class="text-danger">₹<?= number_format($totalExpense,2) ?></h4>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card border-info">
<div class="card-body">
<h6>Center Cash Balance — <?= htmlspecialchars($centerCashLabel) ?></h6>
<h4 class="text-info">₹<?= number_format($centerCashValue,2) ?></h4>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card <?= $netProfit>=0?'border-success':'border-danger' ?>">
<div class="card-body">
<h6>Net Profit</h6>
<h4 class="<?= $netProfit>=0?'text-success':'text-danger' ?>">
₹<?= number_format($netProfit,2) ?>
</h4>
</div>
</div>
</div>
</div>

<table id="cashFlowTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Center</th>
<th>Type</th>
<th>Mode</th>
<th>Reference</th>
<th>Child Name</th>
<th>Description</th>
<th>Amount</th>
</tr>
</thead>
<tbody>
<?php foreach ($cashFlowRows as $r): ?>
<tr>
<td><?= date('d M Y',strtotime($r['date'])) ?></td>
<td><?= $r['center'] ?></td>
<td><?= $r['type'] ?></td>
<td><?= $r['mode'] ?></td>
<td><?= $r['ref'] ?></td>
<td><?= htmlspecialchars($r['child_name']) ?></td>
<td><?= $r['desc'] ?></td>
<td class="<?= $r['amount']<0?'text-danger':'text-success' ?>">
<?= number_format($r['amount'],2) ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>

<!-- EXPENDITURE FLOW TABLE -->
<div class="card shadow mb-4">
<div class="card-header"><strong>Expenditure Flow</strong></div>
<div class="card-body">

<table id="expenseFlowTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Center</th>
<th>Mode</th>
<th>Reference</th>
<th>Description</th>
<th>Amount</th>
</tr>
</thead>
<tbody>
<?php foreach ($expenseRows as $r): ?>
<tr>
<td><?= date('d M Y',strtotime($r['date'])) ?></td>
<td><?= $r['center'] ?></td>
<td><?= $r['mode'] ?></td>
<td><?= $r['ref'] ?></td>
<td><?= $r['desc'] ?></td>
<td class="text-danger">
<?= number_format($r['amount'],2) ?>
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
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
function numCol(col) {
    return { targets: [col], render: function(d,t) {
        return t==='display' ? d : parseFloat(String(d).replace(/,/g,''))||0;
    }};
}

$(function () {
    $('#inchargeCashTable').DataTable({
        dom:'Bfrtip', buttons:['excel','csv','pdf','print'],
        columnDefs: [numCol(2), numCol(3), numCol(4)]
    });
    $('#recentCashTable').DataTable({
        dom:'Bfrtip', buttons:['excel','csv','pdf','print'],
        columnDefs: [numCol(3)]
    });
    $('#cashFlowTable').DataTable({
        dom:'Bfrtip', buttons:['excel','csv','pdf','print'],
        columnDefs: [numCol(7)]
    });
    $('#expenseFlowTable').DataTable({
        dom:'Bfrtip', buttons:['excel','csv','pdf','print'],
        columnDefs: [numCol(5)]
    });
});
</script>

</body>
</html>