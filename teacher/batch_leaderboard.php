<?php
// teacher/batch_leaderboard.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Fetch teacher ID from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher'");
    if ($stmt) {
        $stmt->bind_param("s", $teacher_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($teacher_id);
            $stmt->fetch();
            $_SESSION['teacher_id'] = $teacher_id;
        } else {
            header("Location: index.php?message=invalid_cookie");
            exit();
        }
        $stmt->close();
    } else {
        $error = "An error occurred. Please try again.";
    }
}

if (!$teacher_id) {
    $error = "Invalid session. Please log in again.";
}

// ------------------------------------------------------------
// Get list of teacher's batches for filtering
// ------------------------------------------------------------
$batches = [];
$batchesQuery = "SELECT id, name FROM batches WHERE teacher_id = ?";
$batchStmt = $db->prepare($batchesQuery);
if ($batchStmt) {
    $batchStmt->bind_param("i", $teacher_id);
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
$habitsQuery = "SELECT id, title FROM habits WHERE is_global = 1";
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
// Retrieve Leaderboard Data
// ------------------------------------------------------------
$leaderboardData = [];

$query = "
    SELECT u.username AS parent_name,
           b.name AS batch_name,
           COALESCE(SUM(eu.points), 0) AS total_score
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
    WHERE b.teacher_id = ?
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
    $stmt->bind_param("iii", $teacher_id, $selectedBatchId, $selectedHabitId);
} elseif (!empty($selectedBatchId)) {
    $stmt->bind_param("ii", $teacher_id, $selectedBatchId);
} elseif (!empty($selectedHabitId)) {
    $stmt->bind_param("ii", $teacher_id, $selectedHabitId);
} else {
    $stmt->bind_param("i", $teacher_id);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $leaderboardData[] = $row;
    }
    $stmt->close();
} else {
    $error = "Failed to retrieve leaderboard data.";
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Batch Leaderboard - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .leaderboard-filter {
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Batch Leaderboard</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Filter Leaderboard</strong>
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
                                <th>Parent Name</th>
                                <th>Batch</th>
                                <th>Total Score</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($leaderboardData)): ?>
                                <?php $rank = 1; ?>
                                <?php foreach ($leaderboardData as $row): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['total_score']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No data found.</td>
                                </tr>
                            <?php endif; ?>
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
