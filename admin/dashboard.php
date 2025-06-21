<?php
// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once '../connection.php';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Initialize variables
$error = '';
$success = '';

// ✅ Check Evidence Uploads Folder Size
function getFolderSizeInGB($folder) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder)) as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return round($size / (1024 * 1024 * 1024), 2); // GB
}

$evidenceFolderPath = 'uploads/';
$evidenceFolderSizeGB = 0;
$showEvidenceSizeWarning = false;

if (is_dir($evidenceFolderPath)) {
    $evidenceFolderSizeGB = getFolderSizeInGB($evidenceFolderPath);
    if ($evidenceFolderSizeGB >= 4) { // 🚨 Threshold set to 4GB
        $showEvidenceSizeWarning = true;
    }
}


// ✅ Fetch Total Parents Count
$totalParents = 0;
$activeParents = 0;
$parentCountQuery = "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active FROM users WHERE role = 'parent'";
$stmt = $db->prepare($parentCountQuery);
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($totalParents, $activeParents);
    $stmt->fetch();
    $stmt->close();
}

// ✅ Fetch All Enabled Locations from `centers` Table
$allLocations = [];
$locationQuery = "SELECT location FROM centers WHERE status = 'enabled'";
$locStmt = $db->prepare($locationQuery);
if ($locStmt) {
    $locStmt->execute();
    $locRes = $locStmt->get_result();
    while ($row = $locRes->fetch_assoc()) {
        $allLocations[] = $row['location'];
    }
    $locStmt->close();
}

// ✅ Fetch Total Users by Location (Ensuring Locations Start from 0)
$usersByLocation = array_fill_keys($allLocations, 0);
$usersQuery = "
    SELECT c.location, COUNT(u.id) AS total_users 
    FROM centers c
    LEFT JOIN users u ON u.location = c.location AND u.role = 'parent' AND u.status = 'active'
    WHERE c.status = 'enabled'
    GROUP BY c.location
";
$stmt = $db->prepare($usersQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $usersByLocation[$row['location']] = $row['total_users'];
    }
    $stmt->close();
}

// ✅ Fetch **Average** Habit Submissions per Day per Center over the Last 7 Days
$avgHabitSubmissions = array_fill_keys($allLocations, 0);
$habitQuery = "
    SELECT c.location, 
           ROUND(COUNT(eu.id) / NULLIF(COUNT(DISTINCT u.id), 0), 2) AS avg_weekly_submissions
    FROM centers c
    LEFT JOIN users u ON u.location = c.location AND u.role = 'parent' AND u.status = 'active'
    LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id 
        AND DATE(eu.uploaded_at) >= CURDATE() - INTERVAL 7 DAY
    WHERE c.status = 'enabled'
    GROUP BY c.location
";
$stmt = $db->prepare($habitQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $avgHabitSubmissions[$row['location']] = $row['avg_weekly_submissions'];
    }
    $stmt->close();
}

// ✅ Determine Selected Month
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $selectedMonth = $_GET['month'];
} else {
    $selectedMonth = date('Y-m');
}

$startOfMonth = $selectedMonth . "-01";
$endOfMonth = date("Y-m-t", strtotime($startOfMonth));

// ✅ Fetch Monthly Habit Submissions based on selected month
$monthlyHabitSubmissions = array_fill_keys($allLocations, 0);
$habitMonthQuery = "
    SELECT c.location, 
           ROUND(COUNT(eu.id) / NULLIF(COUNT(DISTINCT u.id), 0), 2) AS monthly_avg_submissions
    FROM centers c
    LEFT JOIN users u ON u.location = c.location AND u.role = 'parent' AND u.status = 'active'
    LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id 
        AND eu.uploaded_at BETWEEN ? AND ?
    WHERE c.status = 'enabled'
    GROUP BY c.location
";
$stmt = $db->prepare($habitMonthQuery);
if ($stmt) {
    $stmt->bind_param('ss', $startOfMonth, $endOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $monthlyHabitSubmissions[$row['location']] = $row['monthly_avg_submissions'];
    }
    $stmt->close();
}

// Fetch data for a given location or for all locations
$location = $_GET['location'] ?? 'ALL';
$stmt = $db->prepare("SELECT date, active_parent_count FROM parent_counts_history WHERE location = ? ORDER BY date ASC");
$stmt->bind_param("s", $location);
$stmt->execute();
$res = $stmt->get_result();

$dates = [];
$counts = [];
while ($row = $res->fetch_assoc()) {
    $dates[] = $row['date'];
    $counts[] = $row['active_parent_count'];
}
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Admin Dashboard - Habits365Club</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

    

    <style>
        .chart-container {
            width: 100%;
            height: 180px;
        }
        .info-card {
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        .info-card h5 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        .info-card h3 {
            margin: 5px 0 0;
            font-size: 22px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body class="vertical light">


<div class="wrapper">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <!-- Evidence Folder Size Alert -->
            <?php if ($showEvidenceSizeWarning): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
              📦 Evidence uploads folder is currently <?php echo $evidenceFolderSizeGB; ?> GB! Please consider clearing old files.
            </div>
            <?php endif; ?>
            <h2 class="page-title">Admin Dashboard</h2>

            <!-- Dashboard Info Alert -->
            <div class="alert alert-info mb-4">
                <strong>How to read the dashboard:</strong>
                <ul class="mb-0">
                    <li><b>Parents Distribution by Location</b>: Shows the number of active parents at each center.</li>
                    <li><b>Weekly Habit Submissions Table</b>: For each location, see the number of active parents and the average number of habit submissions per parent over the last 7 days. The ideal score when all the parents send the habits will be 42.</li>
                    <li><b>Monthly Habit Submissions (by Location)</b>: Shows the average submissions per parent for each location in the selected month.</li>
                </ul>
            </div>

            <div class="row">
                <!-- Total Parents -->
                <div class="col-md-6">
                    <div class="info-card bg-light">
                        <h5>Total Parents</h5>
                        <h3><?php echo $totalParents; ?></h3>
                    </div>
                </div>

                <!-- Active Parents -->
                <div class="col-md-6">
                    <div class="info-card bg-light">
                        <h5>Active Parents</h5>
                        <h3><?php echo $activeParents; ?></h3>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5>Parents Distribution by Location</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="locationChart" class="chart-container"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Habit Submissions Table -->
            <div class="card shadow mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Weekly Habit Submissions (Last 7 Days) by Location</h5>
                </div>
                <div class="card-body">
                    <table id="weeklyHabitTable" class="table table-striped table-bordered datatable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Active Parents</th>
                                <th>Avg Submissions (Last 7 Days)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avgHabitSubmissions as $loc => $avg): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loc); ?></td>
                                    <td><?php echo $usersByLocation[$loc]; ?></td>
                                    <td><?php echo $avg; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Monthly Habit Submissions (by Location)</h5>
                            <form method="GET" action="dashboard.php" class="form-inline">
                              <select name="month" id="monthSelector" class="form-control mr-2" style="width: auto;">
                                <?php
                                  for ($i = 0; $i < 12; $i++) {
                                    $month = date('Y-m', strtotime("-$i month"));
                                    $selected = (isset($_GET['month']) && $_GET['month'] == $month) ? 'selected' : (($i == 0 && !isset($_GET['month'])) ? 'selected' : '');
                                    echo "<option value='$month' $selected>$month</option>";
                                  }
                                ?>
                              </select>
                              <button type="submit" class="btn btn-primary btn-sm">Load</button>
                            </form>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyHabitChart" class="chart-container"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Parents Over Time Chart -->
            <div class="card shadow mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Active Parents Over Time (Location: <?php echo htmlspecialchars($location); ?>)</h5>
                </div>
                <div class="card-body">
                    <canvas id="parentCountChart" class="chart-container"></canvas>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Then DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        $('#weeklyHabitTable').DataTable({
            order: [[2, 'desc']]
        });
    });

    // ✅ Location Chart (Total Parents Per Center)
    new Chart(document.getElementById('locationChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(function($loc, $val) { return "$loc ($val)"; }, array_keys($usersByLocation), array_values($usersByLocation))); ?>,
            datasets: [{
                label: 'Active Parents',
                data: <?php echo json_encode(array_values($usersByLocation)); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // ✅ Avg Habit Submission Chart (Last 7 Days)
    new Chart(document.getElementById('dailyHabitChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(function($loc, $val) { return "$loc ($val)"; }, array_keys($avgHabitSubmissions), array_values($avgHabitSubmissions))); ?>,
            datasets: [{
                label: "Avg Weekly Submissions per Active Parent",
                data: <?php echo json_encode(array_values($avgHabitSubmissions)); ?>,
                backgroundColor: 'rgba(255, 159, 64, 0.6)',
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // ✅ Monthly Habit Submission Chart (PHP-rendered)
    new Chart(document.getElementById('monthlyHabitChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(function($loc, $val) { return "$loc ($val)"; }, array_keys($monthlyHabitSubmissions), array_values($monthlyHabitSubmissions))); ?>,
            datasets: [{
                label: "Avg Submissions per Parent (<?php echo htmlspecialchars($selectedMonth); ?>)",
                data: <?php echo json_encode(array_values($monthlyHabitSubmissions)); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // ✅ Active Parents Over Time Chart
    const ctx = document.getElementById('parentCountChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [{
                label: 'Active Parents',
                data: <?php echo json_encode($counts); ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                fill: false
            }]
        }
    });

</script>

</body>
</html>