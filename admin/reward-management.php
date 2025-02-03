<?php
// admin/reward-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Fetch all assigned rewards
$rewardQuery = "
    SELECT users.username, rewards.points, rewards.badges, rewards.certificates, rewards.created_at 
    FROM rewards
    JOIN users ON rewards.user_id = users.id
    ORDER BY rewards.created_at DESC
";

$rewardStmt = $db->prepare($rewardQuery);
$rewardStmt->execute();
$rewards = $rewardStmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Reward Management - Habits Web App</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Reward Management</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Auto-Generated Rewards</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <th>Username</th>
                            <th>Points</th>
                            <th>Badges</th>
                            <th>Certificates</th>
                            <th>Assigned On</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($reward = $rewards->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reward['username']); ?></td>
                                <td><?php echo htmlspecialchars($reward['points']); ?></td>
                                <td><?php echo htmlspecialchars($reward['badges']); ?></td>
                                <td><?php echo htmlspecialchars($reward['certificates']); ?></td>
                                <td><?php echo htmlspecialchars($reward['created_at']); ?></td>
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
</body>
</html>
