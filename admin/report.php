<?php
// admin/reports.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include the database connection
require_once '../connection.php';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Get filters from GET parameters
$selectedCenter = $_GET['center'] ?? ''; 
$selectedWeek = $_GET['week'] ?? date('W');
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Weekly low scoring students
$weeklyLowScorers = [];
$weeklyLowScoreSQL = "
SELECT 
  u.full_name AS parent_name,
  b.name AS batch_name,
  GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ', ') AS teacher_name,
  COUNT(eu.id) AS submission_count,
  MAX(eu.uploaded_at) AS last_submission,
  (SELECT COUNT(*) FROM habits) * 7 AS expected_submissions
FROM users u
JOIN batches b ON u.batch_id = b.id
LEFT JOIN batch_teachers bt ON b.id = bt.batch_id
LEFT JOIN users t ON bt.teacher_id = t.id AND t.status = 'active'  /* Add status filter for teachers */
LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
WHERE u.role = 'parent' 
AND u.status = 'active'
AND WEEK(eu.uploaded_at, 1) = ?" . ($selectedCenter ? " AND u.location = ?" : "") . "
GROUP BY u.id
ORDER BY submission_count ASC
";
if ($selectedCenter) {
    $weeklyStmt = $db->prepare($weeklyLowScoreSQL);
    $weeklyStmt->bind_param("is", $selectedWeek, $selectedCenter);
} else {
    $weeklyStmt = $db->prepare($weeklyLowScoreSQL);
    $weeklyStmt->bind_param("i", $selectedWeek);
}
$weeklyStmt->execute();
$weeklyResult = $weeklyStmt->get_result();
while ($row = $weeklyResult->fetch_assoc()) {
    $weeklyLowScorers[] = $row;
}
$weeklyStmt->close();

// Monthly low scoring students
$monthlyLowScorers = [];
$monthlyLowScoreSQL = "
SELECT 
  u.full_name AS parent_name,
  b.name AS batch_name,
  GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ', ') AS teacher_name,
  COUNT(eu.id) AS submission_count,
  MAX(eu.uploaded_at) AS last_submission,
  (SELECT COUNT(*) FROM habits) * DAY(LAST_DAY(CONCAT(?, '-01'))) AS expected_submissions
FROM users u
JOIN batches b ON u.batch_id = b.id
LEFT JOIN batch_teachers bt ON b.id = bt.batch_id
LEFT JOIN users t ON bt.teacher_id = t.id AND t.status = 'active'  /* Add status filter for teachers */
LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
WHERE u.role = 'parent' 
AND u.status = 'active'
" . ($selectedCenter ? " AND u.location = ?" : "") . "
AND (eu.uploaded_at IS NULL OR DATE_FORMAT(eu.uploaded_at, '%Y-%m') = ?)
GROUP BY u.id
ORDER BY submission_count ASC
";
if ($selectedCenter) {
    $monthlyStmt = $db->prepare($monthlyLowScoreSQL);
    $monthlyStmt->bind_param("sss", $selectedMonth, $selectedCenter, $selectedMonth);
} else {
    $monthlyStmt = $db->prepare($monthlyLowScoreSQL);
    $monthlyStmt->bind_param("ss", $selectedMonth, $selectedMonth);
}
$monthlyStmt->execute();
$monthlyResult = $monthlyStmt->get_result();
while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyLowScorers[] = $row;
}
$monthlyStmt->close();

// Teacher performance comparing last week vs week before
// Get week numbers for current and last week
$currentWeek = date('W', strtotime('last week'));
$lastWeek = date('W', strtotime('2 weeks ago'));

$teacherScores = [];
$teacherSQL = "
SELECT 
    u.full_name AS teacher_name,
    -- Last week
    (
        SELECT IFNULL(ROUND(COUNT(eu1.id) / NULLIF(COUNT(DISTINCT p1.id),0),2),0)
        FROM batches b1
        JOIN batch_teachers bt1 ON b1.id = bt1.batch_id
        JOIN users p1 ON p1.batch_id = b1.id AND p1.role = 'parent' AND p1.status = 'active'
        LEFT JOIN evidence_uploads eu1 ON eu1.parent_id = p1.id AND WEEK(eu1.uploaded_at, 1) = ?
        WHERE bt1.teacher_id = u.id
        " . ($selectedCenter ? "AND p1.location = ?" : "") . "
    ) AS last_week_avg,
    -- This week
    (
        SELECT IFNULL(ROUND(COUNT(eu2.id) / NULLIF(COUNT(DISTINCT p2.id),0),2),0)
        FROM batches b2
        JOIN batch_teachers bt2 ON b2.id = bt2.batch_id
        JOIN users p2 ON p2.batch_id = b2.id AND p2.role = 'parent' AND p2.status = 'active'
        LEFT JOIN evidence_uploads eu2 ON eu2.parent_id = p2.id AND WEEK(eu2.uploaded_at, 1) = ?
        WHERE bt2.teacher_id = u.id
        " . ($selectedCenter ? "AND p2.location = ?" : "") . "
    ) AS this_week_avg
FROM users u
WHERE u.role = 'teacher'
AND u.status = 'active'  /* Add this line to filter active teachers only */
";
if ($selectedCenter) {
    $teacherStmt = $db->prepare($teacherSQL);
    $teacherStmt->bind_param("isis", $lastWeek, $selectedCenter, $currentWeek, $selectedCenter);
} else {
    $teacherStmt = $db->prepare($teacherSQL);
    $teacherStmt->bind_param("ii", $lastWeek, $currentWeek);
}
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
while ($row = $teacherResult->fetch_assoc()) {
    $row['diff'] = $row['this_week_avg'] - $row['last_week_avg'];
    $teacherScores[] = $row;
}
$teacherStmt->close();

// Get selected month for teacher performance (format: YYYY-MM)
$selectedTeacherMonth = $_GET['teacher_month'] ?? date('Y-m');

// Teacher performance for the selected month
$teacherMonthlyScores = [];
$teacherMonthlySQL = "
SELECT 
    u.full_name AS teacher_name,
    (
        SELECT IFNULL(ROUND(COUNT(eu1.id) / NULLIF(COUNT(DISTINCT p1.id),0),2),0)
        FROM batches b1
        JOIN batch_teachers bt1 ON b1.id = bt1.batch_id
        JOIN users p1 ON p1.batch_id = b1.id AND p1.role = 'parent' AND p1.status = 'active'
        LEFT JOIN evidence_uploads eu1 ON eu1.parent_id = p1.id AND DATE_FORMAT(eu1.uploaded_at, '%Y-%m') = ?
        WHERE bt1.teacher_id = u.id
        " . ($selectedCenter ? "AND p1.location = ?" : "") . "
    ) AS month_avg
FROM users u
WHERE u.role = 'teacher'
AND u.status = 'active'
";
if ($selectedCenter) {
    $teacherMonthlyStmt = $db->prepare($teacherMonthlySQL);
    $teacherMonthlyStmt->bind_param("ss", $selectedTeacherMonth, $selectedCenter);
} else {
    $teacherMonthlyStmt = $db->prepare($teacherMonthlySQL);
    $teacherMonthlyStmt->bind_param("s", $selectedTeacherMonth);
}
$teacherMonthlyStmt->execute();
$teacherMonthlyResult = $teacherMonthlyStmt->get_result();
while ($row = $teacherMonthlyResult->fetch_assoc()) {
    $teacherMonthlyScores[] = $row;
}
$teacherMonthlyStmt->close();

// Load available centers from centers table
$centers = [];
$centerQuery = "SELECT location FROM centers";
$centerStmt = $db->prepare($centerQuery);
$centerStmt->execute();
$centerResult = $centerStmt->get_result();
while ($row = $centerResult->fetch_assoc()) {
    $centers[] = $row['location'];
}
$centerStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Admin Reports - Habits365Club</title>
    
    <!-- DataTables and Buttons CDN links -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
    
    <style>
        .table-container {
            margin-top: 20px;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Reports & Analytics</h2>

            <!-- Location Filter (keep at the top) -->
<form method="GET" class="mb-3">
    <label for="center">Location</label>
    <select id="center" name="center" class="form-control d-inline-block w-auto">
        <option value="">All Locations</option>
        <?php foreach ($centers as $center): ?>
            <option value="<?php echo htmlspecialchars($center); ?>" <?php echo ($selectedCenter == $center) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($center); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="hidden" name="week" value="<?php echo htmlspecialchars($selectedWeek); ?>">
    <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonth); ?>">
    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
</form>

            <!-- Teacher Performance Card (NO week filter above this) -->
<div class="card shadow mt-4">
    <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Teacher Performance (Week-over-Week)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($teacherScores)): ?>
            <table id="teacherPerformanceTable" class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Teacher Name</th>
                        <th>Last Week Avg Submissions/Parent</th>
                        <th>This Week Avg Submissions/Parent</th>
                        <th>Difference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teacherScores as $row): ?>
                        <?php $diff = $row['this_week_avg'] - $row['last_week_avg']; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                            <td><?php echo $row['last_week_avg']; ?></td>
                            <td><?php echo $row['this_week_avg']; ?></td>
                            <td>
                                <?php
                                $arrow = '';
                                $color = '';
                                if ($diff > 0) {
                                    $arrow = '↑';
                                    $color = 'green';
                                } elseif ($diff < 0) {
                                    $arrow = '↓';
                                    $color = 'red';
                                } else {
                                    $arrow = '→';
                                    $color = 'orange';
                                }
                                ?>
                                <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                    <?php echo $diff . ' ' . $arrow; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted text-center">No teacher data available.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Teacher Monthly Performance Card -->
<div class="card shadow mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Teacher Performance (Monthly)</h5>
        <form method="GET" class="mb-0">
            <input type="hidden" name="center" value="<?php echo htmlspecialchars($selectedCenter); ?>">
            <label for="teacher_month" class="mb-0 mr-1">Month</label>
            <select id="teacher_month" name="teacher_month" class="form-control d-inline-block w-auto">
                <?php
                $currentYear = date('Y');
                $currentMonth = date('n');
                for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                    for ($m = 12; $m >= 1; $m--) {
                        $val = sprintf('%04d-%02d', $y, $m);
                        $selected = ($selectedTeacherMonth == $val) ? 'selected' : '';
                        echo "<option value=\"$val\" $selected>" . date('F Y', strtotime("$y-$m-01")) . "</option>";
                    }
                }
                ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm ml-1">Apply</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!empty($teacherMonthlyScores)): ?>
            <table id="teacherMonthlyPerformanceTable" class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Teacher Name</th>
                        <th>Avg Submissions/Parent (<?php echo htmlspecialchars(date('F Y', strtotime($selectedTeacherMonth . '-01'))); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teacherMonthlyScores as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                            <td><?php echo $row['month_avg']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted text-center">No teacher data available for this month.</p>
        <?php endif; ?>
    </div>
</div>

            <!-- Weekly Low Scoring Students Table -->
<div class="card shadow mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Low Scoring Students - Weekly</h5>
        <form method="GET" class="mb-0">
            <input type="hidden" name="center" value="<?php echo htmlspecialchars($selectedCenter); ?>">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonth); ?>">
            <label for="week" class="mb-0 mr-1">Week</label>
            <select id="week" name="week" class="form-control d-inline-block w-auto">
                <?php for ($i = 1; $i <= 52; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($selectedWeek == $i) ? 'selected' : ''; ?>>
                        Week <?php echo $i; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm ml-1">Apply</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!empty($weeklyLowScorers)): ?>
            <table id="lowScoreWeeklyTable" class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Child Name</th>
                        <th>Batch</th>
                        <th>Teacher</th>
                        <th>Submissions</th>
                        <th>Last Submission</th>
                        <th>Expected</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeklyLowScorers as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                            <td><?php echo $row['submission_count']; ?></td>
                            <td><?php echo $row['last_submission'] ? date('d M Y H:i', strtotime($row['last_submission'])) : '-'; ?></td>
                            <td><?php echo $row['expected_submissions']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted text-center">No students found with low scores for the selected week.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Low Scoring Students - Monthly</h5>
        <form method="GET" class="mb-0">
            <input type="hidden" name="center" value="<?php echo htmlspecialchars($selectedCenter); ?>">
            <label for="month" class="mb-0 mr-1">Month</label>
            <select id="month" name="month" class="form-control d-inline-block w-auto">
                <?php
                $currentYear = date('Y');
                $currentMonth = date('n');
                for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                    for ($m = 12; $m >= 1; $m--) {
                        $val = sprintf('%04d-%02d', $y, $m);
                        $selected = ($selectedMonth == $val) ? 'selected' : '';
                        echo "<option value=\"$val\" $selected>" . date('F Y', strtotime("$y-$m-01")) . "</option>";
                    }
                }
                ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm ml-1">Apply</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!empty($monthlyLowScorers)): ?>
            <table id="lowScoreMonthlyTable" class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Child Name</th>
                        <th>Batch</th>
                        <th>Teacher</th>
                        <th>Submissions</th>
                        <th>Last Submission</th>
                        <th>Expected</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyLowScorers as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                            <td><?php echo $row['submission_count']; ?></td>
                            <td><?php echo $row['last_submission'] ? date('d M Y H:i', strtotime($row['last_submission'])) : '-'; ?></td>
                            <td><?php echo $row['expected_submissions']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted text-center">No students found with low scores for the selected month.</p>
        <?php endif; ?>
    </div>
</div>

        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>

<!-- DataTables and Export Buttons -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    // Common configuration for all tables
    const commonConfig = {
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'B>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-sm btn-info mr-1',
                title: function() {
                    return $(this.element).closest('.card').find('.card-title').text();
                },
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-success mr-1',
                title: function() {
                    return $(this.element).closest('.card').find('.card-title').text();
                },
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-danger',
                title: function() {
                    return $(this.element).closest('.card').find('.card-title').text();
                },
                exportOptions: {
                    columns: ':visible'
                }
            }
        ]
    };

    // Teacher Performance Table
    $('#teacherPerformanceTable').DataTable({
        ...commonConfig,
        columnDefs: [{ type: 'num', targets: 3 }],
        order: [[3, 'asc']]
    });

    // Weekly Low Score Table
    $('#lowScoreWeeklyTable').DataTable({
        ...commonConfig,
        order: [[3, 'asc']]
    });

    // Monthly Low Score Table
    $('#lowScoreMonthlyTable').DataTable({
        ...commonConfig,
        order: [[3, 'asc']]
    });

    // Teacher Monthly Performance Table
    $('#teacherMonthlyPerformanceTable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'B>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-sm btn-info mr-1',
                title: 'Teacher Performance (Monthly)',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-success mr-1',
                title: 'Teacher Performance (Monthly)',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-danger',
                title: 'Teacher Performance (Monthly)',
                exportOptions: { columns: ':visible' }
            }
        ],
        order: [[1, 'desc']]
    });
});
</script>
</body>
</html>