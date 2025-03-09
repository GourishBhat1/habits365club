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
$selectedMonth = $_GET['month'] ?? date('Y-m'); // Default to current month

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
    WHERE b.incharge_id = ? 
    AND DATE_FORMAT(ht.updated_at, '%Y-%m') = ?";

if (!empty($selectedBatch)) {
    $batchSQL .= " AND b.id = ?";
}
$batchSQL .= " GROUP BY b.id";

$batchStmt = $db->prepare($batchSQL);
if (!empty($selectedBatch)) {
    $batchStmt->bind_param("isi", $incharge_id, $selectedMonth, $selectedBatch);
} else {
    $batchStmt->bind_param("is", $incharge_id, $selectedMonth);
}

$batchStmt->execute();
$batchResult = $batchStmt->get_result();
while ($row = $batchResult->fetch_assoc()) {
    $batchStats[] = $row;
}
$batchStmt->close();

// Fetch low-scoring students (Below 75 marks in the selected month)
$lowScorers = [];
$lowScoreSQL = "
    SELECT 
        u.full_name AS student_name, 
        u.email, 
        COALESCE(SUM(eu.points), 0) AS total_score
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    JOIN evidence_uploads eu ON u.id = eu.parent_id
    WHERE u.role = 'parent' 
        AND b.incharge_id = ? 
        AND DATE_FORMAT(eu.uploaded_at, '%Y-%m') = ? -- ✅ Monthly filter
    GROUP BY u.id
    HAVING total_score < 75
    ORDER BY total_score ASC";

$lowScoreStmt = $db->prepare($lowScoreSQL);
$lowScoreStmt->bind_param("is", $incharge_id, $selectedMonth);
$lowScoreStmt->execute();
$lowScoreResult = $lowScoreStmt->get_result();
while ($row = $lowScoreResult->fetch_assoc()) {
    $lowScorers[] = $row;
}
$lowScoreStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Incharge Reports - Habits Web App</title>

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
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Total Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowScorers as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
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