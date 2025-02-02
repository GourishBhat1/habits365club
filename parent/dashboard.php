<?php
// parent/dashboard.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the parent is authenticated via session or cookie
if (!isset($_SESSION['parent_email']) && !isset($_COOKIE['parent_email'])) {
    echo "<p>❌ Debug: No authentication found. Redirecting...</p>";
    header("Location: index.php");
    exit();
}

// Retrieve parent email
$parent_email = $_SESSION['parent_email'] ?? $_COOKIE['parent_email'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}

// Fetch parent ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_email);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$stmt->close();

// Validate if parent exists
if (!$parent_id) {
    die("Parent not found.");
}

// Fetch total evidence uploads by this parent
$stmt = $conn->prepare("SELECT COUNT(*) AS evidence_count FROM evidence_uploads WHERE parent_id = ?");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
$evidence_data = $result->fetch_assoc();
$evidence_count = $evidence_data['evidence_count'] ?? 0;
$stmt->close();

// Fetch all available habits count
$stmt = $conn->prepare("SELECT COUNT(*) AS habit_count FROM habits");
$stmt->execute();
$result = $stmt->get_result();
$habit_data = $result->fetch_assoc();
$habit_count = $habit_data['habit_count'] ?? 0;
$stmt->close();

// 🏆 Leaderboard Data (Top Parents by Approved Evidence)
$stmt = $conn->prepare("
    SELECT u.id, u.username, COUNT(e.id) AS score
    FROM users u
    LEFT JOIN evidence_uploads e ON u.id = e.parent_id AND e.status = 'approved'
    WHERE u.role = 'parent'
    GROUP BY u.id
    ORDER BY score DESC
");

if (!$stmt) {
    die("❌ SQL Error: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
$leaderboard = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Parent Dashboard - Habits365Club</title>

    <!-- CSS Includes -->
    <link rel="stylesheet" href="css/simplebar.css">
    <link rel="stylesheet" href="css/feather.css">
    <link rel="stylesheet" href="css/select2.css">
    <link rel="stylesheet" href="css/daterangepicker.css">
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
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
            <div class="row justify-content-center">
                <div class="col-12">
                    <h2 class="page-title">Parent Dashboard</h2>
                    <div class="row">
                        <!-- Stat Cards -->
                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <h6 class="mb-0">Total Habits Available</h6>
                                    <h3><?php echo $habit_count; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <h6 class="mb-0">Uploaded Evidence</h6>
                                    <h3><?php echo $evidence_count; ?></h3>
                                </div>
                            </div>
                        </div>

                        <!-- 🏆 Leaderboard -->
                        <div class="col-lg-6">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">Leaderboard</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Parent Name</th>
                                            <th>Score</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($leaderboard)): ?>
                                                <?php $rank = 1; ?>
                                                <?php foreach ($leaderboard as $parent): ?>
                                                    <tr>
                                                        <td><?php echo $rank++; ?></td>
                                                        <td><?php echo htmlspecialchars($parent['username']); ?></td>
                                                        <td><?php echo htmlspecialchars($parent['score']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No leaderboard data available.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div> <!-- End Leaderboard -->

                    </div>

                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>