<?php
// incharge/review_habit_evidence.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Retrieve incharge ID from session or cookie
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

// Establish database connection
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

// ------------------------------------------------------------
// Handle Approve/Reject logic if the form is submitted
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['submission_id'])) {
    $submissionId = $_POST['submission_id'];
    $action = $_POST['action'];
    $feedback = $_POST['feedback'] ?? '';

    // Update submission status
    if ($action === 'approved') {
        $updateQuery = "UPDATE evidence_uploads SET status = ?, feedback = ?, points = 1 WHERE id = ?";
    } else {
        $updateQuery = "UPDATE evidence_uploads SET status = ?, feedback = ?, points = 0 WHERE id = ?";
    }
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
// Retrieve habit evidence submissions for students under this incharge
// ------------------------------------------------------------
$submissions = [];
$submissionQuery = "
    SELECT eu.id AS submission_id,
           u.full_name AS student_name,
           u.username AS student_username,      -- Add this line
           h.title AS habit_title,
           eu.file_path AS evidence_path,
           eu.status,
           eu.feedback,
           eu.uploaded_at
    FROM evidence_uploads eu
    JOIN users u ON eu.parent_id = u.id
    JOIN habits h ON eu.habit_id = h.id
    WHERE u.batch_id IN (
        SELECT id FROM batches WHERE incharge_id = ?
    )
    ORDER BY eu.uploaded_at DESC
";
$stmt = $db->prepare($submissionQuery);
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
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

    <!-- Ensure light mode is applied -->
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">

    <style>
        .badge-pending { background-color: #ffc107; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        body {
            background-color: #f8f9fa; /* Light mode background */
            color: #212529;
        }
        .card {
            border-radius: 8px;
            background-color: #fff; /* Ensure cards have white background */
        }
        .table {
            background-color: #fff; /* Ensure tables are not dark */
        }
        .table-hover tbody tr:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body class="vertical light"> <!-- Explicitly set light mode -->
<div class="wrapper">
    <!-- Include Navbar & Sidebar -->
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
                        <table id="evidenceTable" class="table table-bordered table-hover">
                            <thead class="thead-light">
                            <tr>
                                <th>Student Name (Username)</th> <!-- Update header -->
                                <th>Habit</th>
                                <th>Evidence</th>
                                <th>Status</th>
                                <th>Feedback</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($submissions as $sub): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($sub['student_name']); ?>
                                        <?php if (!empty($sub['student_username'])): ?>
                                            <span class="text-muted">(<?php echo htmlspecialchars($sub['student_username']); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sub['habit_title']); ?></td>
                                    <td>
                                        <?php if (!empty($sub['evidence_path'])): ?>
                                            <a href="<?php echo CDN_URL . htmlspecialchars($sub['evidence_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                View Evidence
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php echo ($sub['status'] === 'approved') ? 'badge-approved' : 
                                                        (($sub['status'] === 'rejected') ? 'badge-rejected' : 'badge-pending'); ?>">
                                            <?php echo ucfirst($sub['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></td>
                                    <td><?php echo date("Y-m-d H:i:s", strtotime($sub['uploaded_at'])); ?></td>
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
                        <p class="text-muted text-center">No submissions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- DataTables -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#evidenceTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "order": [[5, "desc"]] // Adjust index to match the uploaded_at column, assuming it's the 6th column (0-based index)
        });
    });
</script>
</body>
</html>

