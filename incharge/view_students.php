<?php
// incharge/view_students.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the incharge is authenticated via session or cookie
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID from session or cookie
$incharge_id = $_SESSION['incharge_id'] ?? null;

if (!$incharge_id && isset($_COOKIE['incharge_username'])) {
    $incharge_username = $_COOKIE['incharge_username'];

    // Fetch incharge ID using username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
    if (!$stmt) {
        die("❌ SQL Error: " . $db->error);
    }
    $stmt->bind_param("s", $incharge_username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($incharge_id);
        $stmt->fetch();
        $_SESSION['incharge_id'] = $incharge_id;
    } else {
        header("Location: index.php?message=invalid_cookie");
        exit();
    }
    $stmt->close();
}

// Get batch ID from URL parameter
$batch_id = $_GET['batch_id'] ?? null;

if (!$batch_id) {
    die("❌ Invalid request. Batch ID missing.");
}

// Verify if the incharge is assigned to this batch
$stmt = $db->prepare("SELECT id FROM batches WHERE id = ? AND incharge_id = ?");
$stmt->bind_param("ii", $batch_id, $incharge_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("❌ Unauthorized access to batch.");
}
$stmt->close();

// Fetch students assigned to the batch
$students = [];
$stmt = $db->prepare("SELECT id, full_name, username, phone, email, standard, course_name FROM users WHERE role = 'parent' AND batch_id = ?");
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();
while ($student = $result->fetch_assoc()) {
    $students[] = $student;
}
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>View Students - Habits365Club</title>
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
        .student-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
        }
        .student-title {
            font-size: 16px;
            font-weight: bold;
        }
        .student-desc {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Students in Batch</h2>

            <div class="card shadow mb-4">
                <div class="card-header">
                    <strong>Assigned Students</strong>
                </div>
                <div class="card-body">
                    <?php if (!empty($students)): ?>
                        <table id="studentsTable" class="table table-hover datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Standard</th>
                                    <th>Course</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['standard'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted text-center">No students found for this batch.</p>
                    <?php endif; ?>
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
        $('#studentsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
