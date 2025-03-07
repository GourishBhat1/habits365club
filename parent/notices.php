<?php
// parent/notices.php

session_start();
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

// Fetch all notices
$query = "SELECT id, title, message, created_at FROM notices ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$notices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Notices - Habits365Club</title>

    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <style>
        .notice-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        }
        .notice-title {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .notice-message {
            font-size: 14px;
            color: #333;
            margin-top: 5px;
        }
        .notice-date {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
            text-align: right;
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
            <h2 class="page-title">Notices</h2>
            <p class="text-muted">📢 Latest updates & announcements.</p>

            <?php if (!empty($notices)): ?>
                <?php foreach ($notices as $notice): ?>
                    <div class="notice-card">
                        <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                        <div class="notice-message"><?php echo nl2br(htmlspecialchars($notice['message'])); ?></div>
                        <div class="notice-date"><?php echo date("F j, Y", strtotime($notice['created_at'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    📭 No notices available at the moment.
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>