

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
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Add Income</title>
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

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>