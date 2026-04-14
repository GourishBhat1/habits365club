<?php
session_start();
require_once '../connection.php';

// Admin auth
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   GET INVOICE ID
------------------------------*/
$invoice_id = $_GET['id'] ?? 0;

if (!$invoice_id) {
    die("Invalid Invoice ID");
}

/* -----------------------------
   FETCH INVOICE WITH PARENT DETAILS
------------------------------*/
$stmt = $db->prepare("
    SELECT i.*, u.full_name, u.phone
    FROM invoices i
    JOIN users u ON i.user_id = u.id
    WHERE i.id = ?
");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die("Invoice not found");
}

/* -----------------------------
   FETCH SALES USERS (for lead source)
------------------------------*/
$salesStmt = $db->prepare("SELECT id, full_name FROM users WHERE role='sales'");
$salesStmt->execute();
$salesUsers = $salesStmt->get_result();
$salesStmt->close();

$successMsg = $errorMsg = null;

/* -----------------------------
   HANDLE UPDATE
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = (float)($_POST['amount'] ?? 0);
    $status = $_POST['status'] ?? 'unpaid';
    $course_start = $_POST['course_start_date'] ?? null;
    $course_end = $_POST['course_end_date'] ?? null;
    $lead_source = $_POST['lead_source'] ?? null;

    if ($amount <= 0) {
        $errorMsg = "Invalid amount";
    }

    if (!$errorMsg) {

        $base_amount = $amount;

        $stmt = $db->prepare("
            UPDATE invoices
            SET base_amount = ?,
                payable_amount = ?,
                course_start_date = ?,
                course_end_date = ?,
                lead_source = ?,
                status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ddssssi",
            $base_amount,
            $amount,
            $course_start,
            $course_end,
            $lead_source,
            $status,
            $invoice_id
        );
        $stmt->execute();
        $stmt->close();

        $successMsg = "Payment updated successfully";

        // Refresh
        $stmt = $db->prepare("
            SELECT i.*, u.full_name, u.phone
            FROM invoices i
            JOIN users u ON i.user_id = u.id
            WHERE i.id = ?
        ");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Edit Payment</title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Edit Payment</h2>

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
<label>Invoice Number</label>
<input type="text" class="form-control" 
value="<?= htmlspecialchars($invoice['invoice_number']) ?>" readonly>
</div>

<div class="form-group">
<label>Parent</label>
<input type="text" class="form-control"
value="<?= htmlspecialchars($invoice['full_name'].' ('.$invoice['phone'].')') ?>" readonly>
</div>

<div class="form-group">
<label>Amount *</label>
<input type="number" step="0.01" name="amount"
value="<?= $invoice['payable_amount'] ?>"
class="form-control" required>
</div>

<div class="form-row">
<div class="form-group col-md-6">
<label>Course Start Date</label>
<input type="date" name="course_start_date"
value="<?= $invoice['course_start_date'] ?>"
class="form-control">
</div>

<div class="form-group col-md-6">
<label>Course End Date</label>
<input type="date" name="course_end_date"
value="<?= $invoice['course_end_date'] ?>"
class="form-control">
</div>
</div>

<div class="form-group">
<label>Lead Source</label>
<select name="lead_source" class="form-control">
<option value="">Select</option>

<optgroup label="Sales Team">
<?php while($s = $salesUsers->fetch_assoc()): ?>
<option value="sales_<?= $s['id'] ?>"
<?= $invoice['lead_source']=='sales_'.$s['id']?'selected':'' ?>>
<?= htmlspecialchars($s['full_name']) ?>
</option>
<?php endwhile; ?>
</optgroup>

<optgroup label="Other">
<option value="instagram" <?= $invoice['lead_source']=='instagram'?'selected':'' ?>>Instagram</option>
<option value="referral" <?= $invoice['lead_source']=='referral'?'selected':'' ?>>Referral</option>
<option value="walk_in" <?= $invoice['lead_source']=='walk_in'?'selected':'' ?>>Walk-in</option>
<option value="website" <?= $invoice['lead_source']=='website'?'selected':'' ?>>Website</option>
</optgroup>

</select>
</div>

<div class="form-group">
<label>Status *</label>
<select name="status" class="form-control">
<option value="paid" <?= $invoice['status']=='paid'?'selected':'' ?>>Paid</option>
<option value="unpaid" <?= $invoice['status']=='unpaid'?'selected':'' ?>>Unpaid</option>
</select>
</div>

<button class="btn btn-primary">Update Payment</button>

<a href="payment.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>