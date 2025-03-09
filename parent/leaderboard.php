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
$stmt = $conn->prepare("SELECT id, location FROM users WHERE username = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_username);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$parent_location = $parent['location'] ?? null;
$stmt->close();

// Validate if parent exists
if (!$parent_id) {
    die("Parent not found.");
}

// Fetch leaderboard (Top parents sorted by total points for the current week)
$query = "
    SELECT 
        u.full_name AS parent_name, 
        u.profile_picture AS parent_pic, 
        CONCAT('Week ', WEEK(CURDATE(), 1)) AS week_number,  
        COALESCE(SUM(e.points), 0) AS total_score
    FROM users u
    LEFT JOIN evidence_uploads e ON u.id = e.parent_id 
        AND WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1)  
    WHERE u.role = 'parent' 
        AND u.location = ?  
    GROUP BY u.id
    ORDER BY total_score DESC
    LIMIT 10
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $parent_location);
$stmt->execute();
$leaderboard = $stmt->get_result();
$stmt->close();

// ✅ Fetch Master of the Week (Highest Score Till Now from Same Location & Show Current Week Score)
$query = "
    SELECT 
        u.full_name AS parent_name, 
        u.profile_picture AS parent_pic,  
        COALESCE(SUM(e.points), 0) AS total_score
    FROM users u
    LEFT JOIN evidence_uploads e ON u.id = e.parent_id  
        AND WEEK(e.uploaded_at, 1) = WEEK(CURDATE(), 1)  -- ✅ Filter only current week scores
    WHERE u.role = 'parent'
        AND u.location = ?  -- ✅ Ensure same location as logged-in parent
    GROUP BY u.id
    HAVING total_score = (
        SELECT MAX(current_week_score) FROM (
            SELECT parent_id, COALESCE(SUM(points), 0) AS current_week_score  
            FROM evidence_uploads  
            WHERE WEEK(uploaded_at, 1) = WEEK(CURDATE(), 1)  
            GROUP BY parent_id
        ) AS scores
    )
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $parent_location);
$stmt->execute();
$master_of_week = $stmt->get_result();
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
    .master-card {
      border: 2px solid #FFD700;
      background-color: #FFF9C4;
      padding: 15px;
      text-align: center;
      border-radius: 10px;
      margin-bottom: 20px;
    }
    .profile-img {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #ddd;
    }
    .leaderboard-img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 1px solid #ddd;
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
            <h2 class="page-title">Masterboard</h2>
            <p class="text-muted">🏆 Showing top 10 parents based on total points.</p>

            <!-- Master of the Week Section -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>Club Master of the Week</strong>
                </div>
                <div class="card-body">
                    <?php if ($master_of_week->num_rows > 0): ?>
                        <div class="row">
                            <?php while ($row = $master_of_week->fetch_assoc()): ?>
                                <div class="col-md-6">
                                    <div class="master-card">
                                        <img src="<?php echo htmlspecialchars($row['parent_pic'] ?? 'assets/images/user.png'); ?>" 
                                             alt="Profile" class="profile-img">
                                        <h4 class="text-warning">🏅 <?php echo htmlspecialchars($row['parent_name']); ?></h4>
                                        <p class="mb-0">Total Score: <strong><?php echo $row['total_score']; ?></strong></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No data available for Master of the Week.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leaderboard Section -->
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
                                    <th>Child Name</th>
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
                                        <td>
                                            <img src="<?php echo htmlspecialchars($row['parent_pic'] ?? 'assets/images/user.png'); ?>" 
                                                 alt="Profile" class="leaderboard-img">
                                            <?php echo htmlspecialchars($row['parent_name']); ?>
                                        </td>
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