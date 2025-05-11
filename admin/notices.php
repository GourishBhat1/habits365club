<?php
// admin/notices.php

session_start();
require_once '../connection.php';

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch all distinct locations from centers
$location_stmt = $db->prepare("SELECT DISTINCT location FROM centers WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
$location_stmt->execute();
$location_result = $location_stmt->get_result();
$locations = [];
while ($row = $location_result->fetch_assoc()) {
    $locations[] = $row['location'];
}
$location_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['message'], $_POST['location'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $location = $_POST['location'];
    $created_by = $_SESSION['admin_id'] ?? 0;

    if ($location === 'all') {
        $stmt = $db->prepare("INSERT INTO notices (title, message, location, created_by) VALUES (?, ?, ?, ?)");
        foreach ($locations as $centerLoc) {
            $stmt->bind_param("ssss", $title, $message, $centerLoc, $created_by);
            $stmt->execute();
        }
        $stmt->close();
    } else {
        $stmt = $db->prepare("INSERT INTO notices (title, message, location, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $message, $location, $created_by);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: notices.php");
    exit();
}

$notices = [];
$stmt = $db->prepare("SELECT id, title, message, location AS center_name, created_at FROM notices ORDER BY created_at DESC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notices[] = $row;
    }
    $stmt->close();
}

// Handle deletion of notice via GET parameter
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notice_id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM notices WHERE id = ?");
    $stmt->bind_param("i", $notice_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notices.php");
    exit();
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>All Notices - Admin</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">All Notices</h2>

            <!-- Create Notice Form -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <h5 class="card-title">Create New Notice</h5>
                    <form method="POST">
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" class="form-control" name="title" id="title" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea class="form-control" name="message" id="summernote" rows="4" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="location">Target Location</label>
                            <select class="form-control" name="location" id="location" required>
                                <option value="all">All</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Notice</button>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body table-responsive">
                    <table id="noticeTable" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notices as $notice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notice['title']); ?></td>
                                    <td><?php echo $notice['message']; ?></td>
                                    <td><?php echo htmlspecialchars($notice['center_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($notice['created_at'])); ?></td>
                                    <td>
                                        <a href="notices.php?delete=<?php echo $notice['id']; ?>" onclick="return confirm('Are you sure you want to delete this notice?');" class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DataTables Script -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs4.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#noticeTable').DataTable({
            "order": [[3, "desc"]]
        });
        $('#summernote').summernote({
            height: 150
        });
    });
</script>
</body>
</html>