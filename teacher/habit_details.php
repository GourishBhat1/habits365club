<?php
// teacher/habit_details.php

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
// Get habit_id (from query string ?habit_id=...)
// ------------------------------------------------------------
$habit_id = $_GET['habit_id'] ?? null;

if (!$habit_id) {
    die("❌ Invalid habit ID.");
}

// ------------------------------------------------------------
// Fetch Habit Details
// ------------------------------------------------------------
$habitDetails = null;
$habitStmt = $db->prepare("SELECT id, title, description FROM habits WHERE id = ?");
if ($habitStmt) {
    $habitStmt->bind_param("i", $habit_id);
    $habitStmt->execute();
    $habitResult = $habitStmt->get_result();
    $habitDetails = $habitResult->fetch_assoc();
    $habitStmt->close();
}

if (!$habitDetails) {
    die("❌ Habit not found.");
}

// ------------------------------------------------------------
// Fetch Habit Submissions from students in teacher's assigned batches
// ------------------------------------------------------------
$submissions = [];
$sql = "
    SELECT eu.id as submission_id,
           u.username as parent_name,
           eu.file_path as evidence_path,
           eu.status,
           eu.points as score,
           eu.feedback,
           eu.uploaded_at as created_at
    FROM evidence_uploads eu
    JOIN users u ON eu.parent_id = u.id
    WHERE eu.habit_id = ?
    AND u.batch_id IN (
        SELECT batch_id FROM batch_teachers WHERE teacher_id = ?
    )
    ORDER BY eu.uploaded_at DESC
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
}

// ------------------------------------------------------------
// Handle Form Submission for Approving/Rejection Submissions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id'])) {
    $submission_id = $_POST['submission_id'];
    $status = $_POST['status'];
    $score = $_POST['score'] ?? 0;
    $feedback = $_POST['feedback'] ?? '';

    // Update the submission status, score, and feedback
    $updateStmt = $db->prepare("UPDATE evidence_uploads SET status = ?, points = ?, feedback = ? WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("sisi", $status, $score, $feedback, $submission_id);
        if ($updateStmt->execute()) {
            $success = "✅ Submission updated successfully!";
            header("Refresh:0"); // Reload the page
        } else {
            $error = "❌ Failed to update submission.";
        }
        $updateStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Habit Details - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .badge-pending { background-color: #ffc107; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        .table-responsive { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
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
                                    <th>Child Name</th>
                                    <th>Evidence</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Feedback</th>
                                    <th>Submitted On</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($submissions as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['parent_name']); ?></td>
                                        <td>
                                            <?php if (!empty($sub['evidence_path'])): ?>
                                                <a href="<?php echo CDN_URL . htmlspecialchars($sub['evidence_path']); ?>" target="_blank">View Evidence</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo htmlspecialchars($sub['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($sub['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($sub['score']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($sub['created_at']); ?></td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="submission_id" value="<?php echo $sub['submission_id']; ?>">
                                                <select name="status">
                                                    <option value="pending">Pending</option>
                                                    <option value="approved">Approve</option>
                                                    <option value="rejected">Reject</option>
                                                </select>
                                                <input type="number" name="score" min="0" max="100" placeholder="Score">
                                                <input type="text" name="feedback" placeholder="Feedback">
                                                <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No submissions found for this habit.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
