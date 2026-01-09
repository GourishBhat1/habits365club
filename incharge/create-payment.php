<?php
session_start();
require_once '../connection.php';

// Auth check
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$conn = $db;

/* -----------------------------
   INCHARGE CONTEXT
------------------------------*/
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

$stmt = $conn->prepare("
    SELECT id, location 
    FROM users 
    WHERE username = ? AND role = 'incharge'
");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$incharge = $stmt->get_result()->fetch_assoc();
$stmt->close();

$incharge_id = $incharge['id'];
$location    = $incharge['location'];

/* -----------------------------
   PREFILL FROM READMISSION
------------------------------*/
$prefill_user_id = $_GET['user_id'] ?? '';
$prefill_amount  = $_GET['amount'] ?? '';
$prefill_remark  = $_GET['remark'] ?? '';
$prefill_due_date = $_GET['due_date'] ?? null;
$source = $_GET['source'] ?? null;
$prefill_discount = isset($_GET['discount']) ? floatval($_GET['discount']) : 0;
$prefill_course_start = $_GET['course_start_date'] ?? null;
$prefill_course_end   = $_GET['course_end_date'] ?? null;
$prefill_lead_source = $_GET['lead_source'] ?? null;

$is_from_readmission = (
    !empty($prefill_user_id) &&
    !empty($prefill_amount) &&
    $source === 'readmission' &&
    !empty($prefill_due_date)
);

/* -----------------------------
   FETCH PARENTS (SAME LOCATION)
------------------------------*/
$parentsStmt = $conn->prepare("
    SELECT id, full_name, phone 
    FROM users 
    WHERE role = 'parent' AND location = ?
    ORDER BY full_name
");
$parentsStmt->bind_param("s", $location);
$parentsStmt->execute();
$parents = $parentsStmt->get_result();
$parentsStmt->close();

/* -----------------------------
   FETCH SALES USERS
------------------------------*/
$salesStmt = $conn->prepare("
    SELECT id, full_name 
    FROM users 
    WHERE role = 'sales'
    ORDER BY full_name
");
$salesStmt->execute();
$salesUsers = $salesStmt->get_result();
$salesStmt->close();

/* -----------------------------
   HANDLE FORM SUBMIT
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['readmission_due_date'])) {

        $chkStmt = $conn->prepare("
            SELECT id
            FROM invoices
            WHERE user_id = ?
              AND source = 'readmission'
              AND readmission_due_date = ?
            LIMIT 1
        ");
        $chkStmt->bind_param("is", $_POST['user_id'], $_POST['readmission_due_date']);
        $chkStmt->execute();
        $existing = $chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();

        if ($existing) {
            header("Location: view-invoice.php?id=" . $existing['id']);
            exit();
        }
    }

    $user_id = intval($_POST['user_id']);
    $amount  = floatval($_POST['amount']);
    $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
    $remark  = trim($_POST['remark']);
    $mark_paid = isset($_POST['mark_paid']);

    $course_start_date = !empty($_POST['course_start_date']) ? $_POST['course_start_date'] : null;
    $course_end_date   = !empty($_POST['course_end_date']) ? $_POST['course_end_date'] : null;
    $lead_source = !empty($_POST['lead_source']) ? $_POST['lead_source'] : null;

    $payment_mode = $_POST['payment_mode'] ?? null;
    $tracking_id  = trim($_POST['tracking_id'] ?? '');

    $base_amount = $amount + $discount;

    if ($user_id > 0 && $amount > 0) {

        // Generate invoice number (date-based)
        $today = date('Ymd');
        $seqRes = $conn->query("
            SELECT COUNT(*) AS cnt 
            FROM invoices 
            WHERE invoice_number LIKE 'INV-$today%'
        ");
        $seq = $seqRes->fetch_assoc()['cnt'] + 1;
        $invoice_number = "INV-$today-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // Insert invoice
        $invStmt = $conn->prepare("
            INSERT INTO invoices
            (invoice_number, invoice_date, user_id, center_name,
             base_amount, discount_amount, payable_amount,
             billing_cycle, status, created_by_role, created_by_id,
             source, readmission_due_date, course_start_date, course_end_date, lead_source)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 0, 'unpaid', 'incharge', ?, ?, ?, ?, ?, ?)
        ");
        $invStmt->bind_param(
            "sisdddisssss",
            $invoice_number,
            $user_id,
            $location,
            $base_amount,
            $discount,
            $amount,
            $incharge_id,
            $source,
            $prefill_due_date,
            $course_start_date,
            $course_end_date,
            $lead_source
        );
        $invStmt->execute();
        $invoice_id = $invStmt->insert_id;
        $invStmt->close();

        // Mark as paid if selected
        if ($mark_paid) {
            $finalRemark = $remark;

            if ($payment_mode) {
                $finalRemark .= "\nPayment Mode: " . ucfirst($payment_mode);
            }
            if ($payment_mode === 'online' && !empty($tracking_id)) {
                $finalRemark .= "\nTracking ID: " . $tracking_id;
            }

            $txnStmt = $conn->prepare("
                INSERT INTO transactions
                (invoice_id, user_id, amount, type, reason, remark)
                VALUES (?, ?, ?, 'credit', 'payment', ?)
            ");
            $txnStmt->bind_param("iids", $invoice_id, $user_id, $amount, $finalRemark);
            $txnStmt->execute();
            $txnStmt->close();

            $conn->query("UPDATE invoices SET status = 'paid' WHERE id = $invoice_id");
        }

        header("Location: view-invoice.php?id=" . $invoice_id);
        exit();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Create Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main role="main" class="main-content">
<div class="container-fluid">

<h2 class="page-title">Create Payment</h2>

<div class="card shadow">
<div class="card-body">

<form method="POST">

    <div class="form-group">
        <label>Parent</label>
        <select name="user_id" class="form-control select2" required
            <?php echo $is_from_readmission ? 'disabled' : ''; ?>>
            <option value="">Select Parent</option>
            <?php while ($p = $parents->fetch_assoc()): ?>
                <option value="<?php echo $p['id']; ?>"
                    <?php echo ($p['id'] == $prefill_user_id) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['full_name'] . ' (' . $p['phone'] . ')'); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <?php if ($is_from_readmission): ?>
            <input type="hidden" name="user_id" value="<?php echo $prefill_user_id; ?>">
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>Amount (₹)</label>
        <input type="number" step="0.01" name="amount"
               class="form-control"
               value="<?php echo htmlspecialchars($prefill_amount); ?>"
               required>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label>Course Start Date</label>
            <input type="date" name="course_start_date"
                   class="form-control"
                   value="<?php echo htmlspecialchars($prefill_course_start); ?>">
        </div>

        <div class="form-group col-md-6">
            <label>Course End Date</label>
            <input type="date" name="course_end_date"
                   class="form-control"
                   value="<?php echo htmlspecialchars($prefill_course_end); ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Lead Source</label>
        <select name="lead_source" class="form-control">
            <option value="">Select Lead Source</option>

            <optgroup label="Sales Team">
                <?php while ($s = $salesUsers->fetch_assoc()): ?>
                    <option value="sales_<?php echo $s['id']; ?>"
                        <?php echo ($prefill_lead_source === 'sales_' . $s['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['full_name']); ?>
                    </option>
                <?php endwhile; ?>
            </optgroup>

            <optgroup label="Other Sources">
                <option value="instagram">Instagram</option>
                <option value="referral">Referral</option>
                <option value="walk_in">Walk-in</option>
                <option value="website">Website</option>
                <option value="other">Other</option>
            </optgroup>
        </select>
    </div>

    <div class="form-group">
        <label>Remark</label>
        <textarea name="remark" class="form-control"><?php
            echo htmlspecialchars($prefill_remark);
        ?></textarea>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" name="mark_paid" class="form-check-input" id="markPaid">
        <label class="form-check-label" for="markPaid">
            Payment received now (mark as paid)
        </label>
    </div>

    <div id="paymentDetails" style="display:none;">
        <div class="form-group">
            <label>Payment Mode</label>
            <select name="payment_mode" class="form-control">
                <option value="cash">Cash</option>
                <option value="online">Online</option>
            </select>
        </div>

        <div class="form-group" id="trackingGroup" style="display:none;">
            <label>Online Transaction / Tracking ID</label>
            <input type="text" name="tracking_id" class="form-control"
                   placeholder="UPI / Bank Ref / Gateway ID">
        </div>
    </div>

    <?php if ($is_from_readmission): ?>
        <input type="hidden" name="readmission_due_date"
               value="<?php echo htmlspecialchars($prefill_due_date); ?>">
        <input type="hidden" name="discount"
               value="<?php echo htmlspecialchars($prefill_discount); ?>">
    <?php endif; ?>

    <button type="submit" class="btn btn-primary">
        Generate Invoice
    </button>

</form>

</div>
</div>

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
<script>
$('.select2').select2();

$('#markPaid').on('change', function () {
    $('#paymentDetails').toggle(this.checked);
});

$('select[name="payment_mode"]').on('change', function () {
    if ($(this).val() === 'online') {
        $('#trackingGroup').show();
    } else {
        $('#trackingGroup').hide();
    }
});
</script>
</body>
</html>