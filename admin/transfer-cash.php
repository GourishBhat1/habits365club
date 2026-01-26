

<?php
session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   CONFIG: OWNER ADMINS
------------------------------*/
$ownerEmails = [
    'prashant@example.com',
    'pallavi@example.com'
];

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
   FETCH LOGGED-IN ADMIN
------------------------------*/
$admin_email = $_SESSION['admin_email'] ?? $_COOKIE['admin_email'];

$stmt = $db->prepare("
    SELECT id, username, full_name
    FROM users
    WHERE email = ? AND role = 'admin'
    LIMIT 1
");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Unauthorized access.");
}

$admin_id = (int)$admin['id'];

/* -----------------------------
   FETCH OWNER ADMINS
------------------------------*/
$owners = [];

$placeholders = implode(',', array_fill(0, count($ownerEmails), '?'));
$types = str_repeat('s', count($ownerEmails));

$sql = "
    SELECT id, full_name, email
    FROM users
    WHERE role = 'admin'
      AND email IN ($placeholders)
    ORDER BY full_name
";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$ownerEmails);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $owners[] = $row;
}
$stmt->close();

/* -----------------------------
   CURRENT CASH BALANCE
------------------------------*/
$currentBalance = getCashBalance($db, $admin_id);

/* -----------------------------
   HANDLE TRANSFER
------------------------------*/
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_owner_id = intval($_POST['to_owner_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');

    if ($to_owner_id <= 0 || $amount <= 0) {
        $error = "Invalid transfer details.";
    } elseif ($amount > $currentBalance) {
        $error = "Insufficient cash balance.";
    } else {
        $stmt = $db->prepare("
            INSERT INTO cash_ledger
            (from_user_id, to_user_id, amount, type, reason, created_by)
            VALUES (?, ?, ?, 'transfer', ?, ?)
        ");
        $stmt->bind_param(
            "iidss",
            $admin_id,
            $to_owner_id,
            $amount,
            $remark,
            $admin_id
        );
        $stmt->execute();
        $stmt->close();

        $success = "Cash transferred to owner successfully.";
        $currentBalance -= $amount;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Transfer Cash to Owner</title>
</head>

<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Transfer Cash to Owner</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">

                    <div class="alert alert-info">
                        <strong>Current Cash Balance:</strong>
                        ₹<?php echo number_format($currentBalance, 2); ?>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label>Transfer To (Owner)</label>
                            <select name="to_owner_id" class="form-control" required>
                                <option value="">Select Owner</option>
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?php echo $owner['id']; ?>">
                                        <?php echo htmlspecialchars($owner['full_name']); ?>
                                        (<?php echo htmlspecialchars($owner['email']); ?>)
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
                                   placeholder="Settlement to owner">
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