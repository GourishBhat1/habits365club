

<?php
session_start();
require_once '../connection.php';

// Incharge authentication
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
   VALIDATE INVOICE ID
------------------------------*/
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: payment.php");
    exit();
}

$invoice_id = intval($_GET['id']);

/* -----------------------------
   FETCH INVOICE (SCOPE-LOCKED)
------------------------------*/
$invStmt = $conn->prepare("
    SELECT 
        i.*,
        u.full_name,
        u.phone,
        u.email
    FROM invoices i
    JOIN users u ON i.user_id = u.id
    WHERE i.id = ?
      AND i.created_by_role = 'incharge'
      AND i.created_by_id = ?
");
$invStmt->bind_param("ii", $invoice_id, $incharge_id);
$invStmt->execute();
$invoice = $invStmt->get_result()->fetch_assoc();
$invStmt->close();

if (!$invoice) {
    header("Location: payment.php");
    exit();
}

/* -----------------------------
   FETCH TRANSACTIONS
------------------------------*/
$txnStmt = $conn->prepare("
    SELECT *
    FROM transactions
    WHERE invoice_id = ?
    ORDER BY created_at DESC
");
$txnStmt->bind_param("i", $invoice_id);
$txnStmt->execute();
$transactions = $txnStmt->get_result();
$txnStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main role="main" class="main-content">
<div class="container-fluid">

<h2 class="page-title">Invoice Details</h2>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?>
        </h5>
        <span class="badge <?php echo ($invoice['status'] === 'paid') ? 'badge-success' : 'badge-danger'; ?>">
            <?php echo strtoupper($invoice['status']); ?>
        </span>
    </div>

    <div class="card-body">

        <!-- Meta Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <p><strong>Invoice Date:</strong>
                    <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?>
                </p>
                <p><strong>Billing Cycle:</strong>
                    <?php echo $invoice['billing_cycle']; ?>
                </p>
                <p><strong>Center:</strong>
                    <?php echo htmlspecialchars($invoice['center_name']); ?>
                </p>
            </div>
            <div class="col-md-6">
                <p><strong>Parent Name:</strong>
                    <?php echo htmlspecialchars($invoice['full_name']); ?>
                </p>
                <p><strong>Phone:</strong>
                    <?php echo htmlspecialchars($invoice['phone']); ?>
                </p>
                <p><strong>Email:</strong>
                    <?php echo htmlspecialchars($invoice['email'] ?? '-'); ?>
                </p>
            </div>
        </div>

        <!-- Amount Breakdown -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="border rounded p-3">
                    <p class="mb-1">Base Amount</p>
                    <h5>₹<?php echo number_format($invoice['base_amount'], 2); ?></h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3">
                    <p class="mb-1">Discount</p>
                    <h5>₹<?php echo number_format($invoice['discount_amount'], 2); ?></h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3">
                    <p class="mb-1"><strong>Payable</strong></p>
                    <h4>₹<?php echo number_format($invoice['payable_amount'], 2); ?></h4>
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <h5 class="mb-3">Transactions</h5>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Amount</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($transactions->num_rows > 0): ?>
                    <?php while ($t = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d M Y H:i', strtotime($t['created_at'])); ?></td>
                            <td>
                                <span class="badge <?php echo ($t['type'] === 'credit') ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($t['type']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $t['reason'])); ?></td>
                            <td>₹<?php echo number_format($t['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($t['remark'] ?? '-'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            No transactions found
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="payment.php" class="btn btn-secondary mt-3">
            ← Back to Payments
        </a>

    </div>
</div>

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>