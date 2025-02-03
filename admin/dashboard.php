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

// Fetch Total Users by Location
$usersByLocation = [];
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

// Fetch Active vs. Inactive Users
$activeUsers = [];
$activeQuery = "SELECT location, 
       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_users,
       SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_users
FROM users WHERE role = 'parent' GROUP BY location";
$stmt = $db->prepare($activeQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activeUsers[] = $row;
    }
    $stmt->close();
}

// Fetch Readmission Data
$readmissionData = [];
$readmissionQuery = "SELECT location, COUNT(*) AS due_for_readmission FROM users WHERE due_for_readmission = 1 AND role = 'parent' GROUP BY location";
$stmt = $db->prepare($readmissionQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $readmissionData[] = $row;
    }
    $stmt->close();
}

// Fetch Habit Engagement by Location
$habitEngagement = [];
$habitQuery = "SELECT u.location, COUNT(uh.id) AS total_habit_submissions FROM user_habits uh JOIN users u ON uh.user_id = u.id GROUP BY u.location";
$stmt = $db->prepare($habitQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $habitEngagement[] = $row;
    }
    $stmt->close();
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
                <!-- Location-wise Parent/Student Distribution -->
                <div class="col-lg-6 col-md-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5>Parents/Students Distribution by Location</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="locationChart" class="chart-container"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Active vs. Inactive Users -->
                <div class="col-lg-6 col-md-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5>Active vs Inactive Users by Location</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="activeUsersChart" class="chart-container"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Readmission Status -->
                <div class="col-lg-6 col-md-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5>Readmission Analysis by Location</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="readmissionChart" class="chart-container"></canvas>
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
            </div>
        </div>
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<script>
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

    // Active vs. Inactive Users Chart
    new Chart(document.getElementById('activeUsersChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($activeUsers, 'location')); ?>,
            datasets: [
                {
                    label: 'Active Users',
                    data: <?php echo json_encode(array_column($activeUsers, 'active_users')); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                },
                {
                    label: 'Inactive Users',
                    data: <?php echo json_encode(array_column($activeUsers, 'inactive_users')); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                }
            ]
        }
    });

    // Readmission Chart
    new Chart(document.getElementById('readmissionChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($readmissionData, 'location')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($readmissionData, 'due_for_readmission')); ?>,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
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
</script>

</body>
</html>
