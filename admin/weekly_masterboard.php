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
$selectedWeek = $_GET['week'] ?? date('W');

$weeklyLeaderboard = [];
$query = "
    SELECT 
        u.full_name AS parent_name, 
        u.profile_picture AS parent_pic,
        u.location AS center_name,
        b.name AS batch_name,  
        u.created_at AS date_of_joining,
        COALESCE(SUM(e.points), 0) AS weekly_score
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads e ON e.parent_id = u.id 
        AND WEEK(e.uploaded_at, 1) = ?  -- Use selected week
    WHERE u.role = 'parent'
    AND u.status = 'active'
";

// Apply Center filter if set
if (!empty($selectedCenter)) {
    $query .= " AND u.location = ?";
}
if (!empty($selectedBatchId)) {
    $query .= " AND b.id = ?";
}
if (!empty($selectedHabitId)) {
    $query .= " AND e.habit_id = ?";
}

$query .= "
    GROUP BY u.id, b.id
    ORDER BY weekly_score DESC
";

// Prepare bind params
$params = [$selectedWeek];
$types = "i";
if (!empty($selectedCenter)) {
    $types .= "s";
    $params[] = $selectedCenter;
}
if (!empty($selectedBatchId)) {
    $types .= "i";
    $params[] = $selectedBatchId;
}
if (!empty($selectedHabitId)) {
    $types .= "i";
    $params[] = $selectedHabitId;
}

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
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
    <!-- DataTables CSS -->
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
                    <form method="GET" class="form-inline leaderboard-filter" id="filters">
                        <label for="center" class="mr-2">Center</label>
                        <select name="center" id="center" class="form-control mr-4">
                            <option value="">All Centers</option>
                            <?php foreach ($centers as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($c == $selectedCenter) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="batch_id" class="mr-2">Batch</label>
                        <select name="batch_id" id="batch_id" class="form-control mr-4">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo ($b['id'] == $selectedBatchId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="habit_id" class="mr-2">Habit</label>
                        <select name="habit_id" id="habit_id" class="form-control mr-4">
                            <option value="">All Habits</option>
                            <?php foreach ($habits as $h): ?>
                                <option value="<?php echo $h['id']; ?>" <?php echo ($h['id'] == $selectedHabitId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($h['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="week" class="mr-2">Week</label>
                        <input type="number" min="1" max="53" name="week" id="week" class="form-control mr-4"
                               value="<?php echo htmlspecialchars($selectedWeek); ?>">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </form>
                </div>
            </div>
            <!-- Weekly Score Leaderboard -->
            <div class="card shadow">
                <div class="card-header"><strong>Weekly Masterboard Rankings (Week <?php echo htmlspecialchars($selectedWeek); ?>)</strong></div>
                <div class="card-body">
                    <table id="leaderboardTable" class="table table-hover datatable">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Center</th>
                            <th>Batch</th>
                            <th>Date of Joining</th>
                            <th>Weekly Score</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($weeklyLeaderboard as $scorer): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($scorer['parent_pic'])): ?>
                                        <img src="<?php echo htmlspecialchars($scorer['parent_pic']); ?>" class="profile-img" alt="Profile">
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($scorer['parent_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($scorer['center_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($scorer['date_of_joining']))); ?></td>
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

<!-- DataTables JS -->
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
    $('#leaderboardTable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'B>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-sm btn-info mr-1',
                title: 'Weekly Masterboard - Week <?php echo htmlspecialchars($selectedWeek); ?>',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-success mr-1',
                title: 'Weekly Masterboard - Week <?php echo htmlspecialchars($selectedWeek); ?>',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-danger',
                title: 'Weekly Masterboard - Week <?php echo htmlspecialchars($selectedWeek); ?>',
                exportOptions: { columns: ':visible' }
            }
        ],
        order: [[4, 'desc']], // Sort by weekly score column
        pageLength: 25
    });

    // Submit form on filter change
    $('#filters select, #filters input').change(function() {
        $(this).closest('form').submit();
    });
});
</script>
</html>