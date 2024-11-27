<?php
// admin/batch-management.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once '../connection.php';

// Fetch all batches along with assigned teacher's username
$database = new Database();
$db = $database->getConnection();

$query = "SELECT batches.id, batches.name, users.username AS teacher, batches.created_at
          FROM batches LEFT JOIN users ON batches.teacher_id = users.id";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Batch Management - Habits Web App</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <!-- Optional: Include Select2 CSS if needed -->
    <link rel="stylesheet" href="css/select2.min.css">
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
            <h2 class="page-title">Manage Batches</h2>
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">All Batches</h5>
                    <a href="add-batch.php" class="btn btn-primary">Add New Batch</a>
                </div>
                <div class="card-body">
                    <table id="batchTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Batch Name</th>
                                <th>Assigned Teacher</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['teacher'] ?? 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td>
                                        <a href="edit-batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete-batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this batch?');">Delete</a>
                                        <!-- New Manage Parents Button -->
                                        <a href="manage-parents.php?batch_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Manage Parents</a>
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
<!-- Optional: Include Select2 JS if needed -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('#batchTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
