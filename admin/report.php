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
$selectedLocation = $_GET['location'] ?? '';
$selectedBatch = $_GET['batch_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Fetch unique locations for the filter
$locations = [];
$locationQuery = "SELECT DISTINCT location FROM users WHERE role = 'parent'";
$locationStmt = $db->prepare($locationQuery);
if ($locationStmt) {
    $locationStmt->execute();
    $locationResult = $locationStmt->get_result();
    while ($row = $locationResult->fetch_assoc()) {
        $locations[] = $row['location'];
    }
    $locationStmt->close();
}

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

// Fetch users by location with filter applied
$usersByLocation = [];
$locationSQL = "SELECT location, COUNT(id) as total FROM users WHERE role = 'parent'";
if (!empty($selectedLocation)) {
    $locationSQL .= " AND location = ?";
}
$locationSQL .= " GROUP BY location";

$locationStmt = $db->prepare($locationSQL);
if (!empty($selectedLocation)) {
    $locationStmt->bind_param("s", $selectedLocation);
}
if ($locationStmt) {
    $locationStmt->execute();
    $locationResult = $locationStmt->get_result();
    while ($row = $locationResult->fetch_assoc()) {
        $usersByLocation[] = $row;
    }
    $locationStmt->close();
}

// Fetch batch & habit statistics with filters
$batchStats = [];
$batchSQL = "
    SELECT b.name AS batch_name, 
           COUNT(uh.id) AS total_habits, 
           SUM(CASE WHEN uh.status = 'completed' THEN 1 ELSE 0 END) AS completed_habits,
           SUM(CASE WHEN uh.status = 'pending' THEN 1 ELSE 0 END) AS pending_habits
    FROM batches b
    LEFT JOIN user_habits uh ON b.id = uh.batch_id
    WHERE 1=1";

if (!empty($selectedBatch)) {
    $batchSQL .= " AND b.id = ?";
}
if (!empty($startDate) && !empty($endDate)) {
    $batchSQL .= " AND uh.created_at BETWEEN ? AND ?";
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

    <!-- Google Charts API -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});

        // Users by Location Chart
        google.charts.setOnLoadCallback(drawLocationChart);
        function drawLocationChart() {
            var data = google.visualization.arrayToDataTable([
                ['Location', 'Number of Users'],
                <?php foreach ($usersByLocation as $row) {
                    echo "['" . addslashes($row['location']) . "', " . $row['total'] . "],";
                } ?>
            ]);

            var options = { title: 'Users by Location', pieHole: 0.4 };
            var chart = new google.visualization.PieChart(document.getElementById('locationChart'));
            chart.draw(data, options);
        }

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
                    <div class="col-md-3">
                        <label>Location</label>
                        <select name="location" class="form-control">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo ($selectedLocation == $location) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
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
                </div>
                <button type="submit" class="btn btn-primary mt-3">Apply Filters</button>
            </form>

            <div id="locationChart" style="height: 300px;"></div>
            <div id="batchChart" style="height: 300px;"></div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
