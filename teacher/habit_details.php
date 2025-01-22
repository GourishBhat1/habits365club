<?php
// teacher/habit_details.php

session_start();

// Check if the teacher is authenticated
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

require_once '../connection.php';
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
    } else {
        $error = "An error occurred. Please try again.";
        error_log("Database query failed: " . $db->error);
    }
}

if (!$teacher_id) {
    $error = "Invalid session. Please log in again.";
}

// ------------------------------------------------------------
// Get habit_id (e.g., from query string ?habit_id=...)
// Also optionally get batch_id if you want to filter by batch
// ------------------------------------------------------------
$habit_id = $_GET['habit_id'] ?? null;
$batch_id = $_GET['batch_id'] ?? null;

// ------------------------------------------------------------
// Fetch Habit Details
// ------------------------------------------------------------
$habitDetails = null;
if ($habit_id) {
    $habitStmt = $db->prepare("SELECT id, title, description FROM habits WHERE id = ?");
    if ($habitStmt) {
        $habitStmt->bind_param("i", $habit_id);
        $habitStmt->execute();
        $habitResult = $habitStmt->get_result();
        $habitDetails = $habitResult->fetch_assoc();
        $habitStmt->close();
    }
    if (!$habitDetails) {
        $error = "Habit not found or invalid habit ID.";
    }
}

// ------------------------------------------------------------
// Fetch submissions/progress for this habit (optionally, limited to teacher’s batch)
// ------------------------------------------------------------
$submissions = [];
if ($habitDetails && $batch_id) {
    // For a specific batch
    $sql = "
        SELECT uh.id as submission_id,
               u.name as parent_name,
               b.name as batch_name,
               uh.evidence_path,
               uh.status,
               uh.score,
               uh.feedback,
               uh.created_at
          FROM user_habits uh
          JOIN users u ON uh.user_id = u.id
          JOIN batches b ON uh.batch_id = b.id
         WHERE uh.habit_id = ?
           AND b.id = ?
           AND b.teacher_id = ?
         ORDER BY uh.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iii", $habit_id, $batch_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $submissions[] = $row;
        }
        $stmt->close();
    } else {
        $error = "Failed to load habit submissions.";
        error_log("Prepare failed: " . $db->error);
    }
} elseif ($habitDetails) {
    // For all batches assigned to this teacher
    $sql = "
        SELECT uh.id as submission_id,
               u.name as parent_name,
               b.name as batch_name,
               uh.evidence_path,
               uh.status,
               uh.score,
               uh.feedback,
               uh.created_at
          FROM user_habits uh
          JOIN users u ON uh.user_id = u.id
          JOIN batches b ON uh.batch_id = b.id
         WHERE uh.habit_id = ?
           AND b.teacher_id = ?
         ORDER BY uh.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $habit_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $submissions[] = $row;
        }
        $stmt->close();
    } else {
        $error = "Failed to load habit submissions.";
        error_log("Prepare failed: " . $db->error);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Habit Details - Habits365Club</title>
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
            <h2 class="page-title">Habit Details</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($habitDetails): ?>
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <strong><?php echo htmlspecialchars($habitDetails['title']); ?></strong>
                    </div>
                    <div class="card-body">
                        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($habitDetails['description'])); ?></p>
                        
                        <?php if ($batch_id): ?>
                            <p><strong>Batch Filter:</strong> Showing submissions for batch_id = <?php echo (int)$batch_id; ?></p>
                        <?php else: ?>
                            <p><em>Showing submissions across all your assigned batches.</em></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-header">
                        <strong>Submissions / Progress</strong>
                    </div>
                    <div class="card-body table-responsive">
                        <?php if (!empty($submissions)): ?>
                            <table class="table table-bordered table-hover">
                                <thead>
                                <tr>
                                    <th>Parent Name</th>
                                    <th>Batch</th>
                                    <th>Evidence</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Feedback</th>
                                    <th>Submitted On</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($submissions as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['parent_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['batch_name']); ?></td>
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
                                        <td><?php echo htmlspecialchars($sub['score']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($sub['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No submissions found for this habit.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p>Invalid or missing habit details.</p>
            <?php endif; ?>

        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
