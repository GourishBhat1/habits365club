<?php
// teacher/meets.php

// Start session
session_start();

// Check if the teacher is authenticated via session or cookie
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Include the database connection
require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch teacher ID from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;

if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];

    // Prepare statement to get teacher ID based on email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher'");
    if ($stmt) {
        $stmt->bind_param("s", $teacher_email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($teacher_id);
            $stmt->fetch();
            $_SESSION['teacher_id'] = $teacher_id;
        } else {
            // Invalid cookie, redirect to login
            header("Location: index.php?message=invalid_cookie");
            exit();
        }
        $stmt->close();
    } else {
        // SQL prepare failed
        $error = "An error occurred. Please try again later.";
        error_log("Database query failed: " . $db->error);
    }
}

if ($teacher_id) {
    // Fetch all meet links (latest first) regardless of validity
    $meetsQuery = "SELECT id, meet_link, class_date, class_time FROM batch_meet_links WHERE teacher_id = ? ORDER BY class_date DESC, class_time DESC";
    $meetsStmt = $db->prepare($meetsQuery);
    if ($meetsStmt) {
        $meetsStmt->bind_param("i", $teacher_id);
        $meetsStmt->execute();
        $meetsResult = $meetsStmt->get_result();
        $meetsStmt->close();
    } else {
        $error = "Failed to retrieve meet links.";
        error_log("Prepare failed: " . $db->error);
    }
} else {
    $error = "Invalid session. Please log in again.";
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Manage Meet Links - Habits365Club</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
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
            <h2 class="page-title">Manage Meet Links</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <a href="add-meet.php" class="btn btn-primary mb-3">Add New Meet Link</a>
            <?php if (isset($meetsResult) && $meetsResult->num_rows > 0): ?>
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title">List of Meet Links</h5>
                    </div>
                    <div class="card-body">
                        <table id="meetLinksTable" class="table table-hover datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Meet Link</th>
                                    <th>Class Date</th>
                                    <th>Class Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($meet = $meetsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($meet['id']); ?></td>
                                        <td><a href="<?php echo htmlspecialchars($meet['meet_link']); ?>" target="_blank">Join Meet</a></td>
                                        <td><?php echo htmlspecialchars($meet['class_date']); ?></td>
                                        <td><?php echo htmlspecialchars($meet['class_time']); ?></td>
                                        <td>
                                            <a href="edit-meet.php?id=<?php echo urlencode($meet['id']); ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <a href="delete-meet.php?meet_id=<?php echo urlencode($meet['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this meet link?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p>No meet links found. <a href="add-meet.php">Add a new meet link</a>.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Initialize DataTables -->
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#meetLinksTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
