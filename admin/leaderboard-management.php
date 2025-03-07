<?php
// admin/leaderboard-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// ✅ **Handle multiplier updates inside the `habits` table**
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['habit_multipliers'])) {
    foreach ($_POST['habit_multipliers'] as $habit_id => $multiplier) {
        $multiplier = floatval($multiplier);

        $query = "UPDATE habits SET multiplier = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("di", $multiplier, $habit_id);
        $stmt->execute();
        $stmt->close();
    }
    $success = "Multipliers updated successfully.";
}

// ✅ **Fetch habit multipliers from the `habits` table**
$habitMultipliers = [];
$query = "SELECT id, title, multiplier FROM habits";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $habitMultipliers[] = $row;
}
$stmt->close();

// ✅ **Calculate leaderboard using `rewards`, `habits`, and `users`**
$topScorers = [];
$query = "
    SELECT u.full_name AS parent_name, 
           b.name AS batch_name, 
           SUM(e.points * h.multiplier) AS total_score
    FROM evidence_uploads e
    JOIN users u ON e.parent_id = u.id
    LEFT JOIN batches b ON u.batch_id = b.id
    JOIN habits h ON e.habit_id = h.id
    WHERE e.status = 'approved'
    GROUP BY u.id, b.id
    ORDER BY total_score DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $topScorers[] = $row;
}
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Leaderboard Management - Habits365Club</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Leaderboard Management</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- <div class="card shadow">
                <div class="card-header"><strong>Multipliers & Configuration</strong></div>
                <div class="card-body">
                    <form action="" method="POST">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Habit Name</th>
                                <th>Multiplier</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($habitMultipliers as $habit): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($habit['title']); ?></td>
                                    <td>
                                        <input type="number" step="0.1" name="habit_multipliers[<?php echo $habit['id']; ?>]"
                                               value="<?php echo htmlspecialchars($habit['multiplier']); ?>" class="form-control">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-primary">Update Multipliers</button>
                    </form>
                </div>
            </div> -->

            <div class="card shadow">
                <div class="card-header"><strong>Leaderboard Rankings</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                        <tr>
                            <th>Child Name</th>
                            <th>Batch</th>
                            <th>Total Score</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topScorers as $scorer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($scorer['parent_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['total_score']); ?></td>
                            </tr>
                        <?php endforeach; ?>
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
