<?php
// admin/add-reward.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$error = '';
$success = '';

$database = new Database();
$db = $database->getConnection();

// Fetch users (parents)
$userQuery = "SELECT id, username FROM users WHERE role = 'parent'";
$userStmt = $db->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $points = trim($_POST['points'] ?? 0);
    $badges = trim($_POST['badges'] ?? 0);

    if (empty($user_id)) {
        $error = "Please select a user.";
    } else {
        $insertQuery = "INSERT INTO rewards (user_id, points, badges, created_at) VALUES (?, ?, ?, NOW())";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bind_param("iii", $user_id, $points, $badges);

        if ($insertStmt->execute()) {
            $success = "Reward assigned successfully.";
        } else {
            $error = "An error occurred.";
        }
        $insertStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Assign Reward - Habits Web App</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Assign Reward</h2>
            <div class="card shadow">
                <div class="card-body">
                    <form action="add-reward.php" method="POST">
                        <div class="form-group">
                            <label for="user_id">Select User <span class="text-danger">*</span></label>
                            <select id="user_id" name="user_id" class="form-control select2" required>
                                <option value="">Choose a User</option>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="points">Points</label>
                            <input type="number" id="points" name="points" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label for="badges">Badges</label>
                            <input type="number" id="badges" name="badges" class="form-control" value="0">
                        </div>
                        <button type="submit" class="btn btn-primary">Assign Reward</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
