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
$selectedBatch = $_GET['batch_id'] ?? '';
$selectedMonth = $_GET['month'] ?? date('Y-m'); // Default to current month

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
    SELECT 
        b.name AS batch_name, 
        COUNT(ht.id) AS total_habits, 
        SUM(CASE WHEN ht.status = 'completed' THEN 1 ELSE 0 END) AS completed_habits,
        SUM(CASE WHEN ht.status = 'pending' THEN 1 ELSE 0 END) AS pending_habits
    FROM batches b
    LEFT JOIN users u ON u.batch_id = b.id
    LEFT JOIN habit_tracking ht ON u.id = ht.user_id
    WHERE DATE_FORMAT(ht.updated_at, '%Y-%m') = ?";

// Apply batch filter if set
if (!empty($selectedBatch)) {
    $batchSQL .= " AND b.id = ?";
}
$batchSQL .= " GROUP BY b.id";

$batchStmt = $db->prepare($batchSQL);
if (!empty($selectedBatch)) {
    $batchStmt->bind_param("si", $selectedMonth, $selectedBatch);
} else {
    $batchStmt->bind_param("s", $selectedMonth);
}

if ($batchStmt) {
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    while ($row = $batchResult->fetch_assoc()) {
        $batchStats[] = $row;
    }
    $batchStmt->close();
}

// Fetch low-scoring students (Below 75 marks in the selected month)
$lowScorers = [];
$lowScoreSQL = "
    SELECT 
        u.full_name AS parent_name, 
        u.email, 
        COALESCE(SUM(eu.points), 0) AS total_score
    FROM users u
    JOIN evidence_uploads eu ON u.id = eu.parent_id
    WHERE u.role = 'parent'
        AND DATE_FORMAT(eu.uploaded_at, '%Y-%m') = ? -- ✅ Monthly filter
    GROUP BY u.id
    HAVING total_score < 75
    ORDER BY total_score ASC";

$lowScoreStmt = $db->prepare($lowScoreSQL);
if ($lowScoreStmt) {
    $lowScoreStmt->bind_param("s", $selectedMonth);
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
                    echo "['No Data', 0, 0],";
                }
                ?>
            ]);

            var options = {
                title: 'Batch Habit Progress (<?php echo $selectedMonth; ?>)',
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
                    <div class="col-md-4">
                        <label>Month</label>
                        <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selectedMonth); ?>">
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                    </div>
                </div>
            </form>

            <div id="batchChart" style="height: 300px;"></div>

            <!-- Low Scoring Students Report -->
            <div class="card shadow mt-4">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">Low Scoring Students (Below 75 Marks in <?php echo $selectedMonth; ?>)</h5>
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