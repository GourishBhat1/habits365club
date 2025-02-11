<?php
// admin/user-management.php

session_start();
require_once '../connection.php';

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$update_success = "";
$error_message = "";

// Handle User Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['status'];

    if (!empty($user_id) && in_array($new_status, ['active', 'inactive'])) {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);

        if ($stmt->execute()) {
            $update_success = "User status updated successfully.";
        } else {
            $error_message = "Error updating user status.";
        }
        $stmt->close();
    }
}

// Fetch user counts
$database = new Database();
$db = $database->getConnection();

// Get total users count
$totalUsersQuery = "SELECT COUNT(*) AS total_users FROM users";
$stmt = $db->prepare($totalUsersQuery);
$stmt->execute();
$totalUsersResult = $stmt->get_result()->fetch_assoc();
$totalUsers = $totalUsersResult['total_users'];
$stmt->close();

// Get active users count
$activeUsersQuery = "SELECT COUNT(*) AS active_users FROM users WHERE status = 'active'";
$stmt = $db->prepare($activeUsersQuery);
$stmt->execute();
$activeUsersResult = $stmt->get_result()->fetch_assoc();
$activeUsers = $activeUsersResult['active_users'];
$stmt->close();

// Fetch all users
$query = "SELECT id, full_name, username, email, phone, standard, location AS center_name, course_name, role, created_at, status FROM users ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>User Management - Habits365Club</title>
    
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">

    <style>
        .badge-admin { background-color: #007bff; color: white; }
        .badge-teacher { background-color: #28a745; color: white; }
        .badge-parent { background-color: #ffc107; color: black; }

        /* Status Badge */
        .badge-active { background-color: #28a745; color: white; }
        .badge-inactive { background-color: #dc3545; color: white; }

        /* Button Styling */
        .btn-status {
            min-width: 90px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        /* Dashboard Cards */
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            color: white;
        }
        .card-blue { background-color: #007bff; }
        .card-green { background-color: #28a745; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">User Management</h2>

            <!-- Success/Error Messages -->
            <?php if (!empty($update_success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($update_success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- User Count Cards -->
            <div class="row mb-4 text-white">
                <div class="col-md-6">
                    <div class="card stat-card card-blue shadow">
                        <h5>Total Users</h5>
                        <h2><?php echo $totalUsers; ?></h2>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card stat-card card-green shadow">
                        <h5>Active Users</h5>
                        <h2><?php echo $activeUsers; ?></h2>
                    </div>
                </div>
            </div>

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
                                <th>Status</th>
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
                                    <td>
                                        <?php
                                        $status = $row['status'];
                                        $statusBadge = ($status === 'active') ? 'badge-active' : 'badge-inactive';
                                        ?>
                                        <span class="badge <?php echo $statusBadge; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo ($status === 'active') ? 'inactive' : 'active'; ?>">
                                            <button type="submit" name="update_status" class="btn btn-status btn-sm <?php echo ($status === 'active') ? 'btn-danger' : 'btn-success'; ?>">
                                                <?php echo ($status === 'active') ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
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
