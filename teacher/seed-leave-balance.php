<?php
session_start();
require_once '../connection.php';

// Auth: only admins should run this
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    die("Unauthorized — admin login required");
}

$database = new Database();
$db = $database->getConnection();

$leaveData = [
    'Yadnya'    => 1,
    'Ruhina'    => 6,
    'Shreya'    => 3,
    'Shubada'   => 2,
    'Harsha'    => 1,
    'Poonam'    => 11,
    'Sabiya'    => 0,
    'Sifa'      => 0,
    'Aparna'    => 1,
    'Sidhi'     => 3,
    'Mohini'    => 0,
    'Kirti'     => 0,
    'Taskina'   => 0,
    'Alfiya'    => 0,
    'Nazmin'    => 0,
    'Nazbune'   => 0,
    'Dhanashri' => 5,
    'Srisha'    => 0,
    'Mital'     => 5,
    'Vikisha'   => 1,
    'Yukta'     => 0,
];

$matches = [];
$unmatched = [];

foreach ($leaveData as $firstName => $aprilLeaves) {
    $stmt = $db->prepare("SELECT id, full_name, role, username FROM users WHERE full_name LIKE ? AND role IN ('teacher','incharge','quality','sales') AND status = 'active'");
    $like = "%$firstName%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($users) === 0) {
        $unmatched[] = $firstName;
    } else {
        foreach ($users as $u) {
            $matches[] = [
                'first_name'    => $firstName,
                'user_id'       => $u['id'],
                'full_name'     => $u['full_name'],
                'role'          => $u['role'],
                'username'      => $u['username'],
                'april_leaves'  => $aprilLeaves,
                'total_earned'  => $aprilLeaves + 1,
            ];
        }
    }
}

// Handle apply
$applied = false;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    foreach ($matches as $m) {
        $stmt = $db->prepare("
            INSERT INTO leave_balance (user_id, total_earned, total_used, last_credit_month)
            VALUES (?, ?, 0, '2026-05')
            ON DUPLICATE KEY UPDATE
                total_earned = VALUES(total_earned),
                total_used = 0,
                last_credit_month = '2026-05'
        ");
        $stmt->bind_param("ii", $m['user_id'], $m['total_earned']);
        if (!$stmt->execute()) {
            $errors[] = "Failed for {$m['full_name']}: " . $stmt->error;
        }
        $stmt->close();
    }
    if (empty($errors)) {
        $applied = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Seed Leave Balances</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">

<h2>Seed Leave Balances (April + May 2026)</h2>

<?php if ($applied): ?>
<div class="alert alert-success">
    <strong>Done!</strong> Leave balances have been updated for <?= count($matches) ?> user(s).
    <?php if (!empty($unmatched)): ?>
        <br><strong>Unmatched names (no DB user found):</strong> <?= implode(', ', $unmatched) ?>
    <?php endif; ?>
    <hr>
    <a href="seed-leave-balance.php" class="btn btn-outline-secondary">Go back</a>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$applied): ?>

<div class="card shadow mb-4">
<div class="card-header"><strong>Matched Users</strong></div>
<div class="card-body">
    <?php if (empty($matches)): ?>
        <div class="alert alert-warning">No users matched any of the names.</div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>First Name (from list)</th>
                    <th>Matched User</th>
                    <th>Role</th>
                    <th>April Leaves</th>
                    <th>Will Set total_earned =</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matches as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['first_name']) ?></td>
                    <td><?= htmlspecialchars($m['full_name']) ?> (ID: <?= $m['user_id'] ?>)</td>
                    <td><?= htmlspecialchars($m['role']) ?></td>
                    <td><?= $m['april_leaves'] ?></td>
                    <td><strong><?= $m['total_earned'] ?></strong> (April + May)</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</div>

<?php if (!empty($unmatched)): ?>
<div class="card shadow mb-4 border-danger">
<div class="card-header text-danger"><strong>Unmatched Names</strong></div>
<div class="card-body">
    <p>These names didn't match any user in the database:</p>
    <ul>
    <?php foreach ($unmatched as $name): ?>
        <li><strong><?= htmlspecialchars($name) ?></strong></li>
    <?php endforeach; ?>
    </ul>
    <p class="text-muted">Check spelling or search manually before applying.</p>
</div>
</div>
<?php endif; ?>

<?php if (!empty($matches)): ?>
<form method="POST" onsubmit="return confirm('Set leave balances for <?= count($matches) ?> user(s)? This will reset total_used to 0.');">
    <button type="submit" name="apply" class="btn btn-primary btn-lg">Apply Leave Balances</button>
    <span class="text-muted ml-3">(total_earned = April + 1, total_used = 0, last_credit_month = 2026-05)</span>
</form>
<?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
