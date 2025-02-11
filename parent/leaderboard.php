<?php
// leaderboard.php

// Start session
session_start();

// Include database connection
require_once '../connection.php';

// Check if the parent is authenticated
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

// Fetch leaderboard (Top parents sorted by total points)
$query = "
    SELECT u.username AS parent_name, COALESCE(SUM(e.points), 0) AS total_score
    FROM users u
    LEFT JOIN evidence_uploads e ON u.id = e.parent_id
    WHERE u.role = 'parent'
    GROUP BY u.id
    ORDER BY total_score DESC
    LIMIT 10
";

$stmt = $conn->prepare($query);
$stmt->execute();
$leaderboard = $stmt->get_result();
$stmt->close();

// ✅ Total leaderboard records count
$leaderboard_count = $leaderboard->num_rows;
?>

<!doctype html>
<html lang="en">
<head>
  <?php include 'includes/header.php'; ?>
  <title>Parent Dashboard - Masterboard</title>

  <!-- CSS -->
  <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
  <style>
    .badge-rank {
      font-size: 14px;
      padding: 5px 10px;
      border-radius: 50%;
      color: white;
      font-weight: bold;
    }
    .rank-1 { background-color: #FFD700; } /* Gold */
    .rank-2 { background-color: #C0C0C0; } /* Silver */
    .rank-3 { background-color: #CD7F32; } /* Bronze */
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
            <h2 class="page-title">Masterboard</h2>
            <p class="text-muted">🏆 Showing top 10 parents based on total points.</p>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Top Performers</strong>
                </div>
                <div class="card-body">
                    <?php if ($leaderboard_count > 0): ?>
                        <table class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Parent Name</th>
                                    <th>Total Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rank = 1;
                                while ($row = $leaderboard->fetch_assoc()):
                                    $rank_class = ($rank == 1) ? "rank-1" : (($rank == 2) ? "rank-2" : (($rank == 3) ? "rank-3" : ""));
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-rank <?php echo $rank_class; ?>">
                                                <?php echo $rank; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                        <td><?php echo $row['total_score']; ?></td>
                                    </tr>
                                <?php
                                    $rank++;
                                endwhile;
                                ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            No masterboard data available.
                        </div>
                    <?php endif; ?>
                </div>
            </div> <!-- .card -->
        </div> <!-- .container-fluid -->
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
