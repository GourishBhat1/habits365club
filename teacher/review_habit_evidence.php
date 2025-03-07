<?php
// teacher/review_habit_evidence.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Instantiate database
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Fetch teacher ID from session or cookie logic
$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];

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
        $error = "An error occurred. Please try again.";
    }
}

if (!$teacher_id) {
    $error = "Invalid session. Please log in again.";
}

// ------------------------------------------------------------
// Handle Approve/Reject logic if the form is submitted
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['submission_id'])) {
    $submissionId = $_POST['submission_id'];
    $action = $_POST['action'];
    $feedback = $_POST['feedback'] ?? '';

    // Update submission status
    $updateQuery = "UPDATE evidence_uploads SET status = ?, feedback = ? WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    if ($updateStmt) {
        $updateStmt->bind_param("ssi", $action, $feedback, $submissionId);
        if ($updateStmt->execute()) {
            $success = "Habit evidence has been {$action} successfully.";
        } else {
            $error = "Failed to update submission.";
        }
        $updateStmt->close();
    } else {
        $error = "Failed to prepare the update statement.";
    }
}

// ------------------------------------------------------------
// Retrieve habit evidence submissions for students under this teacher
// ------------------------------------------------------------
$submissions = []; 
$submissionQuery = "
    SELECT eu.id AS submission_id,
           u.full_name AS parent_name,
           h.title AS habit_title,
           eu.file_path AS evidence_path,
           eu.status,
           eu.feedback,
           eu.uploaded_at
    FROM evidence_uploads eu
    JOIN users u ON eu.parent_id = u.id
    JOIN habits h ON eu.habit_id = h.id
    WHERE u.batch_id IN (
        SELECT id FROM batches WHERE teacher_id = ?
    )
    ORDER BY eu.uploaded_at DESC
";
$stmt = $db->prepare($submissionQuery);
if ($stmt) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $stmt->close();
} else {
    $error = "Failed to fetch habit evidence.";
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Review Habit Evidence - Habits365Club</title>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/responsive.bootstrap4.min.css">

    <style>
        .badge-pending { background-color: #ffc107; color: white; }
        .badge-approved { background-color: #28a745; color: white; }
        .badge-rejected { background-color: #dc3545; color: white; }

        /* DataTable Responsive */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.2em 0.6em;
        }

        /* Fix Dark Mode Issue */
        body.vertical.light {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Review Habit Evidence</h2>

            <!-- Success/Error Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Submissions</strong>
                </div>
                <div class="card-body">
                    <?php if (count($submissions) > 0): ?>
                        <table id="evidenceTable" class="table table-striped table-bordered dt-responsive nowrap">
                            <thead>
                            <tr>
                                <th>Parent Name</th>
                                <th>Habit</th>
                                <th>Evidence</th>
                                <th>Status</th>
                                <th>Feedback</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($submissions as $sub): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sub['parent_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['habit_title']); ?></td>
                                    <td>
                                        <?php if (!empty($sub['evidence_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($sub['evidence_path']); ?>" target="_blank">
                                                View Evidence
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo 'badge-' . strtolower($sub['status']); ?>">
                                            <?php echo ucfirst($sub['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($sub['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="submission_id" value="<?php echo $sub['submission_id']; ?>">
                                                <input type="hidden" name="action" value="approved">
                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                            <button class="btn btn-sm btn-danger" data-toggle="modal"
                                                    data-target="#rejectModal-<?php echo $sub['submission_id']; ?>">
                                                Reject
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No submissions found.</p>
                    <?php endif; ?>
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
<script src="js/dataTables.responsive.min.js"></script>
<script src="js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#evidenceTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "responsive": true
        });
    });
</script>
</body>
</html>
