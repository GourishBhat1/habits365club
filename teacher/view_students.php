<?php
// teacher/view_students.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Get batch_id from query parameter
$batch_id = $_GET['batch_id'] ?? null;

if (!$batch_id) {
    die("Invalid batch ID.");
}

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

$error = '';

// Validate access to the batch
$teacher_id = $_SESSION['teacher_id'] ?? $_COOKIE['teacher_id'] ?? null;

$accessStmt = $db->prepare("SELECT 1 FROM batch_teachers WHERE batch_id = ? AND teacher_id = ?");
$accessStmt->bind_param("ii", $batch_id, $teacher_id);
$accessStmt->execute();
$accessStmt->store_result();
if ($accessStmt->num_rows === 0) {
    die("Unauthorized access to this batch.");
}
$accessStmt->close();

// Fetch students (parents) assigned to the batch
$query = "
    SELECT id, full_name AS name, email
    FROM users
    WHERE batch_id = ? AND role = 'parent'
";
$stmt = $db->prepare($query);

if (!$stmt) {
    $error = "Failed to prepare SQL statement: " . $db->error;
} else {
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Students in Batch - Habits365Club</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/select2.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
        }
        .card {
            border-radius: 8px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #ddd;
        }
        .table-hover tbody tr:hover {
            background-color: #f1f1f1;
        }
        .btn-primary, .btn-info {
            color: #fff;
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
            <h2 class="page-title">Students in Batch</h2>
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">All Students in Batch</h5>
                    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
                <div class="card-body">
                    <table id="studentsTable" class="table table-hover table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($student = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <a href="student_profile.php?student_id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">View Profile</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No students found in this batch.</td>
                                </tr>
                            <?php endif; ?>
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
        $('#studentsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
