<?php
// teacher/batch_masterboard.php

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
$batchesQuery = "
    SELECT b.id, b.name 
    FROM batches b 
    JOIN batch_teachers bt ON b.id = bt.batch_id 
    WHERE bt.teacher_id = ?
";
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
    JOIN batch_teachers bt ON b.id = bt.batch_id
    LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
        AND WEEK(eu.uploaded_at, 1) = WEEK(CURDATE(), 1)
    WHERE bt.teacher_id = ?
    AND u.status = 'active'  /* Add this line to filter active students only */
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
    ORDER BY total_score ASC
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
    $error = "Failed to retrieve masterboard data.";
}

// ------------------------------------------------------------
// Fetch Master of the Week (Highest Score in Teacher's Batches)
// ------------------------------------------------------------
$query = "
    SELECT 
        u.full_name AS student_name, 
        u.profile_picture AS student_pic,  
        b.name AS batch_name,
        COALESCE(SUM(eu.points), 0) AS total_score
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    JOIN batch_teachers bt ON b.id = bt.batch_id
    LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id  
        AND WEEK(eu.uploaded_at, 1) = WEEK(CURDATE(), 1) 
    WHERE bt.teacher_id = ?
    GROUP BY u.id
    ORDER BY total_score DESC
    LIMIT 1
";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$master_of_week = $stmt->get_result();
$stmt->close();

?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Batch Masterboard - Habits365Club</title>
    <!-- Add DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
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
        .master-card {
            border: 2px solid #FFD700;
            background-color: #FFF9C4;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
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
            <h2 class="page-title">Batch Masterboard</h2>

            <!-- Master of the Week Section -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>Master of the Week</strong>
                </div>
                <div class="card-body">
                    <?php if ($master_of_week->num_rows > 0): ?>
                        <div class="master-card">
                            <?php while ($row = $master_of_week->fetch_assoc()): ?>
                                <img src="<?php echo htmlspecialchars($row['student_pic'] ?? 'assets/images/user.png'); ?>" 
                                     alt="Profile" class="profile-img">
                                <h4 class="text-warning">🏅 <?php echo htmlspecialchars($row['student_name']); ?></h4>
                                <p>Batch: <?php echo htmlspecialchars($row['batch_name']); ?></p>
                                <p>Total Score: <strong><?php echo $row['total_score']; ?></strong></p>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No data available for Master of the Week.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add this form before the table-responsive div -->
            <form method="GET" class="form-inline leaderboard-filter mb-4">
                <div class="form-group mr-3">
                    <label for="batch_id" class="mr-2">Select Batch:</label>
                    <select name="batch_id" id="batch_id" class="form-control">
                        <option value="">All Batches</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>" 
                                    <?php echo ($selectedBatchId == $batch['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($batch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </form>

            <!-- Leaderboard Table -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>Leaderboard</strong>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-bordered datatable">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Batch</th>
                                <th>Total Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($leaderboardData as $row): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['total_score']); ?></td>
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

<!-- Add DataTables and Export Buttons -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    $('.table').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'B>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-sm btn-info mr-1',
                title: 'Batch Leaderboard',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-success mr-1',
                title: 'Batch Leaderboard',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-danger',
                title: 'Batch Leaderboard',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ],
        order: [[3, 'desc']], // Sort by total score column
        pageLength: 25
    });
});
</script>
</body>
</html>