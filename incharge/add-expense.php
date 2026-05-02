<?php
session_start();
require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

/* ---------------------------------
   FETCH INCHARGE DETAILS
----------------------------------*/
$inchargeUsername = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

$stmt = $db->prepare("
    SELECT id, full_name
    FROM users
    WHERE username = ?
      AND role = 'incharge'
");
$stmt->bind_param("s", $inchargeUsername);
$stmt->execute();
$incharge = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$incharge) {
    die("Invalid incharge");
}

$incharge_id = (int)$incharge['id'];
$successMsg = $errorMsg = null;

/* ---------------------------------
   HELPER: CURRENT CASH WITH INCHARGE
----------------------------------*/
function getCurrentCash(mysqli $db, int $incharge_id): float
{
    // Cash collected
    $stmt = $db->prepare("
        SELECT IFNULL(SUM(i.payable_amount), 0) AS total
        FROM invoices i
        INNER JOIN transactions t ON t.invoice_id = i.id
        WHERE i.status = 'paid'
          AND i.created_by_role = 'incharge'
          AND i.created_by_id = ?
          AND t.remark LIKE '%Payment Mode: Cash%'
    ");
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $collected = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Cash transferred
    $stmt = $db->prepare("
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM cash_ledger
        WHERE from_user_id = ?
          AND reason LIKE 'Expense:%'
    ");
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $transferred = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $collected - $transferred;
}

/* ---------------------------------
   HANDLE FORM SUBMIT
----------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $category = $_POST['category'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $payment_mode = $_POST['payment_mode'] ?? '';

    if ($amount <= 0) {
        $errorMsg = "Amount must be greater than zero.";
    }

    $allowed_categories = ['salary','stationery','rent','electricity','maintenance','other'];
    if (!in_array($category, $allowed_categories)) {
        $errorMsg = "Invalid category.";
    }

    if ($amount > 1000000) {
        $errorMsg = "Amount too large.";
    }

    if (empty($payment_mode) || !in_array($payment_mode, ['Cash', 'Online'])) {
        $errorMsg = "Please select a valid payment mode.";
    }

    if (!$errorMsg && $payment_mode === 'Cash') {
        $currentCash = getCurrentCash($db, $incharge_id);
        if ($amount > $currentCash) {
            $errorMsg = "Insufficient cash. Available cash: ₹" . number_format($currentCash, 2);
        }
    }

    if (!$errorMsg) {

        $description = trim($description);
        $description .= " | Mode: " . $payment_mode;

        $stmt = $db->prepare("
            INSERT INTO expenses
            (recorded_by_role, recorded_by_id, amount, category, description, expense_date)
            VALUES ('incharge', ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("Prepare failed: " . $db->error);
        }

        $stmt->bind_param(
            "idsss",
            $incharge_id,   // recorded_by_id
            $amount,
            $category,
            $description,
            $expense_date
        );

        if (!$stmt->execute()) {
            echo "<pre style='background:#300;color:#fff;padding:10px;'>";
            echo "EXPENSE INSERT ERROR:\n";
            echo $stmt->error;
            echo "\nDB Error: " . $db->error;
            echo "</pre>";
            exit;
        }

        $expense_id = $stmt->insert_id;

        $stmt->close();

        /* CASH LEDGER ENTRY (ALWAYS) */

        $reason = "Expense: " . ucfirst($category) . " | " . $description;
        $reference = (string)$expense_id;

        $stmt = $db->prepare("
            INSERT INTO cash_ledger
            (from_user_id, to_user_id, amount, type, reason, reference, created_by)
            VALUES (?, NULL, ?, 'transfer', ?, ?, ?)
        ");

        if (!$stmt) {
            die("Ledger prepare failed: " . $db->error);
        }

        $ledger_amount = abs($amount);

        $stmt->bind_param(
            "idsis",
            $incharge_id,
            $ledger_amount,
            $reason,
            $reference,
            $incharge_id
        );

        $stmt->execute();
        $stmt->close();

        $successMsg = "Expense recorded successfully.";
    }
}

/* ---------------------------------
   FILTERS + FETCH EXPENSES
----------------------------------*/
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$category_filter = $_GET['category'] ?? '';

$query = "SELECT * FROM expenses WHERE recorded_by_role='incharge' AND recorded_by_id=?";
$params = [$incharge_id];
$types = "i";

if (!empty($from_date)) {
    $query .= " AND expense_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $query .= " AND expense_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

$query .= " ORDER BY expense_date DESC, id DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$expenses = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Add Expense</title>
</head>

<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">

            <h2 class="page-title">Add Expense</h2>

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?php echo $successMsg; ?></div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body">

                    <form method="POST">

                        <div class="form-group">
                            <label>Amount (₹)</label>
                            <input type="number" step="0.01" name="amount"
                                   class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control" required>
                                <option value="salary">Salary</option>
                                <option value="stationery">Stationery</option>
                                <option value="rent">Rent</option>
                                <option value="electricity">Electricity</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Payment Mode</label>
                            <select name="payment_mode" class="form-control" required>
                                <option value="Cash">Cash</option>
                                <option value="Online">Online</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control"
                                      placeholder="Include details about the expense"
                                      required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Expense Date</label>
                            <input type="date" name="expense_date"
                                   class="form-control" required>
                        </div>

                        <button class="btn btn-danger">
                            Record Expense
                        </button>

                    </form>

                </div>
            </div>

            <div class="card shadow mt-4">
                <div class="card-body">

                    <form method="GET" class="form-inline mb-3">

                        <label class="mr-2">From</label>
                        <input type="date" name="from_date" value="<?= $from_date ?>" class="form-control mr-3">

                        <label class="mr-2">To</label>
                        <input type="date" name="to_date" value="<?= $to_date ?>" class="form-control mr-3">

                        <label class="mr-2">Category</label>
                        <select name="category" class="form-control mr-3">
                            <option value="">All</option>
                            <option value="salary" <?= $category_filter=='salary'?'selected':'' ?>>Salary</option>
                            <option value="stationery" <?= $category_filter=='stationery'?'selected':'' ?>>Stationery</option>
                            <option value="rent" <?= $category_filter=='rent'?'selected':'' ?>>Rent</option>
                            <option value="electricity" <?= $category_filter=='electricity'?'selected':'' ?>>Electricity</option>
                            <option value="maintenance" <?= $category_filter=='maintenance'?'selected':'' ?>>Maintenance</option>
                            <option value="other" <?= $category_filter=='other'?'selected':'' ?>>Other</option>
                        </select>

                        <button class="btn btn-primary mr-2">Filter</button>
                        <a href="add-expense.php" class="btn btn-secondary">Reset</a>

                    </form>

                    <table id="expenseTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $expenses->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['expense_date']) ?></td>
                                <td>₹<?= number_format($row['amount'],2) ?></td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
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
<script>
$(document).ready(function() {
    $('#expenseTable').DataTable({
        order: [[0,'desc']]
    });
});
</script>
</body>
</html>