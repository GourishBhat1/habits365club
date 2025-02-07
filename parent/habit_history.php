<?php
// habit_history.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the parent is authenticated
if (!isset($_SESSION['parent_email']) && !isset($_COOKIE['parent_email'])) {
    header("Location: index.php");
    exit();
}

// Retrieve parent email
$parent_email = $_SESSION['parent_email'] ?? $_COOKIE['parent_email'];

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Validate database connection
if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}

// Fetch parent ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'parent'");
if (!$stmt) {
    die("❌ SQL Error (Fetch Parent ID): " . $conn->error);
}
$stmt->bind_param("s", $parent_email);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$stmt->close();

// Validate if parent exists
if (!$parent_id) {
    die("❌ Parent not found.");
}

// Fetch habit history with points included
$query = "
    SELECT e.uploaded_at AS date, h.title AS habit, e.status, e.feedback, e.points
    FROM evidence_uploads e
    JOIN habits h ON e.habit_id = h.id
    WHERE e.parent_id = ?
    ORDER BY e.uploaded_at DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("❌ SQL Error (Fetch Habit History): " . $conn->error);
}

$stmt->bind_param("i", $parent_id);
$stmt->execute();
$habit_history = $stmt->get_result();
$stmt->close();

// ✅ Debugging: Output total habits found
$habit_count = $habit_history->num_rows;

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Parent Dashboard - Habit History</title>

  <!-- Including all CSS files -->
  <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
  <style>
    .alert {
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      text-align: center;
    }
    .alert-info {
      background-color: #d1ecf1;
      color: #0c5460;
    }
    .badge-success { background-color: #28a745; }
    .badge-warning { background-color: #ffc107; }
    .badge-danger { background-color: #dc3545; }
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
            <div class="row justify-content-center">
                <div class="col-12">
                    <h2 class="page-title">Habit History</h2>

                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong>Your Past Habit Submissions</strong>
                        </div>
                        <div class="card-body" data-simplebar style="max-height:400px;">
                            <?php if ($habit_count > 0): ?>
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Habit</th>
                                            <th>Status</th>
                                            <th>Feedback (if rejected)</th>
                                            <th>Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $habit_history->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                                <td><?php echo htmlspecialchars($row['habit']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = "badge-warning";
                                                    if ($row['status'] === 'approved') {
                                                        $status_class = "badge-success";
                                                    } elseif ($row['status'] === 'rejected') {
                                                        $status_class = "badge-danger";
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo !empty($row['feedback']) ? htmlspecialchars($row['feedback']) : "-"; ?></td>
                                                <td><?php echo $row['points']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No habit history found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div> 
            </div> 
        </div> 
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>