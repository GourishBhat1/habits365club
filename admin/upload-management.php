<?php
// admin/upload-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include DB connection or other necessary files
// require_once '../connection.php';

$error = '';
$success = '';

// $database = new Database();
// $db = $database->getConnection();

// Handle Approve/Reject submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submission_id']) && isset($_POST['action'])) {
        $submissionId = $_POST['submission_id'];
        $action = $_POST['action']; // 'approved' or 'rejected'
        $feedback = $_POST['feedback'] ?? '';

        // Example: update your user_habits or evidence table
        // $stmt = $db->prepare("UPDATE user_habits SET status = ?, feedback = ? WHERE id = ?");
        // ...
        $success = "Submission has been $action successfully (placeholder).";
    }
}

// Fetch all or filtered pending submissions
// Example placeholder data:
$submissions = [
    [
        'id' => 101,
        'user_name' => 'John Doe',
        'habit_name' => 'Daily Reading',
        'batch_name' => 'Batch A',
        'evidence_path' => 'uploads/john_reading.jpg',
        'status' => 'pending',
        'feedback' => '',
    ],
    [
        'id' => 102,
        'user_name' => 'Sarah Lee',
        'habit_name' => 'Morning Exercise',
        'batch_name' => 'Batch B',
        'evidence_path' => 'uploads/sarah_exercise.mp4',
        'status' => 'rejected',
        'feedback' => 'Video too dark',
    ],
    // ...
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload Management - Admin</title>

    <!-- Include CSS from admin/dashboard.php -->
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
    <!-- Navbar & Sidebar -->
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Upload Management</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
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
                            <th>Batch</th>
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
                                <td><?php echo htmlspecialchars($sub['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['habit_name']); ?></td>
                                <td>
                                    <?php if (!empty($sub['evidence_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($sub['evidence_path']); ?>" target="_blank">View</a>
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
                                <td><?php echo htmlspecialchars($sub['feedback']); ?></td>
                                <td>
                                    <?php if ($sub['status'] === 'pending'): ?>
                                        <!-- Approve button -->
                                        <form action="" method="POST" style="display:inline;">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            <input type="hidden" name="action" value="approved">
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <!-- Reject button (with optional feedback modal) -->
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
