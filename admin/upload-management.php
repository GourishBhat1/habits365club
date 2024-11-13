<?php
// admin/upload-management.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Fetch all uploads along with user and habit information
$database = new Database();
$db = $database->getConnection();

$query = "SELECT uploads.id, users.username, habits.title, uploads.file_path, uploads.file_type, uploads.uploaded_at,
                 rewards.points, rewards.badges, rewards.certificates
          FROM uploads
          JOIN users ON uploads.user_id = users.id
          JOIN habits ON uploads.habit_id = habits.id
          LEFT JOIN rewards ON uploads.id = rewards.upload_id"; // Assuming rewards are linked to uploads via upload_id
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Uploads Management - Habits Web App</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <!-- Optional: Add styles for image/video thumbnails -->
    <style>
        .upload-thumbnail img, .upload-thumbnail video {
            max-width: 100px;
            max-height: 100px;
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
            <h2 class="page-title">Manage Uploads</h2>
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">All Uploads</h5>
                    <!-- Optionally, add a button to view upload statistics or filters -->
                </div>
                <div class="card-body">
                    <table id="uploadTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Habit</th>
                                <th>File</th>
                                <th>Type</th>
                                <th>Uploaded At</th>
                                <th>Points</th>
                                <th>Badges</th>
                                <th>Certificates</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td class="upload-thumbnail">
                                        <?php if($row['file_type'] === 'image'): ?>
                                            <img src="<?php echo htmlspecialchars($row['file_path']); ?>" alt="Upload">
                                        <?php elseif($row['file_type'] === 'video'): ?>
                                            <video controls>
                                                <source src="<?php echo htmlspecialchars($row['file_path']); ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst($row['file_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['uploaded_at']); ?></td>
                                    <td><?php echo htmlspecialchars($row['points'] ?? '0'); ?></td>
                                    <td><?php echo htmlspecialchars($row['badges'] ?? '0'); ?></td>
                                    <td><?php echo htmlspecialchars($row['certificates'] ?? '0'); ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank" class="btn btn-sm btn-info">View</a>
                                        <a href="delete-upload.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this upload?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- DataTables JS -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#uploadTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
