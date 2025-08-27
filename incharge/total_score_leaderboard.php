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
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-t');

// ------------------------------------------------------------
// Fetch Total Scores Leaderboard
// ------------------------------------------------------------
$totalLeaderboard = [];
$query = "
    SELECT 
        u.full_name AS student_name, 
        u.username AS student_username,           -- Add this line
        u.profile_picture AS student_pic,
        b.name AS batch_name,  
        u.created_at AS date_of_joining,
        COALESCE(SUM(e.points), 0) AS total_score
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads e ON e.parent_id = u.id 
    WHERE u.role = 'parent' 
        AND b.incharge_id = ?
        AND u.status = 'active'
";

// Apply batch filter if set
if (!empty($selectedBatchId)) {
    $query .= " AND b.id = ? ";
}

// Apply habit filter if set
if (!empty($selectedHabitId)) {
    $query .= " AND e.habit_id = ? ";
}

// Apply date range filter
if (!empty($fromDate) && !empty($toDate)) {
    $query .= " AND e.uploaded_at BETWEEN ? AND ? ";
}

$query .= "
    GROUP BY u.id, b.id
    ORDER BY total_score DESC
";

// Prepare statement with dynamic params
$params = [$incharge_id];
$types = "i";
if (!empty($selectedBatchId)) {
    $params[] = $selectedBatchId;
    $types .= "i";
}
if (!empty($selectedHabitId)) {
    $params[] = $selectedHabitId;
    $types .= "i";
}
if (!empty($fromDate) && !empty($toDate)) {
    $params[] = $fromDate;
    $params[] = $toDate;
    $types .= "ss";
}

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);

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

                        <label for="from_date" class="mr-2">From</label>
                        <input type="date" name="from_date" id="from_date" class="form-control mr-4"
                               value="<?php echo htmlspecialchars($fromDate); ?>">

                        <label for="to_date" class="mr-2">To</label>
                        <input type="date" name="to_date" id="to_date" class="form-control mr-4"
                               value="<?php echo htmlspecialchars($toDate); ?>">

                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </form>
                </div>
            </div>

            <!-- Total Score Leaderboard -->
            <div class="card shadow">
                <div class="card-header"><strong>Monthly Masterboard Rankings</strong></div>
                <div class="card-body">
                    <table class="table table-hover datatable">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Batch</th>
                            <th>Date of Joining</th>
                            <th>Total Score</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($totalLeaderboard as $scorer): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($scorer['student_pic'] ?? 'assets/images/user.png'); ?>" class="profile-img">
                                    <?php echo htmlspecialchars($scorer['student_name']); ?>
                                    <?php if (!empty($scorer['student_username'])): ?>
                                        <span class="text-muted">(<?php echo htmlspecialchars($scorer['student_username']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($scorer['batch_name']); ?></td>
                                <td>
                                    <?php echo !empty($scorer['date_of_joining']) ? date('d M Y', strtotime($scorer['date_of_joining'])) : 'N/A'; ?>
                                </td>
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
    $('.datatable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'B>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-sm btn-info mr-1',
                title: 'Total Score Masterboard',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-success mr-1',
                title: 'Total Score Masterboard',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-danger',
                title: 'Total Score Masterboard',
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