<?php
session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();
$conn = $db;

// Validate invoice ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: payment.php");
    exit();
}


$invoice_id = intval($_GET['id']);

/* -----------------------------
   MARK INVOICE AS PAID
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {

    // Fetch payable amount
    $stmt = $conn->prepare("SELECT payable_amount FROM invoices WHERE id = ? AND status = 'unpaid'");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $stmt->bind_result($payable_amount);
    $stmt->fetch();
    $stmt->close();

    if ($payable_amount > 0) {

        // Insert payment transaction
        $txnStmt = $conn->prepare("
            INSERT INTO transactions (invoice_id, user_id, amount, type, reason, remark)
            VALUES (?, ?, ?, 'credit', 'payment', 'Marked as paid by admin')
        ");
        $txnStmt->bind_param(
            "iid",
            $invoice_id,
            $invoice['user_id'],
            $payable_amount
        );
        $txnStmt->execute();
        $txnStmt->close();

        // Update invoice status
        $updateStmt = $conn->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
        $updateStmt->bind_param("i", $invoice_id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    header("Location: view-invoice.php?id=" . $invoice_id);
    exit();
}

/* -----------------------------
   FETCH INVOICE
------------------------------*/
$invoiceStmt = $conn->prepare("
    SELECT i.*, u.full_name, u.phone, u.email
    FROM invoices i
    JOIN users u ON i.user_id = u.id
    WHERE i.id = ?
");
$invoiceStmt->bind_param("i", $invoice_id);
$invoiceStmt->execute();
$invoice = $invoiceStmt->get_result()->fetch_assoc();
$invoiceStmt->close();

if (!$invoice) {
    header("Location: payment.php");
    exit();
}

/* -----------------------------
   FETCH TRANSACTIONS
------------------------------*/
$txnStmt = $conn->prepare("
    SELECT * FROM transactions
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

<div id="invoiceArea">
    <div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></h5>

        <div class="d-flex align-items-center">
            <span class="badge mr-3 <?php echo ($invoice['status'] === 'paid') ? 'badge-success' : 'badge-danger'; ?>">
                <?php echo strtoupper($invoice['status']); ?>
            </span>

            <button class="btn btn-sm btn-outline-secondary mr-2"
                    onclick="downloadInvoicePDF()">
                Download PDF
            </button>

            <?php if ($invoice['status'] === 'unpaid'): ?>
                <form method="POST" onsubmit="return confirm('Mark this invoice as PAID?');">
                    <button type="submit" name="mark_paid" class="btn btn-success btn-sm">
                        Mark as Paid
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body">

        <!-- Invoice Meta -->
        <div class="row mb-4">
            <div class="col-md-6">
                <p><strong>Invoice Date:</strong> <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></p>
                <p><strong>Billing Cycle:</strong> <?php echo $invoice['billing_cycle']; ?></p>
                <p><strong>Center:</strong> <?php echo htmlspecialchars($invoice['center_name']); ?></p>
                <?php if (!empty($invoice['course_start_date']) || !empty($invoice['course_end_date'])): ?>
                    <p>
                        <strong>Course Duration:</strong><br>
                        <?php if (!empty($invoice['course_start_date'])): ?>
                            From <?php echo date('d M Y', strtotime($invoice['course_start_date'])); ?>
                        <?php endif; ?>
                        <?php if (!empty($invoice['course_end_date'])): ?>
                            to <?php echo date('d M Y', strtotime($invoice['course_end_date'])); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <p><strong>Parent Name:</strong> <?php echo htmlspecialchars($invoice['full_name']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['phone']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice['email'] ?? '-'); ?></p>
            </div>
        </div>

        <!-- Amount Breakdown -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="border p-3 rounded">
                    <p class="mb-1">Base Amount</p>
                    <h5>₹<?php echo number_format($invoice['base_amount'], 2); ?></h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border p-3 rounded">
                    <p class="mb-1">Discount</p>
                    <h5>₹<?php echo number_format($invoice['discount_amount'], 2); ?></h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border p-3 rounded">
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
                                <td><?php echo ucfirst(str_replace('_',' ',$t['reason'])); ?></td>
                                <td>₹<?php echo number_format($t['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($t['remark'] ?? '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No transactions yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    </div>
</div>

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</body>
<script>
function downloadInvoicePDF() {
    const element = document.getElementById('invoiceArea');

    const opt = {
        margin: 10,
        filename: 'Invoice-<?php echo $invoice['invoice_number']; ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 2,
            useCORS: true
        },
        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'portrait'
        }
    };

    html2pdf().set(opt).from(element).save();
}
</script>
</html>