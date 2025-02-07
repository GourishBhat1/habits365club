<?php
// admin/upload-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Initialize variables
$error = '';
$success = '';

// Handle Approve/Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id']) && isset($_POST['action'])) {
    $submissionId = $_POST['submission_id'];
    $action = $_POST['action']; // 'approved' or 'rejected'
    $feedback = $_POST['feedback'] ?? '';

    // Update evidence status
    $stmt = $db->prepare("UPDATE evidence_uploads SET status = ?, feedback = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssi", $action, $feedback, $submissionId);
        if ($stmt->execute()) {
            $success = "Submission has been $action successfully.";
        } else {
            $error = "Failed to update submission.";
        }
        $stmt->close();
    } else {
        $error = "Error preparing statement.";
    }
}

// Fetch all habit evidence submissions
$submissions = [];
$query = "
    SELECT e.id, u.username AS user_name, h.title AS habit_name, e.file_path, e.status, e.feedback
    FROM evidence_uploads e
    JOIN users u ON e.parent_id = u.id
    JOIN habits h ON e.habit_id = h.id
    ORDER BY e.uploaded_at DESC
";
$stmt = $db->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Upload Management - Admin</title>
    <link rel="stylesheet" href="css/simplebar.css">
    <link rel="stylesheet" href="css/feather.css">
    <link rel="stylesheet" href="css/select2.css">
    <link rel="stylesheet" href="css/dropzone.css">
    <link rel="stylesheet" href="css/uppy.min.css">
    <link rel="stylesheet" href="css/jquery.steps.css">
    <link rel="stylesheet" href="css/jquery.timepicker.css">
    <link rel="stylesheet" href="css/quill.snow.css">
    <link rel="stylesheet" href="css/daterangepicker.css">
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
    <style>
        .badge-pending { background-color: #ffc107; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Upload Management</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Habit Evidence Submissions</strong>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <th>User</th>
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
                                <td><?php echo htmlspecialchars($sub['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['habit_name']); ?></td>
                                <td>
                                    <?php if (!empty($sub['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($sub['status'] === 'approved') {
                                        echo '<span class="badge badge-approved">Approved</span>';
                                    } elseif ($sub['status'] === 'rejected') {
                                        echo '<span class="badge badge-rejected">Rejected</span>';
                                    } else {
                                        echo '<span class="badge badge-pending">Pending</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($sub['feedback'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($sub['status'] === 'pending'): ?>
                                        <!-- Approve button -->
                                        <form action="" method="POST" style="display:inline;">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            <input type="hidden" name="action" value="approved">
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <!-- Reject button -->
                                        <button type="button" class="btn btn-sm btn-danger" data-toggle="modal"
                                                data-target="#rejectModal-<?php echo $sub['id']; ?>">
                                            Reject
                                        </button>

                                        <!-- Reject modal -->
                                        <div class="modal fade" id="rejectModal-<?php echo $sub['id']; ?>" tabindex="-1" role="dialog">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <form action="" method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reject Submission</h5>
                                                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                            <input type="hidden" name="action" value="rejected">
                                                            <div class="form-group">
                                                                <label>Feedback (optional)</label>
                                                                <textarea name="feedback" rows="3" class="form-control"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-danger">Reject</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <em>N/A</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- End card -->
        </div><!-- End container-fluid -->
    </main>
</div><!-- End wrapper -->

<?php include 'includes/footer.php'; ?>
</body>
</html>
