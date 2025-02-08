<?php
// parent/dashboard.php

// Start session
session_start();
require_once '../connection.php';

// Check if the parent is authenticated via session or cookie
if (!isset($_SESSION['parent_username']) && !isset($_COOKIE['parent_username'])) {
    header("Location: index.php");
    exit();
}

// Retrieve parent username
$parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch parent ID
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_username);
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

// Fetch total habits count
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
$stmt->execute();
$result = $stmt->get_result();
$leaderboard = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Parent Dashboard - Habits365Club</title>

    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            color: white;
        }
        .card-blue { background-color: #007bff; }
        .card-green { background-color: #28a745; }
        .leaderboard-table {
            width: 100%;
            text-align: left;
        }
        .leaderboard-table th, .leaderboard-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .leaderboard-container {
            width: 100%;
        }

        .text-white {
    color: white !important;
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
            <h2 class="page-title">Welcome, <?php echo htmlspecialchars($parent_username); ?>!</h2>
            
            <!-- Stat Cards -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card stat-card card-blue shadow text-white">
                        <h5 class="text-white">Total Habits Available</h5>
                        <h2 class="text-white"><?php echo $habit_count; ?></h2>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card stat-card card-green shadow text-white">
                        <h5 class="text-white">Uploaded Evidence</h5>
                        <h2 class="text-white"><?php echo $evidence_count; ?></h2>
                    </div>
                </div>
            </div>


            <!-- 🏆 Leaderboard (Full Width) -->
            <div class="card shadow mt-4">
                <div class="card-header">
                    <h5 class="mb-0">🏆 Leaderboard - Top Parents</h5>
                </div>
                <div class="card-body leaderboard-container">
                    <table class="leaderboard-table">
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

        </div>
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
