<?php
// admin/certificate-management.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include the database connection
// Adjust the path based on your directory structure
require_once __DIR__ . '/../connection.php';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Initialize variables for messages
$message = '';
$error = '';

// Handle any success or error messages passed via GET parameters
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Fetch all certificates with user information
$query = "SELECT
            certificates.id,
            users.username,
            certificates.milestone,
            certificates.certificate_path,
            certificates.generated_at
          FROM certificates
          JOIN users ON certificates.user_id = users.id
          ORDER BY certificates.generated_at DESC";

$stmt = $db->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Database query failed: " . $db->error);
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Certificate Management - Habits Web App</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .certificate-thumbnail {
            max-width: 150px;
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
            <h2 class="page-title">Manage Certificates</h2>
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">All Certificates</h5>
                    <a href="add-certificate.php" class="btn btn-primary">Add New Certificate</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <table id="certificateTable" class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Milestone</th>
                                <th>Certificate</th>
                                <th>Generated At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['milestone']); ?></td>
                                    <td>
                                        <a href="../<?php echo htmlspecialchars($row['certificate_path']); ?>" target="_blank">
                                            <img src="../<?php echo htmlspecialchars($row['certificate_path']); ?>" alt="Certificate" class="certificate-thumbnail">
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['generated_at']); ?></td>
                                    <td>
                                        <a href="view-certificate.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        <a href="edit-certificate.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete-certificate.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this certificate?');">Delete</a>
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
        $('#certificateTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "columnDefs": [
                { "orderable": false, "targets": 3 }, // Disable ordering on the Certificate column
                { "orderable": false, "targets": 5 }  // Disable ordering on the Actions column
            ]
        });
    });
</script>
</body>
</html>
