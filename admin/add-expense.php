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
    $category = trim($_POST['category'] ?? '');
    $payment_mode = $_POST['payment_mode'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');

    if ($amount <= 0) {
        $errorMsg = "Enter valid amount.";
    }

    // CATEGORY VALIDATION
    $allowed_categories = ['salary','stationery','rent','electricity','maintenance','other'];
    if (!in_array($category, $allowed_categories)) {
        $errorMsg = "Invalid category selected.";
    }

    // AMOUNT SANITY CHECK
    if ($amount > 1000000) {
        $errorMsg = "Amount too large.";
    }

    if (!$errorMsg) {

        // Improve description with payment mode
        $description = trim($description);
        $description = ($description ? $description . " | " : "") . "Mode: " . $payment_mode;

        /* INSERT INTO EXPENSES */
        $stmt = $db->prepare("
            INSERT INTO expenses
            (recorded_by_role, recorded_by_id, amount, category, description, expense_date)
            VALUES ('admin', ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("Prepare failed: " . $db->error);
        }

        $stmt->bind_param(
            "idsss",
            $admin_id,
            $amount,
            $category,
            $description,
            $expense_date
        );

        $stmt->execute();
        $expense_id = $stmt->insert_id;
        $stmt->close();

        /* CASH LEDGER ENTRY */
        $reason = "Admin Expense - " . ucfirst($category) . ($description ? " | " . $description : "");

        // Always insert into ledger (both cash + online for consistency)
        $stmt = $db->prepare("
            INSERT INTO cash_ledger
            (from_user_id, to_user_id, amount, type, reason, created_by, reference)
            VALUES (?, NULL, ?, 'transfer', ?, ?, ?)
        ");

        if (!$stmt) {
            die("Ledger prepare failed: " . $db->error);
        }

        // Store positive amount, direction handled via from_user_id
        $ledger_amount = abs($amount);

        $stmt->bind_param(
            "idsis",
            $admin_id,
            $ledger_amount,
            $reason,
            $admin_id,
            $expense_id
        );

        $stmt->execute();
        $stmt->close();

        $successMsg = "Expense added successfully.";
    }
}

/* -----------------------------
   DELETE EXPENSE
------------------------------*/
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Delete from expenses
    $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    // Delete corresponding ledger entry using reference
    $stmt = $db->prepare("DELETE FROM cash_ledger WHERE reference = ?");
    $ref = (string)$delete_id;
    $stmt->bind_param("s", $ref);
    $stmt->execute();
    $stmt->close();

    header("Location: add-expense.php");
    exit();
}

/* -----------------------------
   FILTERS
------------------------------*/
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$category_filter = $_GET['category'] ?? '';

$query = "SELECT e.*, u.full_name, e.created_at
          FROM expenses e 
          LEFT JOIN users u ON e.recorded_by_id = u.id
          WHERE 1";

$params = [];
$types = "";

if ($from_date) {
    $query .= " AND e.expense_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if ($to_date) {
    $query .= " AND e.expense_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

if ($category_filter) {
    $query .= " AND e.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

$query .= " ORDER BY e.expense_date DESC, e.id DESC";

$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$expenses = $stmt->get_result();
$stmt->close();
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Add Expense</title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Add Expense</h2>

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
<label>Category</label>
<select name="category" class="form-control">
<option value="salary">Salary</option>
<option value="stationery">Stationery</option>
<option value="rent">Rent</option>
<option value="electricity">Electricity</option>
<option value="maintenance">Maintenance</option>
<option value="other">Other</option>
</select>
</div>

<div class="form-group">
<label>Payment Mode *</label>
<select name="payment_mode" class="form-control" required>
<option value="Cash">Cash</option>
<option value="Online">Online</option>
</select>
</div>

<div class="form-group">
<label>Description</label>
<textarea name="description" class="form-control"></textarea>
</div>

<div class="form-group">
<label>Date *</label>
<input type="date" name="expense_date"
value="<?= date('Y-m-d') ?>"
class="form-control" required>
</div>

<button class="btn btn-primary">Save Expense</button>

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

<label class="mr-2">Category</label>
<select name="category" class="form-control mr-3">
<option value="">All</option>
<option value="salary" <?= $category_filter=='salary'?'selected':'' ?>>Salary</option>
<option value="stationery" <?= $category_filter=='stationery'?'selected':'' ?>>Stationery</option>
<option value="rent" <?= $category_filter=='rent'?'selected':'' ?>>Rent</option>
<option value="electricity" <?= $category_filter=='electricity'?'selected':'' ?>>Electricity</option>
<option value="maintenance" <?= $category_filter=='maintenance'?'selected':'' ?>>Maintenance</option>
<option value="other" <?= $category_filter=='other'?'selected':'' ?>>Other</option>
</select>

<button class="btn btn-primary mr-2">Filter</button>
<a href="add-expense.php" class="btn btn-secondary">Reset</a>

</form>

</div>
</div>

<div class="card shadow mt-3">
<div class="card-body">

<table id="expenseTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Amount</th>
<th>Category</th>
<th>Description</th>
<th>Recorded By</th>
<th>Created At</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php while($row = $expenses->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row['expense_date']) ?></td>
<td>₹<?= number_format($row['amount'],2) ?></td>
<td><?= htmlspecialchars($row['category']) ?></td>
<td><?= htmlspecialchars($row['description']) ?></td>
<td><?= htmlspecialchars($row['full_name'] ?? '-') ?></td>
<td><?= isset($row['created_at']) ? date('Y-m-d H:i', strtotime($row['created_at'])) : '-' ?></td>
<td>
<a href="add-expense.php?delete_id=<?= $row['id'] ?>" 
   class="btn btn-sm btn-danger"
   onclick="return confirm('Delete this expense? This will also remove ledger entry.');">
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

<script>
$(document).ready(function() {
    $('#expenseTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['excel','csv','pdf','print'],
        order: [[0,'desc']]
    });
});
</script>

</body>
</html>
