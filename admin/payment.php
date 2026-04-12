<?php
session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();
$conn = $db; // standardise DB usage

/* -----------------------------
   HANDLE PAYMENT SETTINGS SAVE
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee'])) {
    $fee = floatval($_POST['readmission_fee']);

    $stmt = $db->prepare("
        INSERT INTO website_settings (`key`, `value`)
        VALUES ('readmission_fee', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
    ");
    $stmt->bind_param("s", $fee);
    $stmt->execute();
    $stmt->close();
}

/* -----------------------------
   ERASE ALL PAYMENTS (ADMIN)
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['erase_payments'])) {

    // Order matters if there are dependencies
    $db->query("TRUNCATE TABLE cash_ledger");
    $db->query("TRUNCATE TABLE expenses");
    $db->query("TRUNCATE TABLE transactions");
    $db->query("TRUNCATE TABLE invoices");
}

/* -----------------------------
   DELETE SINGLE INVOICE (ADMIN)
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice_id'])) {
    $invoice_id = intval($_POST['delete_invoice_id']);

    // Delete related transactions first
    $stmt = $db->prepare("DELETE FROM transactions WHERE invoice_id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $stmt->close();

    // Delete invoice
    $stmt = $db->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $stmt->close();
}

$feeResult = $db->query("SELECT value FROM website_settings WHERE `key`='readmission_fee'");
$current_fee = ($feeResult && $feeResult->num_rows > 0) 
    ? $feeResult->fetch_assoc()['value'] 
    : '';
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Payments - Habits365Club</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
</head>

<body class="vertical light">
<div class="wrapper">

    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">

            <h2 class="page-title">Payments & Invoices</h2>

<div class="card shadow mb-4">
    <div class="card-header">
        <h5 class="mb-0">Payment Settings</h5>
    </div>
    <div class="card-body">

        <form method="POST" class="form-inline mb-3">
            <label class="mr-3 font-weight-bold">Readmission Fee (₹)</label>
            <input type="number" step="0.01" name="readmission_fee"
                   class="form-control mr-3"
                   value="<?php echo htmlspecialchars($current_fee); ?>" required>
            <button type="submit" name="save_fee" class="btn btn-primary">
                Save Fee
            </button>
        </form>

        <div class="alert alert-warning">
            <strong>Danger Zone:</strong> This will permanently delete all invoices and transactions.
        </div>

        <form method="POST"
              onsubmit="return confirm('This will permanently delete ALL payment data. Continue?');">
            <button type="submit" name="erase_payments" class="btn btn-danger">
                CLEAR DATA
            </button>
        </form>

    </div>
</div>

        <!-- PAGE HEADER -->

        <!-- INVOICES CARD -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0">Invoices</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Status</label>
                        <select id="statusFilter" class="form-control">
                            <option value="">All Status</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="invoiceTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Parent</th>
                                <th>Center</th>
                                <th>Lead Source</th>
                                <th>Base Amount</th>
                                <th>Discount</th>
                                <th>Payable</th>
                                <th>Status</th>
                                <th>Invoice Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "
                                SELECT i.*, u.full_name 
                                FROM invoices i
                                JOIN users u ON i.user_id = u.id
                                ORDER BY i.invoice_date DESC, i.id DESC
                            ";
                            $result = $conn->query($sql);

                            while ($row = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td>
                                        <a href="view-invoice.php?id=<?php echo $row['id']; ?>">
                                            <?php echo htmlspecialchars($row['invoice_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['center_name']); ?></td>
                                    <td>
                                        <?php
                                            if (!empty($row['lead_source'])) {
                                                if (strpos($row['lead_source'], 'sales_') === 0) {
                                                    echo 'Sales';
                                                } else {
                                                    echo ucfirst(str_replace('_', ' ', $row['lead_source']));
                                                }
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </td>
                                    <td>₹<?php echo number_format($row['base_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['discount_amount'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($row['payable_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge 
                                            <?php echo ($row['status'] === 'paid') ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($row['invoice_date'])); ?></td>
                                    <td>
                                        <form method="POST"
                                              onsubmit="return confirm('Delete this invoice and all related transactions? This action cannot be undone.');"
                                              style="display:inline;">
                                            <input type="hidden" name="delete_invoice_id"
                                                   value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TRANSACTIONS CARD -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0">Transactions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="transactionTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice No</th>
                                <th>Parent</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "
                                SELECT t.*, i.invoice_number, u.full_name
                                FROM transactions t
                                JOIN invoices i ON t.invoice_id = i.id
                                JOIN users u ON t.user_id = u.id
                                ORDER BY t.created_at DESC
                            ";
                            $result = $conn->query($sql);

                            while ($row = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td>
                                        <strong>₹<?php echo number_format($row['amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php echo ($row['type'] === 'credit') ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($row['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['reason'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['remark'] ?? '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DATATABLE SCRIPTS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

<script>
$(document).ready(function () {

    var invoiceTable = $('#invoiceTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        order: [[8, 'desc']]
    });

    $('#statusFilter').on('change', function () {
        invoiceTable.column(6).search(this.value).draw();
    });

    $('#transactionTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        order: [[0, 'desc']]
    });

});
</script>

</body>
</html>