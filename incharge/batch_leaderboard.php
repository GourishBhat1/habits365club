<?php
// incharge/batch_leaderboard.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Retrieve incharge username
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch incharge ID
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
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

// Fetch leaderboard for the current week, considering **all batches assigned** to this incharge
$query = "
    SELECT 
        u.full_name AS parent_name,
        b.name AS batch_name,
        CONCAT('Week ', WEEK(CURDATE(), 1)) AS week_number,  -- Get Current Week Number
        COALESCE(SUM(e.points), 0) AS total_score  -- Default to 0 if no scores
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads e ON e.parent_id = u.id 
        AND WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1)  -- 🆕 Filter only current week data
    WHERE u.role = 'parent' 
        AND b.incharge_id = ?  -- 🆕 Filter batches assigned to the incharge
    GROUP BY u.id, b.name
    ORDER BY total_score DESC
    LIMIT 10
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $incharge_id);
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
  <title>Incharge Masterboard - Habits365Club</title>

  <!-- CSS -->
  <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
  <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
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
            <h2 class="page-title">Incharge Masterboard</h2>
            <p class="text-muted">🏆 Showing top 10 students across all assigned batches based on weekly points.</p>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Top Performers (Week <?php echo date("W"); ?>)</strong>
                </div>
                <div class="card-body">
                    <?php if ($leaderboard_count > 0): ?>
                        <table id="leaderboardTable" class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student Name</th>
                                    <th>Batch</th>
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
                                        <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
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
                            No masterboard data available for assigned batches.
                        </div>
                    <?php endif; ?>
                </div>
            </div> <!-- .card -->
        </div> <!-- .container-fluid -->
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- DataTables JS -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#leaderboardTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
