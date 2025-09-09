<?php
// admin/edit-habit.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}
require_once '../connection.php';

$habit_id = $_GET['id'] ?? '';
if (empty($habit_id)) {
    header("Location: habit-management.php");
    exit();
}

$error = '';
$success = '';

$database = new Database();
$db = $database->getConnection();

// Fetch habit details
$query = "SELECT id, title, description, upload_type, auto_approve, reminder_times FROM habits WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $habit_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header("Location: habit-management.php");
    exit();
}
$habit = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $habit_title = trim($_POST['habit_title'] ?? '');
    $description = trim($_POST['habit_description'] ?? '');
    $uploadType = isset($_POST['upload_type']) && is_array($_POST['upload_type']) ? implode(',', $_POST['upload_type']) : '';
    $autoApprove = isset($_POST['auto_approve']) ? 1 : 0;
    $reminderTimes = isset($_POST['reminder_times']) ? array_filter($_POST['reminder_times']) : [];
    $reminderTimesJson = !empty($reminderTimes) ? json_encode($reminderTimes) : null;

    if (empty($habit_title) || empty($description) || empty($uploadType)) {
        $error = "Please fill in all required fields.";
    } else {
        $updateQuery = "UPDATE habits SET title = ?, description = ?, upload_type = ?, auto_approve = ?, reminder_times = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("sssisi", $habit_title, $description, $uploadType, $autoApprove, $reminderTimesJson, $habit_id);

        if ($updateStmt->execute()) {
            $success = "Habit updated successfully.";
            // Refresh habit details
            $habit['title'] = $habit_title;
            $habit['description'] = $description;
            $habit['upload_type'] = $uploadType;
            $habit['auto_approve'] = $autoApprove;
            $habit['reminder_times'] = $reminderTimesJson;
        } else {
            $error = "An error occurred. Please try again.";
        }
        $updateStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Edit Habit - Habits Web App</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Edit Habit</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Habit Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form action="edit-habit.php?id=<?php echo $habit_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="habit_title">Habit Title <span class="text-danger">*</span></label>
                            <input type="text" id="habit_title" name="habit_title" class="form-control" value="<?php echo htmlspecialchars($habit['title']); ?>" required>
                            <div class="invalid-feedback">Please enter a habit title.</div>
                        </div>
                        <div class="form-group">
                            <label for="habit_description">Description <span class="text-danger">*</span></label>
                            <input type="text" id="habit_description" name="habit_description" class="form-control" value="<?php echo htmlspecialchars($habit['description']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Allowed Upload Types <span class="text-danger">*</span></label><br>
                            <?php $types = explode(',', $habit['upload_type']); ?>
                            <label class="mr-2"><input type="checkbox" name="upload_type[]" value="image" <?php if (in_array('image', $types)) echo 'checked'; ?>> Image</label>
                            <label class="mr-2"><input type="checkbox" name="upload_type[]" value="audio" <?php if (in_array('audio', $types)) echo 'checked'; ?>> Audio</label>
                            <label class="mr-2"><input type="checkbox" name="upload_type[]" value="video" <?php if (in_array('video', $types)) echo 'checked'; ?>> Video</label>
                        </div>
                        <div class="form-group">
                            <label>Auto Approve</label><br>
                            <input type="checkbox" name="auto_approve" value="1" <?php if (!empty($habit['auto_approve'])) echo 'checked'; ?>>
                        </div>
                        <div class="form-group" style="flex-direction: column; align-items: flex-start;">
                            <label>Reminder Times</label>
                            <div id="reminder-times-edit">
                                <?php
                                $times = !empty($habit['reminder_times']) ? json_decode($habit['reminder_times'], true) : [];
                                if ($times) {
                                    foreach ($times as $t) {
                                        echo '<div class="input-group mb-2" style="width:170px;">
                                                <input type="time" name="reminder_times[]" value="'.htmlspecialchars($t).'" class="form-control">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeReminderTime(this)">&times;</button>
                                                </div>
                                            </div>';
                                    }
                                } else {
                                    echo '<div class="input-group mb-2" style="width:170px;">
                                            <input type="time" name="reminder_times[]" class="form-control">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeReminderTime(this)">&times;</button>
                                            </div>
                                          </div>';
                                }
                                ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addReminderTimeEdit()">Add Time</button>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Habit</button>
                        <a href="habit-management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script>
function addReminderTimeEdit() {
    var container = $('#reminder-times-edit');
    var newTimeInput = `
    <div class="input-group mb-2" style="width:170px;">
        <input type="time" name="reminder_times[]" class="form-control">
        <div class="input-group-append">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeReminderTime(this)">&times;</button>
        </div>
    </div>`;
    container.append(newTimeInput);
}
function removeReminderTime(btn) {
    $(btn).closest('.input-group').remove();
}
</script>
</body>
</html>
