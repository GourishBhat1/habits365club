<?php
// incharge/view_habits.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Get incharge ID from session or cookie
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID
$stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$result = $stmt->get_result();
$incharge = $result->fetch_assoc();
$incharge_id = $incharge['id'] ?? null;
$stmt->close();

if (!$incharge_id) {
    die("Incharge not found.");
}

// Get selected batch ID from query string
$selectedBatchId = $_GET['batch_id'] ?? '';

// Fetch habits assigned to students in batches under this incharge
$query = "
    SELECT 
        u.full_name AS student_name,
        h.title AS habit_name,
        eu.status AS habit_status,
        eu.feedback,
        eu.uploaded_at AS timestamp
    FROM evidence_uploads eu
    JOIN users u ON eu.parent_id = u.id
    JOIN habits h ON eu.habit_id = h.id
    WHERE u.batch_id IN (SELECT id FROM batches WHERE incharge_id = ?)
";

$params = [$incharge_id];
$types = "i";

if (!empty($selectedBatchId)) {
    $query .= " AND u.batch_id = ?";
    $params[] = $selectedBatchId;
    $types .= "i";
}

$query .= " ORDER BY timestamp DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$habits = $stmt->get_result();
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>View Habits - Incharge Panel</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Student Habit Progress</h2>

            <div class="card shadow">
                <div class="card-body">
                    <table id="habitsTable" class="table table-hover table-bordered">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Habit Name</th>
                                <th>Status</th>
                                <th>Feedback</th>
                                <th>Uploaded At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $habits->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['habit_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($row['habit_status'] == 'approved') ? 'success' : (($row['habit_status'] == 'rejected') ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($row['habit_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($row['feedback']) ? htmlspecialchars($row['feedback']) : '<em>No feedback</em>'; ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($row['timestamp'])); ?></td>
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
        $('#habitsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "order": [[4, "desc"]],  // Ensure column index corresponds to the timestamp field
            "columnDefs": [{
                "targets": 4, // Adjust index for timestamp column
                "type": "datetime"
            }]
        });
    });
</script>
</body>
</html>
