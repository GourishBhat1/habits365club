<?php
// teacher/center_masterboard.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Fetch teacher ID & location from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;
$teacher_location = $_SESSION['teacher_location'] ?? null;

if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];

    // ✅ Fetch `id` and `location` from `users` table
    $stmt = $db->prepare("SELECT id, location FROM users WHERE email = ? AND role = 'teacher'");
    if (!$stmt) {
        die("❌ SQL Error: " . $db->error);
    }
    $stmt->bind_param("s", $teacher_email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($teacher_id, $teacher_location);
        $stmt->fetch();

        // ✅ Store in session for later use
        $_SESSION['teacher_id'] = $teacher_id;
        $_SESSION['teacher_location'] = $teacher_location;
    } else {
        die("❌ ERROR: Invalid session. No teacher found with email: " . htmlspecialchars($teacher_email));
    }
    $stmt->close();
}

// ✅ Ensure teacher_location is set
if (!isset($_SESSION['teacher_location']) || empty($_SESSION['teacher_location'])) {
    $_SESSION['teacher_location'] = "Unknown";
}

// Assign session value to variable
$teacher_location = $_SESSION['teacher_location'];

// ------------------------------------------------------------
// Fetch students in the same center (location-based filtering)
// ------------------------------------------------------------
$leaderboardData = [];
$query = "
    SELECT 
        u.full_name AS student_name,
        u.profile_picture AS student_pic,
        b.name AS batch_name,
        COALESCE(SUM(eu.points), 0) AS total_score
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads eu ON eu.parent_id = u.id
        AND WEEK(eu.uploaded_at, 1) = WEEK(CURDATE(), 1) -- ✅ Current week scores
    WHERE u.role = 'parent' 
        AND u.location = ?
    GROUP BY u.id, b.id
    ORDER BY total_score DESC
";

$stmt = $db->prepare($query);
$stmt->bind_param("s", $teacher_location);
$stmt->execute();
$leaderboard = $stmt->get_result();
$stmt->close();

// ✅ Fetch Master of the Week (Highest Score in the Center)
$query = "
    SELECT 
        u.full_name AS student_name, 
        u.profile_picture AS student_pic,  
        b.name AS batch_name,
        COALESCE(SUM(eu.points), 0) AS total_score
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    LEFT JOIN evidence_uploads eu ON u.id = eu.parent_id  
        AND WEEK(eu.uploaded_at, 1) = WEEK(CURDATE(), 1) 
    WHERE u.role = 'parent' 
        AND u.location = ?
    GROUP BY u.id
    ORDER BY total_score DESC
    LIMIT 1
";

$stmt = $db->prepare($query);
$stmt->bind_param("s", $teacher_location);
$stmt->execute();
$master_of_week = $stmt->get_result();
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Center Masterboard - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .leaderboard-filter {
            margin-bottom: 20px;
        }
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        .master-card {
            border: 2px solid #FFD700;
            background-color: #FFF9C4;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Center Masterboard - <?php echo htmlspecialchars($teacher_location); ?></h2>

            <!-- Master of the Week Section -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>Master of the Week - <?php echo htmlspecialchars($teacher_location); ?></strong>
                </div>
                <div class="card-body">
                    <?php if ($master_of_week->num_rows > 0): ?>
                        <div class="master-card">
                            <?php while ($row = $master_of_week->fetch_assoc()): ?>
                                <img src="<?php echo htmlspecialchars($row['student_pic'] ?? 'assets/images/user.png'); ?>" 
                                     alt="Profile" class="profile-img">
                                <h4 class="text-warning">🏅 <?php echo htmlspecialchars($row['student_name']); ?></h4>
                                <p>Batch: <?php echo htmlspecialchars($row['batch_name']); ?></p>
                                <p>Total Score: <strong><?php echo $row['total_score']; ?></strong></p>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No data available for Master of the Week.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leaderboard Table -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>Top Performers - <?php echo htmlspecialchars($teacher_location); ?></strong>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-bordered">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Batch</th>
                                <th>Total Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1; 
                            while ($row = $leaderboard->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($row['student_pic'] ?? 'assets/images/user.png'); ?>" 
                                             alt="Profile" class="profile-img">
                                        <?php echo htmlspecialchars($row['student_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['total_score']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>