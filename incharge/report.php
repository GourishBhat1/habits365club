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

// Prepare batch filter condition for queries (shared for all tables)
$batchCondition = "";
$params = [$incharge_id];
$paramTypes = "i";
if ($selectedBatch !== '') {
    $batchCondition = " AND b.id = ? ";
    $params[] = $selectedBatch;
    $paramTypes .= "i";
}

// Fetch Teacher Performance (Last Week vs Current Week) with difference and order by difference ascending
$teacherScores = [];
$teacherSQL = "
    SELECT 
        u.full_name AS teacher_name,
        SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 1 THEN e.points ELSE 0 END) AS last_week_score,
        SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 2 THEN e.points ELSE 0 END) AS previous_week_score,
        (SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 1 THEN e.points ELSE 0 END) - 
         SUM(CASE WHEN WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1) - 2 THEN e.points ELSE 0 END)) AS score_diff
    FROM users u
    JOIN batch_teachers bt ON u.id = bt.teacher_id
    JOIN batches b ON bt.batch_id = b.id
    JOIN users p ON p.batch_id = b.id AND p.role = 'parent'
    LEFT JOIN evidence_uploads e ON e.parent_id = p.id
    WHERE b.incharge_id = ? AND p.id IS NOT NULL $batchCondition
    GROUP BY u.id
    ORDER BY score_diff ASC";

$teacherStmt = $db->prepare($teacherSQL);
$teacherStmt->bind_param($paramTypes, ...$params);
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
while ($row = $teacherResult->fetch_assoc()) {
    $teacherScores[] = $row;
}
$teacherStmt->close();

// Helper function to calculate number of days in a given week number and year
function getDaysInWeek($week, $year = null) {
    if (!$year) {
        $year = date('Y');
    }
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $start = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $end = $dto->format('Y-m-d');
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $interval = $startDate->diff($endDate);
    return $interval->days + 1;
}

// Helper function to calculate number of days in a given month (YYYY-MM)
function getDaysInMonth($monthYear) {
    $date = DateTime::createFromFormat('Y-m', $monthYear);
    if (!$date) {
        $date = new DateTime();
    }
    return (int)$date->format('t');
}

// Fetch Weekly Low-Scoring Students (Below 75%)
$weeklyLowScorers = [];
$weeklyMaxPoints = 0;
$weeklyYear = date('Y'); // current year for week calculation
$daysInWeek = getDaysInWeek($selectedWeek, $weeklyYear);

// Get count of habits (no longer filtered by batch)
$habitCountQuery = "SELECT COUNT(*) FROM habits";
$habitStmt = $db->prepare($habitCountQuery);
$habitStmt->execute();
$habitStmt->bind_result($habitCount);
$habitStmt->fetch();
$habitStmt->close();

// Total expected submissions = habitCount * daysInWeek
$totalExpectedWeekly = $habitCount * $daysInWeek;

// Prepare parameters for weekly query (filters: week, year, batch)
$weeklyParams = [$totalExpectedWeekly, $totalExpectedWeekly, $selectedWeek, $weeklyYear, ...$params];
$weeklyParamTypes = "ddii" . $paramTypes;

// Fetch weekly low scorers with total expected, total submitted, last submission date
$weeklyLowScoreSQL = "
    SELECT 
        u.full_name AS student_name, 
        b.name AS batch_name, 
        GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ', ') AS teacher_name,
        u.created_at AS date_of_joining,  -- Changed from created_on to created_at
        COUNT(DISTINCT eu.id) AS total_submitted,
        ? AS total_expected,
        ROUND(COALESCE(SUM(eu.points),0) / GREATEST(?,1) * 100, 2) AS total_score,
        MAX(eu.uploaded_at) AS last_submission_date
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    LEFT JOIN batch_teachers bt ON b.id = bt.batch_id
    LEFT JOIN users t ON bt.teacher_id = t.id
    LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id 
        AND WEEK(eu.uploaded_at, 1) = ? AND YEAR(eu.uploaded_at) = ?
    WHERE u.role = 'parent' AND u.status = 'active'
        AND b.incharge_id = ? $batchCondition
    GROUP BY u.id, b.id
    HAVING total_score < 75 AND total_submitted > 0
    ORDER BY total_submitted ASC";

$weeklyStmt = $db->prepare($weeklyLowScoreSQL);
$weeklyStmt->bind_param($weeklyParamTypes, ...$weeklyParams);
$weeklyStmt->execute();
$weeklyResult = $weeklyStmt->get_result();
while ($row = $weeklyResult->fetch_assoc()) {
    $weeklyLowScorers[] = $row;
}
$weeklyStmt->close();

// Fetch Monthly Low-Scoring Students (Below 75%)
$monthlyLowScorers = [];
$daysInMonth = getDaysInMonth($selectedMonth);

$habitCountQuery = "SELECT COUNT(*) FROM habits";
$habitStmt = $db->prepare($habitCountQuery);
$habitStmt->execute();
$habitStmt->bind_result($habitCount);
$habitStmt->fetch();
$habitStmt->close();

// Total expected submissions = habitCount * daysInMonth
$totalExpectedMonthly = $habitCount * $daysInMonth;

// Prepare parameters for monthly query (filters: month, batch)
$monthlyParams = [$totalExpectedMonthly, $totalExpectedMonthly, $selectedMonth, ...$params];
$monthlyParamTypes = "dds" . $paramTypes;

// Fetch monthly low scorers with total expected, total submitted, last submission date
$monthlyLowScoreSQL = "
    SELECT 
        u.full_name AS student_name, 
        b.name AS batch_name, 
        GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ', ') AS teacher_name,
        u.created_at AS date_of_joining,  -- Changed from created_on to created_at
        COUNT(DISTINCT eu.id) AS total_submitted,
        ? AS total_expected,
        ROUND(COALESCE(SUM(eu.points),0) / GREATEST(?,1) * 100, 2) AS total_score,
        MAX(eu.uploaded_at) AS last_submission_date
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    LEFT JOIN batch_teachers bt ON b.id = bt.batch_id
    LEFT JOIN users t ON bt.teacher_id = t.id
    LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id 
        AND DATE_FORMAT(eu.uploaded_at, '%Y-%m') = ?
    WHERE u.role = 'parent' AND u.status = 'active'
        AND b.incharge_id = ? $batchCondition
    GROUP BY u.id, b.id
    HAVING total_score < 75 AND total_submitted > 0
    ORDER BY total_submitted ASC";

$monthlyStmt = $db->prepare($monthlyLowScoreSQL);
$monthlyStmt->bind_param($monthlyParamTypes, ...$monthlyParams);
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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Reports & Analytics</h2>

            <!-- Filters Section -->
            <div id="filters" class="mb-4">
                <form id="filtersForm" method="get" action="report.php" class="form-inline">
                    <div class="form-group mr-3">
                        <label for="batch_id" class="mr-2">Batch:</label>
                        <select name="batch_id" id="batch_id" class="form-control">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo htmlspecialchars($batch['id']); ?>" <?php if ($selectedBatch == $batch['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($batch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-3">
                        <label for="week" class="mr-2">Week:</label>
                        <select name="week" id="week" class="form-control">
                            <?php
                            $currentWeek = date('W');
                            for ($w = 1; $w <= 53; $w++) {
                                $selected = ($selectedWeek == $w) ? 'selected' : '';
                                echo "<option value=\"$w\" $selected>Week $w</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group mr-3">
                        <label for="month" class="mr-2">Month:</label>
                        <input type="month" name="month" id="month" class="form-control" value="<?php echo htmlspecialchars($selectedMonth); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary ml-2">Apply</button>
                </form>
            </div>

            <div class="card shadow mt-4">
                <div class="card-header"><strong>Teacher Performance - Weekly Comparison</strong></div>
                <div class="card-body">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Teacher Name</th>
                                <th>Previous Week Score</th>
                                <th>Last Week Score</th>
                                <th>Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacherScores as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                    <td><?php echo (int)$row['previous_week_score']; ?></td>
                                    <td><?php echo (int)$row['last_week_score']; ?></td>
                                    <td>
                                        <?php 
                                            $diff = (int)$row['score_diff']; 
                                            $arrow = $diff >= 0 ? '↑' : '↓';
                                            $class = $diff >= 0 ? 'text-success' : 'text-danger';
                                            echo "<span class=\"$class\">$arrow $diff</span>";
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow mt-4">
                <div class="card-header"><strong>Low Scoring Students - Weekly</strong></div>
                <div class="card-body">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Child Name</th>
                                <th>Batch</th>
                                <th>Teacher</th>
                                <th>Date of Joining</th> <!-- Added -->
                                <th>Score Achieved</th>
                                <th>Score Out of</th>
                                <th>Last Submission Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weeklyLowScorers as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                    <td>
                                        <?php echo !empty($row['date_of_joining']) ? date('d M Y', strtotime($row['date_of_joining'])) : 'N/A'; ?>
                                    </td>
                                    <td><?php echo (int)($row['total_submitted']); ?></td>
                                    <td><?php echo (int)($row['total_expected']); ?></td>
                                    <td><?php echo $row['last_submission_date'] ? htmlspecialchars(date('Y-m-d', strtotime($row['last_submission_date']))) : 'N/A'; ?></td>
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
                                <th>Date of Joining</th> <!-- Added -->
                                <th>Score Achieved</th>
                                <th>Score Out of</th>
                                <th>Last Submission Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyLowScorers as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                    <td>
                                        <?php echo !empty($row['date_of_joining']) ? date('d M Y', strtotime($row['date_of_joining'])) : 'N/A'; ?>
                                    </td>
                                    <td><?php echo (int)($row['total_submitted']); ?></td>
                                    <td><?php echo (int)($row['total_expected']); ?></td>
                                    <td><?php echo $row['last_submission_date'] ? htmlspecialchars(date('Y-m-d', strtotime($row['last_submission_date']))) : 'N/A'; ?></td>
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

<!-- DataTables & Export Buttons -->
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
    // Initialize DataTables with export buttons
    $('.datatable').each(function() {
        $(this).DataTable({
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
                        return $(this).closest('.card').find('.card-header strong').text();
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
                        return $(this).closest('.card').find('.card-header strong').text();
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
                        return $(this).closest('.card').find('.card-header strong').text();
                    },
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
            pageLength: 25
        });
    });

    // Submit form on filter change
    $('#filters select').change(function() {
        $('#filtersForm').submit();
    });
});
</script>
</body>
</html>