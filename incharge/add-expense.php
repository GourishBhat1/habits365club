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

        $description .= " | Payment Mode: " . $payment_mode;

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
        $stmt->close();

        if ($payment_mode === 'Cash') {
            $reason = "Expense: " . ucfirst($category);

            $stmt = $db->prepare("
                INSERT INTO cash_ledger
                (from_user_id, to_user_id, amount, reason, created_by)
                VALUES (?, NULL, ?, ?, ?)
            ");

            $stmt->bind_param(
                "idsi",
                $incharge_id,   // from_user_id
                $amount,
                $reason,
                $incharge_id    // created_by
            );

            $stmt->execute();
            $stmt->close();
        }

        $successMsg = "Expense recorded successfully.";
    }
}
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

        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>