<?php
// teacher/batch_habits.php

session_start();
require_once '../connection.php';

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Fetch teacher ID from session or cookie
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
    }
}

if (!$teacher_id) {
    $error = "Invalid session. Please log in again.";
}

// ------------------------------------------------------------
// Fetch batch ID from query parameters
// ------------------------------------------------------------
$batch_id = $_GET['batch_id'] ?? null;
if (!$batch_id) {
    $error = "Invalid batch ID.";
} else {
    $accessCheck = $db->prepare("SELECT 1 FROM batch_teachers WHERE batch_id = ? AND teacher_id = ?");
    $accessCheck->bind_param("ii", $batch_id, $teacher_id);
    $accessCheck->execute();
    $accessCheck->store_result();
    if ($accessCheck->num_rows === 0) {
        $error = "You are not assigned to this batch.";
    }
    $accessCheck->close();
}

if (empty($error)) {
    // ------------------------------------------------------------
    // Handle Form Submission for Habit Progress Updates
    // ------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submission_id = $_POST['submission_id'] ?? null;
        $status = $_POST['status'] ?? 'pending';
        $feedback = $_POST['feedback'] ?? '';

        if ($submission_id) {
            $db->begin_transaction();
            try {
                // Set points based on status
                $points = ($status === 'approved') ? 1 : 0;

                // Update status, feedback, and points
                $updateQuery = "UPDATE evidence_uploads 
                              SET status = ?, 
                                  feedback = ?,
                                  points = ?
                              WHERE id = ?";
                
                $stmt = $db->prepare($updateQuery);
                if ($stmt) {
                    $stmt->bind_param("ssii", $status, $feedback, $points, $submission_id);
                    if ($stmt->execute()) {
                        $db->commit();
                        $success = "✅ Habit progress and points updated successfully!";
                        header("Refresh:0");
                    } else {
                        throw new Exception("Failed to update habit progress");
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Failed to prepare the update statement");
                }
            } catch (Exception $e) {
                $db->rollback();
                $error = "❌ " . $e->getMessage();
            }
        } else {
            $error = "Invalid submission ID provided.";
        }
    }

    // ------------------------------------------------------------
    // Fetch Habit Progress Data for Students in the Selected Batch
    // ------------------------------------------------------------
    $habitData = [];
    $query = "
        SELECT eu.id AS submission_id, 
               u.full_name AS parent_name, 
               h.title AS habit_name, 
               eu.status, 
               eu.feedback, 
               eu.file_path, 
               eu.uploaded_at,
               eu.points
        FROM evidence_uploads eu
        JOIN users u ON eu.parent_id = u.id
        JOIN habits h ON eu.habit_id = h.id
        WHERE u.batch_id = ?
        ORDER BY eu.uploaded_at DESC
    ";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $habitData[] = $row;
        }
        $stmt->close();
    } else {
        $error = "Failed to retrieve habit progress.";
        error_log("Prepare failed: " . $db->error);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Habit Progress - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.3.1/css/rowGroup.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
    <style>
        .badge-pending { background-color: #ffc107; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        .habit-list { max-height: 400px; overflow-y: auto; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Include Navbar & Sidebar -->
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Habit Progress</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($habitData)): ?>
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title">Habit Progress for Batch ID: <?php echo htmlspecialchars($batch_id); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($habitData as $row): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="border p-3 shadow-sm rounded">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($row['parent_name']); ?></h5>
                                        <p class="mb-1"><strong>Habit:</strong> <?php echo htmlspecialchars($row['habit_name']); ?></p>
                                        <p class="mb-1">
                                            <strong>Evidence:</strong>
                                            <?php if (!empty($row['file_path'])): ?>
                                                <a href="<?php echo CDN_URL . htmlspecialchars($row['file_path']); ?>" target="_blank">View</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </p>
                                        <p class="mb-1"><strong>Uploaded At:</strong> <?php echo htmlspecialchars($row['uploaded_at']); ?></p>
                                        <p class="mb-1">
                                            <strong>Status:</strong>
                                            <span class="badge 
                                                <?php echo ($row['status'] === 'approved') ? 'badge-approved' : (($row['status'] === 'rejected') ? 'badge-rejected' : 'badge-pending'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                            </span>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Points:</strong>
                                            <span class="badge <?php echo ($row['points'] > 0) ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo htmlspecialchars($row['points']); ?>
                                            </span>
                                        </p>
                                        <form method="POST" class="form mt-2">
                                            <input type="hidden" name="submission_id" value="<?php echo $row['submission_id']; ?>">
                                            <div class="form-group mb-1">
                                                <select name="status" class="form-control form-control-sm select2 w-100 mb-1">
                                                    <option value="pending" <?php echo ($row['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="approved" <?php echo ($row['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                                    <option value="rejected" <?php echo ($row['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                            </div>
                                            <div class="form-group mb-1">
                                            <input type="text" name="feedback" class="form-control form-control-sm w-100" placeholder="Feedback" value="<?php echo htmlspecialchars($row['feedback'] ?? ''); ?>">
                                            </div>
                                            <button type="submit" class="btn btn-success btn-sm w-100">Update</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p>No habits found for this batch. Please contact the admin.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>

<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#habitTable').DataTable({
            responsive: {
                details: {
                    type: 'column',
                    target: 'tr'
                }
            },
            columnDefs: [{
                className: 'dtr-control',
                orderable: false,
                targets: -1
            }],
            paging: true,
            searching: true,
            ordering: true
        });
    });
</script>
</body>
</html>
