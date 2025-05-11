<?php
// admin/weekly-masterboard.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// Get list of all centers for filtering
// ------------------------------------------------------------
$centers = [];
$centerQuery = "SELECT DISTINCT location FROM users WHERE role = 'parent'";
$centerStmt = $db->prepare($centerQuery);
if ($centerStmt) {
    $centerStmt->execute();
    $centerRes = $centerStmt->get_result();
    while ($row = $centerRes->fetch_assoc()) {
        $centers[] = $row['location'];
    }
    $centerStmt->close();
}

// ------------------------------------------------------------
// Get list of all batches for filtering
// ------------------------------------------------------------
$batches = [];
$batchesQuery = "SELECT id, name FROM batches";
$batchStmt = $db->prepare($batchesQuery);
if ($batchStmt) {
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
$selectedCenter = $_GET['center'] ?? '';
$selectedBatchId = $_GET['batch_id'] ?? '';
$selectedHabitId = $_GET['habit_id'] ?? '';

// ------------------------------------------------------------
// Fetch Weekly Scores Leaderboard
// ------------------------------------------------------------
$weeklyLeaderboard = [];
$query = "
    SELECT 
        u.full_name AS parent_name, 
        u.profile_picture AS parent_pic,
        u.location AS center_name,
        b.name AS batch_name,  
        CONCAT('Week ', WEEK(CURDATE(), 1)) AS week_number,
        COALESCE(SUM(e.points), 0) AS weekly_score
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads e ON e.parent_id = u.id 
        AND WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1)  -- ✅ Current Week Data
    WHERE u.role = 'parent'
";

// Apply Center filter if set
if (!empty($selectedCenter)) {
    $query .= " AND u.location = ?";
}

// Apply Batch filter if set
if (!empty($selectedBatchId)) {
    $query .= " AND b.id = ?";
}

// Apply Habit filter if set
if (!empty($selectedHabitId)) {
    $query .= " AND e.habit_id = ?";
}

$query .= "
    GROUP BY u.id, b.id
    ORDER BY weekly_score DESC
";

$stmt = $db->prepare($query);
if (!empty($selectedCenter) && !empty($selectedBatchId) && !empty($selectedHabitId)) {
    $stmt->bind_param("sii", $selectedCenter, $selectedBatchId, $selectedHabitId);
} elseif (!empty($selectedCenter) && !empty($selectedBatchId)) {
    $stmt->bind_param("si", $selectedCenter, $selectedBatchId);
} elseif (!empty($selectedCenter) && !empty($selectedHabitId)) {
    $stmt->bind_param("si", $selectedCenter, $selectedHabitId);
} elseif (!empty($selectedBatchId) && !empty($selectedHabitId)) {
    $stmt->bind_param("ii", $selectedBatchId, $selectedHabitId);
} elseif (!empty($selectedCenter)) {
    $stmt->bind_param("s", $selectedCenter);
} elseif (!empty($selectedBatchId)) {
    $stmt->bind_param("i", $selectedBatchId);
} elseif (!empty($selectedHabitId)) {
    $stmt->bind_param("i", $selectedHabitId);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $weeklyLeaderboard[] = $row;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Weekly Masterboard - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/dataTables.bootstrap4.min.js"></script>
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
            <h2 class="page-title">Weekly Masterboard</h2>

            <div class="card shadow">
                <div class="card-header"><strong>Filter Masterboard</strong></div>
                <div class="card-body">
                    <form method="GET" class="form-inline leaderboard-filter">
                        <label for="center" class="mr-2">Center</label>
                        <select name="center" id="center" class="form-control mr-4">
                            <option value="">All Centers</option>
                            <?php foreach ($centers as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($c == $selectedCenter) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

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

            <!-- Weekly Score Leaderboard -->
            <div class="card shadow">
                <div class="card-header"><strong>Weekly Masterboard Rankings (Week <?php echo date("W"); ?>)</strong></div>
                <div class="card-body">
                    <table id="leaderboardTable" class="table table-hover datatable">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Center</th>
                            <th>Batch</th>
                            <th>Weekly Score</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($weeklyLeaderboard as $scorer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($scorer['parent_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['center_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['weekly_score']); ?></td>
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