<?php
// notifications.php

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

// 🔍 **New Notification Logic Based on Habit Submissions**
$query = "
    SELECT 
        h.title AS habit_title,
        eu.status AS habit_status,
        eu.feedback,
        eu.uploaded_at AS timestamp
    FROM evidence_uploads eu
    JOIN habits h ON eu.habit_id = h.id
    WHERE eu.parent_id = ?
    ORDER BY timestamp DESC
    LIMIT 20
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("❌ SQL Error (Fetch Notifications): " . $conn->error);
}
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// ✅ Debugging: Output total notifications found
$notification_count = $notifications->num_rows;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Parent Dashboard - Notifications</title>

  <!-- Including all CSS files -->
  <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
  <style>
    .badge-status {
      font-size: 12px;
      padding: 5px 8px;
      border-radius: 5px;
      color: white;
      font-weight: bold;
    }
    .status-approved { background-color: #28a745; } /* Green */
    .status-rejected { background-color: #dc3545; } /* Red */
    .status-pending { background-color: #ffc107; } /* Yellow */
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
                    <h2 class="page-title">Notifications</h2>
                    <div class="card shadow">
                        <div class="card-header">
                            <strong>Your Notifications</strong>
                        </div>
                        <div class="card-body" data-simplebar style="max-height: 400px;">
                            <?php if ($notification_count > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php while ($row = $notifications->fetch_assoc()): 
                                        $badge_class = "status-pending";
                                        if ($row['habit_status'] == "approved") $badge_class = "status-approved";
                                        elseif ($row['habit_status'] == "rejected") $badge_class = "status-rejected";
                                    ?>
                                        <li class="list-group-item">
                                            <span class="badge badge-status <?php echo $badge_class; ?>">
                                                <?php echo ucfirst($row['habit_status']); ?>
                                            </span>
                                            Your habit submission for "<strong><?php echo htmlspecialchars($row['habit_title']); ?></strong>" was <strong><?php echo ucfirst($row['habit_status']); ?></strong>.
                                            <?php if (!empty($row['feedback'])): ?>
                                                <small class="d-block text-muted">Feedback: <?php echo htmlspecialchars($row['feedback']); ?></small>
                                            <?php endif; ?>
                                            <small class="text-muted d-block"><?php echo $row['timestamp']; ?></small>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No new notifications available.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div> <!-- .col-12 -->
            </div> <!-- .row -->
        </div> <!-- .container-fluid -->
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>