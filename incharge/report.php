<?php
// incharge/reports.php

session_start();

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php");
    exit();
}

// Include the database connection
require_once '../connection.php';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Get incharge ID
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

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

// Get filters from GET parameters
$selectedBatch = $_GET['batch_id'] ?? '';
$selectedWeek = $_GET['week'] ?? date('W');
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Fetch batches assigned to this incharge for filtering
$batches = [];
$batchQuery = "SELECT id, name FROM batches WHERE incharge_id = ?";
$batchStmt = $db->prepare($batchQuery);
$batchStmt->bind_param("i", $incharge_id);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();
while ($row = $batchResult->fetch_assoc()) {
    $batches[] = $row;
}
$batchStmt->close();

// Fetch Teacher Performance (Last Week vs Current Week)
$teacherScores = [];
$teacherSQL = "
    SELECT 
        u.full_name AS teacher_name,
        SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) THEN e.points ELSE 0 END) AS current_week_score,
        SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 1 THEN e.points ELSE 0 END) AS last_week_score
    FROM users u
    JOIN batch_teachers bt ON u.id = bt.teacher_id
    JOIN batches b ON bt.batch_id = b.id
    JOIN users p ON p.batch_id = b.id AND p.role = 'parent'
    LEFT JOIN evidence_uploads e ON e.parent_id = p.id
    WHERE b.incharge_id = ?
    GROUP BY u.id";

$teacherStmt = $db->prepare($teacherSQL);
$teacherStmt->bind_param("i", $incharge_id);
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
while ($row = $teacherResult->fetch_assoc()) {
    $teacherScores[] = $row;
}
$teacherStmt->close();

// Fetch Weekly Low-Scoring Students (Below 75%)
$weeklyLowScorers = [];
$weeklyLowScoreSQL = "
    SELECT 
        u.full_name AS student_name, 
        b.name AS batch_name, 
        t.full_name AS teacher_name, 
        COALESCE((SUM(eu.points) / (SELECT SUM(e.points) FROM evidence_uploads e WHERE WEEK(e.uploaded_at, 1) = ?)) * 100, 0) AS total_score
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    LEFT JOIN batch_teachers bt ON b.id = bt.batch_id
    LEFT JOIN users t ON bt.teacher_id = t.id
    LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id
    WHERE u.role = 'parent'
        AND b.incharge_id = ?
        AND WEEK(eu.uploaded_at, 1) = ?
    GROUP BY u.id, b.id, t.id
    HAVING total_score < 75
    ORDER BY total_score ASC";

$weeklyStmt = $db->prepare($weeklyLowScoreSQL);
$weeklyStmt->bind_param("iii", $selectedWeek, $incharge_id, $selectedWeek);
$weeklyStmt->execute();
$weeklyResult = $weeklyStmt->get_result();
while ($row = $weeklyResult->fetch_assoc()) {
    $weeklyLowScorers[] = $row;
}
$weeklyStmt->close();

// Fetch Monthly Low-Scoring Students (Below 75%)
$monthlyLowScorers = [];
$monthlyLowScoreSQL = "
    SELECT 
        u.full_name AS student_name, 
        b.name AS batch_name, 
        t.full_name AS teacher_name, 
        COALESCE((SUM(eu.points) / (SELECT SUM(e.points) FROM evidence_uploads e WHERE DATE_FORMAT(e.uploaded_at, '%Y-%m') = ?)) * 100, 0) AS total_score
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    LEFT JOIN batch_teachers bt ON b.id = bt.batch_id
    LEFT JOIN users t ON bt.teacher_id = t.id
    LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id
    WHERE u.role = 'parent'
        AND b.incharge_id = ?
        AND DATE_FORMAT(eu.uploaded_at, '%Y-%m') = ?
    GROUP BY u.id, b.id, t.id
    HAVING total_score < 75
    ORDER BY total_score ASC";

$monthlyStmt = $db->prepare($monthlyLowScoreSQL);
$monthlyStmt->bind_param("sis", $selectedMonth, $incharge_id, $selectedMonth);
$monthlyStmt->execute();
$monthlyResult = $monthlyStmt->get_result();
while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyLowScorers[] = $row;
}
$monthlyStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Incharge Reports - Habits Web App</title>
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
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Reports & Analytics</h2>

            <div id="teacherChart" style="height: 300px;"></div>

            <div class="card shadow mt-4">
                <div class="card-header"><strong>Low Scoring Students - Weekly</strong></div>
                <div class="card-body">
                    <table class="table table-hover datatable">
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
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                    <td><?php echo round($row['total_score'], 2); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow mt-4">
                <div class="card-header"><strong>Low Scoring Students - Monthly</strong></div>
                <div class="card-body">
                    <table class="table table-hover datatable">
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
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                    <td><?php echo round($row['total_score'], 2); ?>%</td>
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