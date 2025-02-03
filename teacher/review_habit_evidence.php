<?php
// teacher/review_habit_evidence.php

session_start();

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Include your connection or initialization file
require_once '../connection.php';

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
        error_log("Database query failed: " . $db->error);
    }
}

if (!$teacher_id) {
    $error = "Invalid session. Please log in again.";
}

// ------------------------------------------------------------
// Handle Approve/Reject logic if the form is submitted
// ------------------------------------------------------------
if (isset($_POST['action']) && isset($_POST['submission_id'])) {
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
        error_log("Prepare failed: " . $db->error);
    }
}

// ------------------------------------------------------------
// Retrieve habit submissions for this teacher's batches
// ------------------------------------------------------------
$submissions = []; 
$submissionQuery = "
    SELECT eu.id AS submission_id,
           p.name AS parent_name,
           h.title AS habit_title,
           eu.file_path AS evidence_path,
           eu.status,
           eu.feedback,
           eu.upload_date
    FROM evidence_uploads eu
    JOIN users p ON eu.parent_id = p.id
    JOIN habits h ON eu.habit_id = h.id
    WHERE p.batch_id IN (
        SELECT id FROM batches WHERE teacher_id = ?
    )
    ORDER BY eu.upload_date DESC
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
    error_log("Prepare failed: " . $db->error);
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Review Habit Evidence - Habits365Club</title>
    <style>
        .badge-pending { background-color: #ffc107; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Navbar & Sidebar -->
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Review Habit Evidence</h2>

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
                <div class="card-body table-responsive">
                    <?php if (count($submissions) > 0): ?>
                        <table class="table table-bordered table-hover">
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
                                        <?php if ($sub['status'] === 'approved'): ?>
                                            <span class="badge badge-approved">Approved</span>
                                        <?php elseif ($sub['status'] === 'rejected'): ?>
                                            <span class="badge badge-rejected">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($sub['status'] === 'pending'): ?>
                                            <form action="" method="POST" style="display:inline;">
                                                <input type="hidden" name="submission_id" value="<?php echo $sub['submission_id']; ?>">
                                                <input type="hidden" name="action" value="approved">
                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                            <button class="btn btn-sm btn-danger" data-toggle="modal"
                                                    data-target="#rejectModal-<?php echo $sub['submission_id']; ?>">
                                                Reject
                                            </button>

                                            <!-- Modal for reject feedback -->
                                            <div class="modal fade" id="rejectModal-<?php echo $sub['submission_id']; ?>" tabindex="-1" role="dialog">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reject Submission</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <form action="" method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="submission_id" value="<?php echo $sub['submission_id']; ?>">
                                                                <input type="hidden" name="action" value="rejected">
                                                                <div class="form-group">
                                                                    <label>Feedback</label>
                                                                    <textarea name="feedback" class="form-control" rows="3"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="submit" class="btn btn-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
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
</body>
</html>
