<?php
// incharge/batch_masterboard.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];
$stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$result = $stmt->get_result();
$incharge = $result->fetch_assoc();
$incharge_id = $incharge['id'] ?? null;
$stmt->close();

if (!$incharge_id) {
    die("Incharge not found.");
}

// ------------------------------------------------------------
// Get list of incharge's batches for filtering
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
// Get list of global habits for filtering
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
// Retrieve Masterboard Data
// ------------------------------------------------------------
$leaderboardData = [];

$query = "
    SELECT 
        u.full_name AS student_name,
        u.profile_picture AS student_pic,
        b.name AS batch_name,
        COALESCE(SUM(eu.points), 0) AS total_score
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
        AND WEEK(eu.uploaded_at, 1) = WEEK(CURDATE(), 1) -- ✅ Filter current week scores
    WHERE b.incharge_id = ?
";

// Apply batch filter if set
if (!empty($selectedBatchId)) {
    $query .= " AND b.id = ? ";
}

// Apply habit filter if set
if (!empty($selectedHabitId)) {
    $query .= " AND eu.habit_id = ? ";
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
        $leaderboardData[] = $row;
    }
    $stmt->close();
} else {
    die("Failed to retrieve masterboard data.");
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Batch Masterboard - Habits365Club</title>
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
            <h2 class="page-title">Batch Masterboard</h2>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Filter Masterboard</strong>
                </div>
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

                    <hr>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Batch</th>
                                <th>Total Score</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $rank = 1; ?>
                            <?php foreach ($leaderboardData as $row): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($row['student_pic'] ?? 'assets/images/user.png'); ?>" 
                                             alt="Profile" class="profile-img">
                                        <?php echo htmlspecialchars($row['student_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['total_score']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><!-- /.table-responsive -->
                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div><!-- /.container-fluid -->
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>