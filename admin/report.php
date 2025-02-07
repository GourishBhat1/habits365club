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

// Fetch batches for the filter
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

        // Batch Habit Progress Chart
        google.charts.setOnLoadCallback(drawBatchChart);
        function drawBatchChart() {
            var data = google.visualization.arrayToDataTable([
                ['Batch', 'Completed', 'Pending'],
                <?php foreach ($batchStats as $row) {
                    echo "['" . addslashes($row['batch_name']) . "', " . $row['completed_habits'] . ", " . $row['pending_habits'] . "],";
                } ?>
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

            <!-- Data Table for Export -->
            <div class="card shadow mt-4">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">Detailed Habit Tracking Report</h5>
                    <button id="exportExcel" class="btn btn-success">Export to Excel</button>
                </div>
                <div class="card-body">
                    <table id="reportsTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Batch Name</th>
                                <th>Total Habits</th>
                                <th>Completed Habits</th>
                                <th>Pending Habits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batchStats as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['total_habits']); ?></td>
                                    <td><?php echo htmlspecialchars($row['completed_habits']); ?></td>
                                    <td><?php echo htmlspecialchars($row['pending_habits']); ?></td>
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

<!-- DataTables and Export Buttons -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script src="js/dataTables.buttons.min.js"></script>
<script src="js/buttons.bootstrap4.min.js"></script>
<script src="js/jszip.min.js"></script>
<script src="js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function () {
        // Initialize DataTables with Export buttons
        var table = $('#reportsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Export to Excel',
                    title: 'Habit Tracking Report'
                }
            ]
        });

        $('#exportExcel').on('click', function() {
            table.button('.buttons-excel').trigger();
        });
    });
</script>
</body>
</html>
