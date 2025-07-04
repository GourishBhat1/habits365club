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
    $userId = $_POST['user_id'];  // Standardized naming
    $newStatus = $_POST['status']; // Standardized naming

    if (!empty($userId) && in_array($newStatus, ['active', 'inactive'])) {
        $database = new Database();
        $db = $database->getConnection();
        
        $updateStmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newStatus, $userId);

        if ($updateStmt->execute()) {
            $updateSuccess = "User status updated successfully.";
        } else {
            $errorMessage = "Error updating user status.";
        }
        $updateStmt->close();
    }
}

// Fetch user counts
$database = new Database();
$db = $database->getConnection();

// Get total users count
$totalUsersQuery = "SELECT COUNT(*) AS total_users FROM users";
$totalStmt = $db->prepare($totalUsersQuery);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalUsers = $totalResult->fetch_assoc()['total_users'];
$totalStmt->close();

// Get active users count
$activeUsersQuery = "SELECT COUNT(*) AS active_users FROM users WHERE status = 'active'";
$activeStmt = $db->prepare($activeUsersQuery);
$activeStmt->execute();
$activeResult = $activeStmt->get_result();
$activeUsers = $activeResult->fetch_assoc()['active_users'];
$activeStmt->close();

// Fetch all users
$query = "SELECT id, full_name, username, email, phone, standard, location AS center_name, course_name, role, created_at, status FROM users ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$userResult = $stmt->get_result();  // Standardized naming

if ($userResult->num_rows === 0) {
    error_log("No users found in the database.");
} else {
    error_log("Users found: " . $userResult->num_rows);
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>User Management - Habits365Club</title>
    
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">

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

            <!-- Filter Section -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="filterRole">Filter by Role</label>
                    <select id="filterRole" class="form-control">
                        <option value="">All Roles</option>
                        <option value="Admin">Admin</option>
                        <option value="Teacher">Teacher</option>
                        <option value="Parent">Parent</option>
                        <option value="Incharge">Incharge</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filterCenter">Filter by Center</label>
                    <select id="filterCenter" class="form-control">
                        <option value="">All Centers</option>
                        <?php
                        $centerQuery = "SELECT DISTINCT location FROM centers ORDER BY location ASC";
                        $centerStmt = $db->prepare($centerQuery);
                        $centerStmt->execute();
                        $centerResult = $centerStmt->get_result();
                        while ($center = $centerResult->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($center['location']) . '">' . htmlspecialchars($center['location']) . '</option>';
                        }
                        $centerStmt->close();
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filterStatus">Filter by Status</label>
                    <select id="filterStatus" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
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
                                <th>Date of Joining</th> <!-- Added column -->
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $userResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($user['standard'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['center_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['course_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $role = strtolower($user['role']);
                                        $badgeClass = ($role === 'admin') ? 'badge-admin' :
                                                      (($role === 'teacher') ? 'badge-teacher' :
                                                      (($role === 'parent') ? 'badge-parent' : 'badge-secondary'));
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $user['status'];
                                        $statusBadge = ($status === 'active') ? 'badge-active' : 'badge-inactive';
                                        ?>
                                        <span class="badge <?php echo $statusBadge; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : 'N/A'; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo ($status === 'active') ? 'inactive' : 'active'; ?>">
                                            <button type="submit" name="update_status" class="btn btn-status btn-sm <?php echo ($status === 'active') ? 'btn-danger' : 'btn-success'; ?>">
                                                <?php echo ($status === 'active') ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                        <a href="edit-user.php?id=<?php echo urlencode($user['id']); ?>" class="btn btn-warning btn-sm">
                                            <i class="fe fe-edit"></i> Edit
                                        </a>
                                        <a href="delete-user.php?id=<?php echo urlencode($user['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?');">
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
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script>
    $(document).ready(function () {
        // Custom search function for exact matching
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            var centerFilter = $('#filterCenter').val();
            var statusFilter = $('#filterStatus').val();
            
            var center = data[6]; // Center column
            var status = data[9]; // Status column - contains the badge text
            
            // If no filters are set, show all rows
            if (!centerFilter && !statusFilter) return true;
            
            // Center exact match
            if (centerFilter && center !== centerFilter) return false;
            
            // Status exact match (need to extract "Active" or "Inactive" from badge text)
            if (statusFilter && !status.includes(statusFilter)) return false;
            
            return true;
        });

        var userTable = $('#userTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'excelHtml5',
                    title: 'User Data',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'csvHtml5',
                    title: 'User Data',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    title: 'User Data',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'print',
                    title: 'User Data',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ]
        });

        // Keep original role filter
        $('#filterRole').on('change', function () {
            userTable.column(8).search(this.value).draw();
        });

        // Update center and status filters to use custom search
        $('#filterCenter, #filterStatus').on('change', function() {
            userTable.draw();
        });
    });
</script>
</body>
</html>
