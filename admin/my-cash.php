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
   CONFIG
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
$isOwner = in_array($admin_email, $ownerEmails);

/* -----------------------------
   CURRENT ADMIN BALANCE
------------------------------*/
$myBalance = getCashBalance($db, $admin_id);

/* -----------------------------
   OWNER COMBINED BALANCE (OWNER ONLY)
------------------------------*/
$ownerCombinedBalance = 0;

if ($isOwner) {
    $placeholders = implode(',', array_fill(0, count($ownerEmails), '?'));
    $types = str_repeat('s', count($ownerEmails));

    $stmt = $db->prepare("
        SELECT id FROM users
        WHERE role = 'admin'
        AND email IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$ownerEmails);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $ownerCombinedBalance += getCashBalance($db, (int)$row['id']);
    }
    $stmt->close();
}

/* -----------------------------
   FETCH RECENT CASH LEDGER
------------------------------*/
$stmt = $db->prepare("
    SELECT cl.*, 
           u1.full_name AS from_name,
           u2.full_name AS to_name
    FROM cash_ledger cl
    LEFT JOIN users u1 ON cl.from_user_id = u1.id
    LEFT JOIN users u2 ON cl.to_user_id = u2.id
    WHERE cl.from_user_id = ? OR cl.to_user_id = ?
    ORDER BY cl.created_at DESC
    LIMIT 20
");
$stmt->bind_param("ii", $admin_id, $admin_id);
$stmt->execute();
$ledger = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>My Cash</title>
</head>

<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">My Cash</h2>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5>My Current Cash Balance</h5>
                            <h2 class="text-primary">
                                ₹<?php echo number_format($myBalance, 2); ?>
                            </h2>
                        </div>
                    </div>
                </div>

                <?php if ($isOwner): ?>
                    <div class="col-md-6">
                        <div class="card shadow border-success">
                            <div class="card-body">
                                <h5>Total Cash With Owners</h5>
                                <h2 class="text-success">
                                    ₹<?php echo number_format($ownerCombinedBalance, 2); ?>
                                </h2>
                                <small class="text-muted">
                                    Combined balance of Prashant & Pallavi
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Recent Cash Movements</strong>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Amount</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ledger->num_rows > 0): ?>
                                <?php while ($row = $ledger->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d M Y H:i', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['from_name'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['to_name'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            ₹<?php echo number_format($row['amount'], 2); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['reason'] ?? '-'); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        No cash transactions found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>