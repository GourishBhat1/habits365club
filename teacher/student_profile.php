<?php
// teacher/student_profile.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Get teacher ID from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher'");
    if ($stmt) {
        $stmt->bind_param("s", $teacher_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($teacher_id);
            $stmt->fetch();
            $_SESSION['teacher_id'] = $teacher_id;
        } else {
            header("Location: index.php?message=invalid_cookie");
            exit();
        }
        $stmt->close();
    } else {
        die("❌ SQL Error: Unable to verify teacher.");
    }
}

if (!$teacher_id) {
    die("❌ Invalid session. Please log in again.");
}

// Get parent ID (student) from URL
$parent_id = $_GET['student_id'] ?? null;
if (!$parent_id) {
    die("❌ Invalid student ID.");
}

$database = new Database();
$db = $database->getConnection();

// Ensure the student (parent) is assigned to this teacher
$studentQuery = "
    SELECT u.id, u.username, u.email, b.name AS batch_name
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    WHERE u.id = ? AND u.role = 'parent' AND b.teacher_id = ?
";
$stmt = $db->prepare($studentQuery);
$stmt->bind_param("ii", $parent_id, $teacher_id);
$stmt->execute();
$studentResult = $stmt->get_result();
$student = $studentResult->fetch_assoc();
$stmt->close();

if (!$student) {
    die("❌ Unauthorized access or student not found.");
}

// Fetch habit progress for the parent (student) with date column
$habitsQuery = "
    SELECT h.id AS habit_id, h.title AS habit_name, eu.status, eu.file_path AS evidence_path, eu.uploaded_at AS submitted_date
    FROM habits h
    LEFT JOIN evidence_uploads eu ON h.id = eu.habit_id AND eu.parent_id = ?
    ORDER BY eu.uploaded_at DESC
";
$stmt = $db->prepare($habitsQuery);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$habitsResult = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Student Profile - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .profile-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #ffffff;
        }
        .profile-card h5 {
            font-weight: bold;
        }
        .badge-pending { background-color: #ffc107; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        .table-responsive {
            overflow-x: auto;
            background: #ffffff;
            padding: 15px;
            border-radius: 8px;
        }
        .dataTables_wrapper {
            width: 100%;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h2 class="page-title">Student Profile</h2>

                    <div class="card profile-card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title"><?php echo htmlspecialchars($student['username']); ?></h5>
                            <span><?php echo htmlspecialchars($student['email']); ?></span><br>
                            <small><strong>Batch:</strong> <?php echo htmlspecialchars($student['batch_name']); ?></small>
                        </div>
                    </div>

                    <h3 class="mt-4">Habit Progress</h3>
                    <div class="card shadow">
                        <div class="card-body table-responsive">
                            <table id="habitsTable" class="table table-bordered table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Habit</th>
                                        <th>Status</th>
                                        <th>Evidence</th>
                                        <th>Date Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($habit = $habitsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($habit['habit_name']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = "badge-pending";
                                            if ($habit['status'] === 'approved') {
                                                $status_class = "badge-approved";
                                            } elseif ($habit['status'] === 'rejected') {
                                                $status_class = "badge-rejected";
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($habit['status'] ?? 'pending'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($habit['evidence_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($habit['evidence_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    View Evidence
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No evidence uploaded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($habit['submitted_date']) ? date("d M Y, H:i A", strtotime($habit['submitted_date'])) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div> <!-- .card -->

                </div> <!-- .col-12 -->
            </div> <!-- .row -->
        </div> <!-- .container-fluid -->
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
            "order": [[3, "desc"]], // Sort by Date Submitted column (latest first)
            "responsive": true
        });
    });
</script>
</body>
</html>
