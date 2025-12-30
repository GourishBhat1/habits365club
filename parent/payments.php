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
   FETCH PARENT DETAILS
------------------------------*/
$parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];

$stmt = $db->prepare("
    SELECT id, full_name 
    FROM users 
    WHERE username = ? AND role = 'parent'
    LIMIT 1
");
$stmt->bind_param("s", $parent_username);
$stmt->execute();
$parent = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parent) {
    die("Parent not found.");
}

$parent_id = $parent['id'];

/* -----------------------------
   FETCH INVOICES
------------------------------*/
$invStmt = $db->prepare("
    SELECT 
        id,
        invoice_number,
        invoice_date,
        source,
        base_amount,
        discount_amount,
        payable_amount,
        status
    FROM invoices
    WHERE user_id = ?
    ORDER BY invoice_date DESC
");
$invStmt->bind_param("i", $parent_id);
$invStmt->execute();
$invoices = $invStmt->get_result();
$invStmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>My Payments</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">My Payments</h2>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Invoices</strong>
                </div>
                <div class="card-body">
                    <table id="paymentsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Base Amount</th>
                                <th>Discount</th>
                                <th>Payable</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $invoices->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['invoice_date'])); ?></td>
                                    <td>
                                        <?php
                                            echo ucfirst($row['source'] ?? 'manual');
                                        ?>
                                    </td>
                                    <td>₹<?php echo number_format($row['base_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($row['discount_amount'] > 0): ?>
                                            <span class="text-success">
                                                -₹<?php echo number_format($row['discount_amount'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            ₹0.00
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>₹<?php echo number_format($row['payable_amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-invoice.php?id=<?php echo $row['id']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            View
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

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        order: [[1, 'desc']]
    });
});
</script>
</body>
</html>