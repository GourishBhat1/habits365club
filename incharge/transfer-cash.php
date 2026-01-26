<?php
session_start();
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   HELPER: GET CASH BALANCE
------------------------------*/
function getCashBalance(mysqli $db, int $user_id): float
{
    $stmt = $db->prepare("
        SELECT IFNULL(SUM(
            CASE
                WHEN to_user_id = ? THEN amount
                WHEN from_user_id = ? THEN -amount
                ELSE 0
            END
        ), 0) AS balance
        FROM cash_ledger
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
    $stmt->close();

    return $balance;
}

/* -----------------------------
   FETCH LOGGED-IN INCHARGE
------------------------------*/
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

$stmt = $db->prepare("
    SELECT id, full_name
    FROM users
    WHERE username = ? AND role = 'incharge'
    LIMIT 1
");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$incharge = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$incharge) {
    die("Unauthorized access.");
}

$incharge_id = (int)$incharge['id'];

/* -----------------------------
   FETCH ADMINS
------------------------------*/
$admins = [];
$stmt = $db->prepare("
    SELECT id, full_name
    FROM users
    WHERE role = 'admin'
    ORDER BY full_name
");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $admins[] = $row;
}
$stmt->close();

/* -----------------------------
   LEDGER CASH BALANCE (AUTHORITATIVE)
------------------------------*/
$ledgerBalance = getCashBalance($db, $incharge_id);

/* -----------------------------
   EXPECTED CASH FROM PAID CASH INVOICES
------------------------------*/
$stmt = $db->prepare("
    SELECT IFNULL(SUM(i.payable_amount), 0) AS cash_total
    FROM transactions t
    INNER JOIN invoices i ON i.id = t.invoice_id
    WHERE i.status = 'paid'
      AND i.created_by_role = 'incharge'
      AND i.created_by_id = ?
      AND t.remark LIKE '%Payment Mode: Cash%'
");
$stmt->bind_param("i", $incharge_id);
$stmt->execute();
$cashFromInvoices = (float)$stmt->get_result()->fetch_assoc()['cash_total'];
$stmt->close();

/* -----------------------------
   CASH ALREADY TRANSFERRED
------------------------------*/
$stmt = $db->prepare("
    SELECT IFNULL(SUM(amount), 0) AS transferred
    FROM cash_ledger
    WHERE from_user_id = ?
");
$stmt->bind_param("i", $incharge_id);
$stmt->execute();
$cashTransferred = (float)$stmt->get_result()->fetch_assoc()['transferred'];
$stmt->close();

/* -----------------------------
   EXPECTED CASH WITH INCHARGE
------------------------------*/
$expectedCash = $cashFromInvoices - $cashTransferred;
if ($expectedCash < 0) {
    $expectedCash = 0;
}

/* -----------------------------
   HANDLE TRANSFER
------------------------------*/
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_admin_id = intval($_POST['to_admin_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');

    if ($to_admin_id <= 0 || $amount <= 0) {
        $error = "Invalid transfer details.";
    } elseif ($amount > $expectedCash) {
        $error = "Insufficient expected cash balance.";
    } else {
        $stmt = $db->prepare("
            INSERT INTO cash_ledger
            (from_user_id, to_user_id, amount, type, reason, created_by)
            VALUES (?, ?, ?, 'transfer', ?, ?)
        ");
        $stmt->bind_param(
            "iidss",
            $incharge_id,
            $to_admin_id,
            $amount,
            $remark,
            $incharge_id
        );
        $stmt->execute();
        $stmt->close();

        $success = "Cash transferred successfully.";
        $ledgerBalance += $amount;
        $expectedCash -= $amount;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Transfer Cash</title>
</head>

<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Transfer Cash to Admin</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">

                    <div class="alert alert-secondary">
                        <strong>Expected Cash With You (from paid cash invoices):</strong>
                        ₹<?php echo number_format($expectedCash, 2); ?>
                    </div>

                    <div class="alert alert-info">
                        <strong>Ledger Cash Balance (transferred & tracked):</strong>
                        ₹<?php echo number_format($ledgerBalance, 2); ?>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label>Transfer To (Admin)</label>
                            <select name="to_admin_id" class="form-control" required>
                                <option value="">Select Admin</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo $admin['id']; ?>">
                                        <?php echo htmlspecialchars($admin['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number"
                                   step="0.01"
                                   name="amount"
                                   class="form-control"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Remark</label>
                            <input type="text"
                                   name="remark"
                                   class="form-control"
                                   placeholder="Cash handover to admin">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Transfer Cash
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
