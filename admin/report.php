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

// Fetch centers for the filter dropdown
$centers = [];
$centerQuery = "SELECT DISTINCT location FROM users WHERE role = 'parent'";
$centerStmt = $db->prepare($centerQuery);
$centerStmt->execute();
$centerResult = $centerStmt->get_result();
while ($row = $centerResult->fetch_assoc()) {
    $centers[] = $row['location'];
}
$centerStmt->close();

// Fetch teacher scores (Last Week vs Current Week)
$teacherScores = [];
$teacherSQL = "
    SELECT 
        u.full_name AS teacher_name,
        SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) THEN e.points ELSE 0 END) AS current_week_score,
        SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 1 THEN e.points ELSE 0 END) AS last_week_score
    FROM users u
    JOIN batches b ON u.id = b.teacher_id
    JOIN users p ON p.batch_id = b.id AND p.role = 'parent'
    LEFT JOIN evidence_uploads e ON e.parent_id = p.id
    WHERE u.role = 'teacher'";

if (!empty($selectedCenter)) {
    $teacherSQL .= " AND p.location = ?";
}
$teacherSQL .= " GROUP BY u.id";

$teacherStmt = $db->prepare($teacherSQL);
if (!empty($selectedCenter)) {
    $teacherStmt->bind_param("s", $selectedCenter);
}
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
while ($row = $teacherResult->fetch_assoc()) {
    $teacherScores[] = $row;
}
$teacherStmt->close();

// Ensure variables are initialized
$weeklyLowScorers = [];
$monthlyLowScorers = [];

// ✅ Fetch Weekly Low-Scoring Students (Below 75% in the selected week & location)
$weeklyLowScoreSQL = "
SELECT 
u.full_name AS parent_name, 
b.name AS batch_name, 
t.full_name AS teacher_name, 
COALESCE((SUM(eu.points) / COALESCE((SELECT SUM(e.points) FROM evidence_uploads e WHERE WEEK(e.uploaded_at, 1) = ?), 1)) * 100, 0) AS total_score
FROM users u
JOIN batches b ON u.batch_id = b.id
JOIN users t ON b.teacher_id = t.id
LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id
WHERE u.role = 'parent'
AND WEEK(eu.uploaded_at, 1) = ?
AND u.location = ?  
GROUP BY u.id, b.id, t.id
HAVING total_score < 75 AND total_score IS NOT NULL
ORDER BY total_score ASC";

$weeklyStmt = $db->prepare($weeklyLowScoreSQL);
if ($weeklyStmt) {
    $weeklyStmt->bind_param("iis", $selectedWeek, $selectedWeek, $selectedLocation);
    $weeklyStmt->execute();
    $weeklyResult = $weeklyStmt->get_result();
    while ($row = $weeklyResult->fetch_assoc()) {
        $weeklyLowScorers[] = $row;
    }
    $weeklyStmt->close();
}

// ✅ Fetch Monthly Low-Scoring Students (Below 75% in the selected month & location)
$monthlyLowScoreSQL = "
SELECT 
u.full_name AS parent_name, 
b.name AS batch_name, 
t.full_name AS teacher_name, 
COALESCE((SUM(eu.points) / COALESCE((SELECT SUM(e.points) FROM evidence_uploads e WHERE DATE_FORMAT(e.uploaded_at, '%Y-%m') = ?), 1)) * 100, 0) AS total_score
FROM users u
JOIN batches b ON u.batch_id = b.id
JOIN users t ON b.teacher_id = t.id
LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id
WHERE u.role = 'parent'
AND DATE_FORMAT(eu.uploaded_at, '%Y-%m') = ?
AND u.location = ?  
GROUP BY u.id, b.id, t.id
HAVING total_score < 75 AND total_score IS NOT NULL
ORDER BY total_score ASC";

$monthlyStmt = $db->prepare($monthlyLowScoreSQL);
if ($monthlyStmt) {
    $monthlyStmt->bind_param("sss", $selectedMonth, $selectedMonth, $selectedLocation);
    $monthlyStmt->execute();
    $monthlyResult = $monthlyStmt->get_result();
    while ($row = $monthlyResult->fetch_assoc()) {
        $monthlyLowScorers[] = $row;
    }
    $monthlyStmt->close();
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Admin Reports - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});

        function drawTeacherChart() {
            var data = google.visualization.arrayToDataTable([
                ['Teacher', 'Last Week', 'Current Week'],
                <?php foreach ($teacherScores as $row): ?>
                    ['<?php echo addslashes($row['teacher_name']); ?>', <?php echo $row['last_week_score']; ?>, <?php echo $row['current_week_score']; ?>],
                <?php endforeach; ?>
            ]);

            var options = {
                title: 'Teacher Performance (Last Week vs Current Week)',
                hAxis: { title: 'Teachers' },
                vAxis: { title: 'Total Points' },
                chartArea: { width: '60%', height: '70%' },
                bars: 'vertical'
            };

            var chart = new google.visualization.ColumnChart(document.getElementById('teacherChart'));
            chart.draw(data, options);
        }

        google.charts.setOnLoadCallback(drawTeacherChart);
    </script>
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

            <div id="teacherChart" style="height: 300px;"></div>

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
            <input type="number" id="week" name="week" class="form-control" value="<?php echo $selectedWeek; ?>" min="1" max="52">
        </div>

        <!-- Month Filter -->
        <div class="col-md-3">
            <label for="month">Month</label>
            <input type="month" id="month" name="month" class="form-control" value="<?php echo $selectedMonth; ?>">
        </div>

        <!-- Apply Button -->
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
        </div>
    </div>
</form>

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
                        <th>Total Score (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeklyLowScorers as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                            <td><?php echo round($row['total_score'], 2); ?>%</td>
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
                        <th>Total Score (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyLowScorers as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                            <td><?php echo round($row['total_score'], 2); ?>%</td>
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
</body>
</html>