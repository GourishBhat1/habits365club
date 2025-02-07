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
    // ------------------------------------------------------------
    // Handle Form Submission for Habit Progress Updates
    // ------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $habit_id = $_POST['habit_id'] ?? null;
        $parent_id = $_POST['parent_id'] ?? null;
        $points = $_POST['points'] ?? 0;
        $status = $_POST['status'] ?? 'pending';
        $feedback = $_POST['feedback'] ?? '';

        if ($habit_id && $parent_id) {
            $updateQuery = "UPDATE evidence_uploads SET status = ?, points = ?, feedback = ? WHERE habit_id = ? AND parent_id = ?";
            $stmt = $db->prepare($updateQuery);
            if ($stmt) {
                $stmt->bind_param("sisii", $status, $points, $feedback, $habit_id, $parent_id);
                if ($stmt->execute()) {
                    $success = "✅ Habit progress updated successfully!";
                    header("Refresh:0"); // Reload the page
                } else {
                    $error = "❌ Failed to update habit progress.";
                }
                $stmt->close();
            } else {
                $error = "Failed to prepare the update statement.";
            }
        } else {
            $error = "Invalid data provided.";
        }
    }

    // ------------------------------------------------------------
    // Fetch Habit Progress Data for Students in the Selected Batch
    // ------------------------------------------------------------
    $habitData = [];
    $query = "
        SELECT u.id AS parent_id, u.username AS parent_name, 
               h.id AS habit_id, h.title AS habit_name, 
               eu.status, eu.points, eu.feedback
        FROM users u
        JOIN evidence_uploads eu ON eu.parent_id = u.id
        JOIN habits h ON eu.habit_id = h.id
        WHERE u.batch_id = ?
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
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Parent Name</th>
                                    <th>Habit</th>
                                    <th>Status</th>
                                    <th>Points</th>
                                    <th>Feedback</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($habitData as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['habit_name']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php echo ($row['status'] === 'approved') ? 'badge-approved' : (($row['status'] === 'rejected') ? 'badge-rejected' : 'badge-pending'); ?>">
                                            <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['points']); ?></td>
                                    <td><?php echo htmlspecialchars($row['feedback'] ?? ''); ?></td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="habit_id" value="<?php echo $row['habit_id']; ?>">
                                            <input type="hidden" name="parent_id" value="<?php echo $row['parent_id']; ?>">
                                            <select name="status" class="form-control select2">
                                                <option value="pending" <?php echo ($row['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo ($row['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                                <option value="rejected" <?php echo ($row['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                            <input type="number" name="points" class="form-control ml-2" value="<?php echo $row['points']; ?>" min="0" max="100">
                                            <input type="text" name="feedback" class="form-control ml-2" placeholder="Feedback">
                                            <button type="submit" class="btn btn-success btn-sm ml-2">Update</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p>No habits found for this batch. Please contact the admin.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
