<?php
// admin/reports.php

// Start session
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
$selectedBatch = $_GET['batch_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Fetch batches for the filter dropdown
$batches = [];
$batchQuery = "SELECT id, name FROM batches";
$batchStmt = $db->prepare($batchQuery);
if ($batchStmt) {
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    while ($row = $batchResult->fetch_assoc()) {
        $batches[] = $row;
    }
    $batchStmt->close();
}

// Fetch batch & habit statistics with filters
$batchStats = [];
$batchSQL = "
    SELECT b.name AS batch_name, 
           COUNT(ht.id) AS total_habits, 
           SUM(CASE WHEN ht.status = 'completed' THEN 1 ELSE 0 END) AS completed_habits,
           SUM(CASE WHEN ht.status = 'pending' THEN 1 ELSE 0 END) AS pending_habits
    FROM batches b
    LEFT JOIN users u ON u.batch_id = b.id
    LEFT JOIN habit_tracking ht ON u.id = ht.user_id
    WHERE 1=1";

if (!empty($selectedBatch)) {
    $batchSQL .= " AND b.id = ?";
}
if (!empty($startDate) && !empty($endDate)) {
    $batchSQL .= " AND ht.updated_at BETWEEN ? AND ?";
}
$batchSQL .= " GROUP BY b.id";

$batchStmt = $db->prepare($batchSQL);
if (!empty($selectedBatch) && !empty($startDate) && !empty($endDate)) {
    $batchStmt->bind_param("iss", $selectedBatch, $startDate, $endDate);
} elseif (!empty($selectedBatch)) {
    $batchStmt->bind_param("i", $selectedBatch);
} elseif (!empty($startDate) && !empty($endDate)) {
    $batchStmt->bind_param("ss", $startDate, $endDate);
}

if ($batchStmt) {
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    while ($row = $batchResult->fetch_assoc()) {
        $batchStats[] = $row;
    }
    $batchStmt->close();
}

// Fetch low-scoring students (Below 75 marks)
$lowScorers = [];
$lowScoreSQL = "
    SELECT u.full_name AS parent_name, u.email, SUM(eu.points) AS total_score
    FROM users u
    JOIN evidence_uploads eu ON u.id = eu.parent_id
    WHERE u.role = 'parent'
    GROUP BY u.id
    HAVING total_score < 75
    ORDER BY total_score ASC";

$lowScoreStmt = $db->prepare($lowScoreSQL);
if ($lowScoreStmt) {
    $lowScoreStmt->execute();
    $lowScoreResult = $lowScoreStmt->get_result();
    while ($row = $lowScoreResult->fetch_assoc()) {
        $lowScorers[] = $row;
    }
    $lowScoreStmt->close();
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Admin Reports - Habits Web App</title>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/buttons.bootstrap4.min.css">

    <!-- Google Charts API -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});

        function drawBatchChart() {
            var data = google.visualization.arrayToDataTable([
                ['Batch', 'Completed', 'Pending'],
                <?php 
                if (!empty($batchStats)) {
                    foreach ($batchStats as $row) {
                        echo "['" . addslashes($row['batch_name']) . "', " . (int)$row['completed_habits'] . ", " . (int)$row['pending_habits'] . "],";
                    }
                } else {
                    echo "['No Data', 0, 0],"; // Prevents chart from breaking
                }
                ?>
            ]);

            var options = {
                title: 'Batch Habit Progress',
                hAxis: { title: 'Batches' },
                vAxis: { title: 'Number of Habits' },
                chartArea: { width: '60%', height: '70%' },
                bars: 'vertical'
            };

            var chart = new google.visualization.ColumnChart(document.getElementById('batchChart'));
            chart.draw(data, options);
        }

        google.charts.setOnLoadCallback(drawBatchChart);
    </script>
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
                <div class="form-row">
                    <div class="col-md-4">
                        <label>Batch</label>
                        <select name="batch_id" class="form-control">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>" <?php echo ($selectedBatch == $batch['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                    </div>
                </div>
            </form>

            <div id="batchChart" style="height: 300px;"></div>

            <!-- Low Scoring Students Report -->
            <div class="card shadow mt-4">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">Low Scoring Students (Below 75 Marks)</h5>
                    <button id="exportLowScore" class="btn btn-danger">Export to Excel</button>
                </div>
                <div class="card-body">
                    <table id="lowScoreTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Child Name</th>
                                <th>Email</th>
                                <th>Total Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowScorers as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
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
</body>
</html>
