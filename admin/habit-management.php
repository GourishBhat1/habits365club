<?php
// admin/habit-management.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Fetch all habits along with user information
$database = new Database();
$db = $database->getConnection();

$query = "SELECT
            habits.id,
            users.username,
            habits.title,
            habits.description,
            habits.frequency,
            habits.streak,
            habits.created_at
          FROM habits
          JOIN users ON habits.user_id = users.id
          ORDER BY habits.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Habit Management - Habits Web App</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <!-- Optional: Add styles for better presentation -->
    <style>
        .card-header .btn {
            float: right;
        }
        .streak-badge {
            background-color: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 12px;
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
            <h2 class="page-title">Manage Habits</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">All Habits</h5>
                    <a href="add-habit.php" class="btn btn-primary">Add New Habit</a>
                </div>
                <div class="card-body">
                    <?php
                    // Display success or error messages if any via GET parameters
                    if (isset($_GET['msg'])):
                        ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($_GET['msg']); ?>
                        </div>
                    <?php
                    endif;
                    if (isset($_GET['error'])):
                        ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php
                    endif;
                    ?>
                    <table id="habitTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Frequency</th>
                                <th>Streak</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo htmlspecialchars($row['frequency']); ?></td>
                                    <td>
                                        <?php if($row['streak'] > 0): ?>
                                            <span class="streak-badge"><?php echo htmlspecialchars($row['streak']); ?> Day<?php echo $row['streak'] > 1 ? 's' : ''; ?> Streak</span>
                                        <?php else: ?>
                                            <span>No Streak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td>
                                        <a href="edit-habit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete-habit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this habit?');">Delete</a>
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
<!-- Initialize DataTables -->
<script>
    $(document).ready(function () {
        $('#habitTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "columnDefs": [
                { "orderable": false, "targets": 7 } // Disable ordering on the Actions column
            ]
        });
    });
</script>
</body>
</html>
