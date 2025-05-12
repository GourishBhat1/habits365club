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
LEFT JOIN users t ON bt.teacher_id = t.id
LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
WHERE u.role = 'parent' AND u.status = 'active'
  AND WEEK(eu.uploaded_at, 1) = ?" . ($selectedCenter ? " AND u.location = ?" : "") . "
GROUP BY u.id
HAVING submission_count < expected_submissions
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
LEFT JOIN users t ON bt.teacher_id = t.id
LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
WHERE u.role = 'parent' AND u.status = 'active' " . ($selectedCenter ? " AND u.location = ?" : "") . "
GROUP BY u.id
HAVING submission_count < expected_submissions AND 
       MAX(DATE_FORMAT(eu.uploaded_at, '%Y-%m')) = ?
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
if (!isset($teacherScores)) {
    $teacherScores = [];
}
$teacherSQL = "
SELECT 
    u.full_name AS teacher_name,
    SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 1 THEN e.points ELSE 0 END) AS current_week_score,
    SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 2 THEN e.points ELSE 0 END) AS last_week_score
FROM users u
JOIN batch_teachers bt ON u.id = bt.teacher_id
JOIN batches b ON bt.batch_id = b.id
JOIN users p ON p.batch_id = b.id AND p.role = 'parent'
LEFT JOIN evidence_uploads e ON e.parent_id = p.id
WHERE u.role = 'teacher'
";
if ($selectedCenter) {
    $teacherSQL .= " AND p.location = ?";
}
$teacherSQL .= "
GROUP BY u.id";
if ($selectedCenter) {
    $teacherStmt = $db->prepare($teacherSQL);
    $teacherStmt->bind_param("s", $selectedCenter);
} else {
    $teacherStmt = $db->prepare($teacherSQL);
}
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
while ($row = $teacherResult->fetch_assoc()) {
    $row['diff'] = $row['current_week_score'] - $row['last_week_score'];
    $teacherScores[] = $row;
}

// Sort by 'diff' ascending
usort($teacherScores, function($a, $b) {
    return $a['diff'] <=> $b['diff'];
});

$teacherStmt->close();

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

            <!-- Filters -->
<form method="GET" class="mb-4">
    <div class="form-row align-items-end">
        <!-- Center Filter -->
        <div class="col-md-4">
            <label for="center">Center</label>
            <select name="center" id="center" class="form-control">
                <option value="">All Centers</option>
                <?php foreach ($centers as $center): ?>
                    <option value="<?php echo $center; ?>" <?php echo ($selectedCenter == $center) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($center); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Week Filter -->
        <div class="col-md-3">
            <label for="week">Week</label>
            <select id="week" name="week" class="form-control">
                <?php
                for ($i = 1; $i <= 52; $i++) {
                    $selected = ($selectedWeek == $i) ? 'selected' : '';
                    echo "<option value=\"$i\" $selected>Week $i</option>";
                }
                ?>
            </select>
        </div>

        <!-- Month Filter -->
        <div class="col-md-3">
            <label for="month">Month</label>
            <select id="month" name="month" class="form-control">
                <?php
                $currentMonth = new DateTime();
                for ($i = 0; $i < 12; $i++) {
                    $monthOption = $currentMonth->format('Y-m');
                    $selected = ($selectedMonth == $monthOption) ? 'selected' : '';
                    echo "<option value=\"$monthOption\" $selected>" . $currentMonth->format('F Y') . "</option>";
                    $currentMonth->modify('-1 month');
                }
                ?>
            </select>
        </div>

        <!-- Apply Button -->
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
        </div>
    </div>
</form>

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
                                    <th>Last Week Score</th>
                                    <th>Current Week Score</th>
                                    <th>Difference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($teacherScores as $row):
                                    $diff = $row['current_week_score'] - $row['last_week_score'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                        <td><?php echo $row['last_week_score']; ?></td>
                                        <td><?php echo $row['current_week_score']; ?></td>
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

<div class="card shadow mt-4">
    <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Low Scoring Students - Weekly</h5>
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
    <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Low Scoring Students - Monthly</h5>
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
});
</script>
</body>
</html>