<?php
session_start();
require_once '../connection.php';

// Incharge auth
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

/* -----------------------------
   FETCH INVOICES
------------------------------*/
$invStmt = $conn->prepare("
    SELECT 
        i.id,
        i.invoice_number,
        i.invoice_date,
        i.payable_amount,
        i.status,
        u.full_name
    FROM invoices i
    JOIN users u ON i.user_id = u.id
    WHERE i.created_by_role = 'incharge'
      AND i.created_by_id = ?
    ORDER BY i.invoice_date DESC, i.id DESC
");
$invStmt->bind_param("i", $incharge_id);
$invStmt->execute();
$invoices = $invStmt->get_result();
$invStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Payments</title>

    <link rel="stylesheet"
          href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main role="main" class="main-content">
<div class="container-fluid">

<h2 class="page-title">Payments</h2>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Invoices Generated</h5>
        <a href="create-payment.php" class="btn btn-sm btn-primary">
            + Create Payment
        </a>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table id="invoiceTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Parent</th>
                        <th>Amount (₹)</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $invoices->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <a href="view-invoice.php?id=<?php echo $row['id']; ?>">
                                <?php echo htmlspecialchars($row['invoice_number']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td>₹<?php echo number_format($row['payable_amount'], 2); ?></td>
                        <td>
                            <span class="badge 
                                <?php echo ($row['status'] === 'paid') ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($row['invoice_date'])); ?></td>
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

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function () {
    $('#invoiceTable').DataTable({
        order: [[4, 'desc']]
    });
});
</script>

</body>
</html>