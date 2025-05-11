<?php
// incharge/incharge-total-masterboard.php

session_start();

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Retrieve incharge username
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

// Fetch incharge ID
$stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$result = $stmt->get_result();
$incharge = $result->fetch_assoc();
$incharge_id = $incharge['id'] ?? null;
$stmt->close();

// Validate if incharge exists
if (!$incharge_id) {
    die("Incharge not found.");
}

// ------------------------------------------------------------
// Get list of batches assigned to this incharge
// ------------------------------------------------------------
$batches = [];
$batchesQuery = "SELECT id, name FROM batches WHERE incharge_id = ?";
$batchStmt = $db->prepare($batchesQuery);
if ($batchStmt) {
    $batchStmt->bind_param("i", $incharge_id);
    $batchStmt->execute();
    $batchRes = $batchStmt->get_result();
    while ($row = $batchRes->fetch_assoc()) {
        $batches[] = $row;
    }
    $batchStmt->close();
}

// ------------------------------------------------------------
// Get list of habits for filtering
// ------------------------------------------------------------
$habits = [];
$habitsQuery = "SELECT id, title FROM habits";
$habitsStmt = $db->prepare($habitsQuery);
if ($habitsStmt) {
    $habitsStmt->execute();
    $habitsRes = $habitsStmt->get_result();
    while ($row = $habitsRes->fetch_assoc()) {
        $habits[] = $row;
    }
    $habitsStmt->close();
}

// ------------------------------------------------------------
// Handle Filters
// ------------------------------------------------------------
$selectedBatchId = $_GET['batch_id'] ?? '';
$selectedHabitId = $_GET['habit_id'] ?? '';

// ------------------------------------------------------------
// Fetch Total Scores Leaderboard
// ------------------------------------------------------------
$totalLeaderboard = [];
$query = "
    SELECT 
        u.full_name AS student_name, 
        u.profile_picture AS student_pic,
        b.name AS batch_name,  
        COALESCE(SUM(e.points), 0) AS total_score
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads e ON e.parent_id = u.id 
    WHERE u.role = 'parent' 
        AND b.incharge_id = ?
";

// Apply batch filter if set
if (!empty($selectedBatchId)) {
    $query .= " AND b.id = ? ";
}

// Apply habit filter if set
if (!empty($selectedHabitId)) {
    $query .= " AND e.habit_id = ? ";
}

$query .= "
    GROUP BY u.id, b.id
    ORDER BY total_score DESC
";

$stmt = $db->prepare($query);
if (!empty($selectedBatchId) && !empty($selectedHabitId)) {
    $stmt->bind_param("iii", $incharge_id, $selectedBatchId, $selectedHabitId);
} elseif (!empty($selectedBatchId)) {
    $stmt->bind_param("ii", $incharge_id, $selectedBatchId);
} elseif (!empty($selectedHabitId)) {
    $stmt->bind_param("ii", $incharge_id, $selectedHabitId);
} else {
    $stmt->bind_param("i", $incharge_id);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $totalLeaderboard[] = $row;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Total Masterboard - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .leaderboard-filter {
            margin-bottom: 20px;
        }
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Total Masterboard</h2>

            <div class="card shadow">
                <div class="card-header"><strong>Filter Masterboard</strong></div>
                <div class="card-body">
                    <form method="GET" class="form-inline leaderboard-filter">
                        <label for="batch_id" class="mr-2">Batch</label>
                        <select name="batch_id" id="batch_id" class="form-control mr-4">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?php echo $b['id']; ?>"
                                    <?php echo ($b['id'] == $selectedBatchId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="habit_id" class="mr-2">Habit</label>
                        <select name="habit_id" id="habit_id" class="form-control mr-4">
                            <option value="">All Habits</option>
                            <?php foreach ($habits as $h): ?>
                                <option value="<?php echo $h['id']; ?>"
                                    <?php echo ($h['id'] == $selectedHabitId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($h['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </form>
                </div>
            </div>

            <!-- Total Score Leaderboard -->
            <div class="card shadow">
                <div class="card-header"><strong>Overall Masterboard Rankings</strong></div>
                <div class="card-body">
                    <table class="table table-hover datatable">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Batch</th>
                            <th>Total Score</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($totalLeaderboard as $scorer): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($scorer['student_pic'] ?? 'assets/images/user.png'); ?>" class="profile-img">
                                    <?php echo htmlspecialchars($scorer['student_name']); ?>
                                </td>
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