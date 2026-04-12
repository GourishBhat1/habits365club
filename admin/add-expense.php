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

    if (!$errorMsg) {

        // Append mode in description (for filtering consistency)
        $description .= " | Payment Mode: " . $payment_mode;

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
        $stmt->close();

        /* CASH LEDGER ENTRY IF CASH */
        if ($payment_mode === 'Cash') {

            $reason = "Admin Expense - " . ucfirst($category);

            $stmt = $db->prepare("
                INSERT INTO cash_ledger
                (from_user_id, to_user_id, amount, reason, created_by)
                VALUES (?, NULL, ?, ?, ?)
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

        $successMsg = "Expense added successfully.";
    }
}
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

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
