<?php
session_start();
if (!isset($_SESSION['parent_username']) && !isset($_COOKIE['parent_username'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   FETCH PARENT
------------------------------*/
$parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];

$pStmt = $db->prepare("
    SELECT id, full_name 
    FROM users 
    WHERE username = ? AND role = 'parent'
    LIMIT 1
");
$pStmt->bind_param("s", $parent_username);
$pStmt->execute();
$parent = $pStmt->get_result()->fetch_assoc();
$pStmt->close();

if (!$parent) {
    die("Unauthorized access.");
}

$parent_id = $parent['id'];

/* -----------------------------
   VALIDATE INVOICE ID
------------------------------*/
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid invoice.");
}

$invoice_id = intval($_GET['id']);

/* -----------------------------
   FETCH INVOICE (SECURE)
------------------------------*/
$invStmt = $db->prepare("
    SELECT *
    FROM invoices
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$invStmt->bind_param("ii", $invoice_id, $parent_id);
$invStmt->execute();
$invoice = $invStmt->get_result()->fetch_assoc();
$invStmt->close();

if (!$invoice) {
    die("Invoice not found.");
}
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
                <div class="card-header">
                    <strong>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                    <span class="float-right badge badge-<?php echo $invoice['status'] === 'paid' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($invoice['status']); ?>
                    </span>
                </div>

                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Date:</strong>
                                <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?>
                            </p>
                            <p><strong>Invoice Type:</strong>
                                <?php echo ucfirst($invoice['source'] ?? 'manual'); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Billed To:</strong><br>
                                <?php echo htmlspecialchars($parent['full_name']); ?>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <h5>Amount Breakdown</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th>Base Amount</th>
                            <td>₹<?php echo number_format($invoice['base_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Habit Discount</th>
                            <td>
                                <?php if ($invoice['discount_amount'] > 0): ?>
                                    <span class="text-success">
                                        -₹<?php echo number_format($invoice['discount_amount'], 2); ?>
                                    </span>
                                <?php else: ?>
                                    ₹0.00
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><strong>Payable Amount</strong></th>
                            <td><strong>₹<?php echo number_format($invoice['payable_amount'], 2); ?></strong></td>
                        </tr>
                    </table>

                    <?php if (!empty($invoice['remark'])): ?>
                        <p><strong>Remarks:</strong><br>
                            <?php echo nl2br(htmlspecialchars($invoice['remark'])); ?>
                        </p>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="payments.php" class="btn btn-secondary">
                            ← Back to Payments
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>