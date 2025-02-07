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

// Ensure `location` exists in `users` before running location-based queries
$locationExists = $db->query("SHOW COLUMNS FROM users LIKE 'location'")->num_rows > 0;

// Fetch Total Users by Location (Only if `location` exists)
$usersByLocation = [];
if ($locationExists) {
    $usersQuery = "SELECT location, COUNT(*) AS total_users FROM users WHERE role = 'parent' GROUP BY location";
    $stmt = $db->prepare($usersQuery);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $usersByLocation[] = $row;
        }
        $stmt->close();
    }
}

// Fetch Habit Engagement by Location (Replace `user_habits` with `habit_tracking`)
$habitEngagement = [];
if ($locationExists) {
    $habitQuery = "SELECT u.location, COUNT(ht.id) AS total_habit_submissions 
                   FROM habit_tracking ht 
                   JOIN users u ON ht.user_id = u.id 
                   GROUP BY u.location";
    $stmt = $db->prepare($habitQuery);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $habitEngagement[] = $row;
        }
        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Admin Dashboard - Habits365Club</title>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .chart-container {
            width: 100%;
            height: 350px;
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
            <h2 class="page-title">Admin Dashboard</h2>

            <div class="row">
                <!-- Location-wise Parent Distribution -->
                <?php if ($locationExists): ?>
                    <div class="col-lg-6 col-md-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5>Parents Distribution by Location</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="locationChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Habit Engagement by Location -->
                    <div class="col-lg-6 col-md-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5>Habit Engagement by Location</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="habitEngagementChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<script>
    <?php if ($locationExists): ?>
    // Location Chart
    new Chart(document.getElementById('locationChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($usersByLocation, 'location')); ?>,
            datasets: [{
                label: 'Total Users',
                data: <?php echo json_encode(array_column($usersByLocation, 'total_users')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
            }]
        }
    });

    // Habit Engagement Chart
    new Chart(document.getElementById('habitEngagementChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($habitEngagement, 'location')); ?>,
            datasets: [{
                label: 'Habit Submissions',
                data: <?php echo json_encode(array_column($habitEngagement, 'total_habit_submissions')); ?>,
                backgroundColor: 'rgba(255, 159, 64, 0.6)',
            }]
        }
    });
    <?php endif; ?>
</script>

</body>
</html>
