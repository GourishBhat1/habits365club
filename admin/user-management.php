<?php
// admin/user-management.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once '../connection.php';

// Fetch all users
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, full_name, username, email, phone, standard, location AS center_name, course_name, role, created_at FROM users ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>User Management - Habits365Club</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">

    <style>
        /* Style for user roles */
        .badge-admin { background-color: #007bff; color: white; }
        .badge-teacher { background-color: #28a745; color: white; }
        .badge-parent { background-color: #ffc107; color: black; }

        /* Responsive Design */
        .action-buttons {
            display: flex;
            gap: 5px;
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
            <h2 class="page-title">User Management</h2>

            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">All Users</h5>
                    <a href="add-user.php" class="btn btn-primary">
                        <i class="fe fe-user-plus"></i> Add New User
                    </a>
                </div>
                <div class="card-body">
                    <table id="userTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Standard</th>
                                <th>Center Name</th>
                                <th>Course Name</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['standard'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['center_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $role = strtolower($row['role']);
                                        $badgeClass = ($role === 'admin') ? 'badge-admin' :
                                                      (($role === 'teacher') ? 'badge-teacher' :
                                                      (($role === 'parent') ? 'badge-parent' : 'badge-secondary'));
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($row['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td class="action-buttons">
                                        <a href="edit-user.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-warning btn-sm">
                                            <i class="fe fe-edit"></i> Edit
                                        </a>
                                        <a href="delete-user.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?');">
                                            <i class="fe fe-trash-2"></i> Delete
                                        </a>
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
        $('#userTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
