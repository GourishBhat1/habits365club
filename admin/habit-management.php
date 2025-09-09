<?php
// admin/habit-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include DB connection
require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Add new habit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- POST DATA: " . htmlspecialchars(print_r($_POST, true)) . " -->"; // DEBUG

    $habitTitle = trim($_POST['habit_title'] ?? '');
    $habitDescription = trim($_POST['habit_description'] ?? '');

    // Handle upload_type as comma-separated string
    $uploadType = '';
    if (isset($_POST['upload_type']) && is_array($_POST['upload_type'])) {
        $uploadType = implode(',', $_POST['upload_type']);
    }

    $autoApprove = isset($_POST['auto_approve']) ? 1 : 0;

    $reminderTimes = isset($_POST['reminder_times']) ? array_filter($_POST['reminder_times']) : [];
    echo "<!-- REMINDER TIMES ARRAY: " . htmlspecialchars(print_r($reminderTimes, true)) . " -->"; // DEBUG
    $reminderTimesJson = !empty($reminderTimes) ? json_encode($reminderTimes) : null;
    echo "<!-- REMINDER TIMES JSON: " . htmlspecialchars($reminderTimesJson) . " -->"; // DEBUG

    if (isset($_POST['addHabit'])) {
        if (!empty($habitTitle) && !empty($habitDescription) && !empty($uploadType)) {
            $query = "INSERT INTO habits (title, description, upload_type, auto_approve, reminder_times) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("sssis", $habitTitle, $habitDescription, $uploadType, $autoApprove, $reminderTimesJson);

            if ($stmt->execute()) {
                $success = "Habit added successfully.";
            } else {
                $error = "Failed to add habit.";
            }
            $stmt->close();
        } else {
            $error = "All fields are required.";
        }
    }

    // Update habit
    if (isset($_POST['updateHabit'])) {
        $habitId = $_POST['habit_id'] ?? '';
        echo "<!-- UPDATE HABIT ID: " . htmlspecialchars($habitId) . " -->"; // DEBUG
        if (!empty($habitId) && !empty($habitTitle) && !empty($habitDescription) && !empty($uploadType)) {
            $query = "UPDATE habits SET title = ?, description = ?, upload_type = ?, auto_approve = ?, reminder_times = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("sssisi", $habitTitle, $habitDescription, $uploadType, $autoApprove, $reminderTimesJson, $habitId);

            if ($stmt->execute()) {
                $success = "Habit updated successfully.";
            } else {
                $error = "Failed to update habit.";
            }
            $stmt->close();
        } else {
            $error = "All fields are required.";
        }
    }
}

// Delete habit
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    $deleteQuery = "DELETE FROM habits WHERE id = ?";
    $stmt = $db->prepare($deleteQuery);
    $stmt->bind_param("i", $deleteId);

    if ($stmt->execute()) {
        $success = "Habit deleted successfully.";
    } else {
        $error = "Unable to delete habit.";
    }
    $stmt->close();
}

// Retrieve all habits
$habitQuery = "SELECT id, title, description, upload_type, auto_approve, reminder_times FROM habits";
$habitStmt = $db->prepare($habitQuery);
$habitStmt->execute();
$habits = $habitStmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Habit Management - Admin</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Habit Management</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Add Habit Form -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <strong>Add New Habit</strong>
                </div>
                <div class="card-body">
                    <form action="" method="POST" class="d-flex flex-wrap align-items-start">
                        <div class="form-group mb-3 mr-2" style="min-width:200px;">
                            <input type="text" name="habit_title" class="form-control" placeholder="Habit Title" required>
                        </div>
                        <div class="form-group mb-3 mr-2" style="min-width:200px;">
                            <input type="text" name="habit_description" class="form-control" placeholder="Description" required>
                        </div>
                        <div class="form-group mb-3 mr-2">
                            <label class="mb-1">Allowed Upload Types:</label><br>
                            <label class="mr-2"><input type="checkbox" name="upload_type[]" value="image"> Image</label>
                            <label class="mr-2"><input type="checkbox" name="upload_type[]" value="audio"> Audio</label>
                            <label class="mr-2"><input type="checkbox" name="upload_type[]" value="video"> Video</label>
                        </div>
                        <div class="form-group mb-3 mr-2">
                            <label class="mb-1">Auto Approve:</label><br>
                            <input type="checkbox" name="auto_approve" value="1">
                        </div>
                        <div class="form-group mb-3 mr-2" style="flex-direction: column; align-items: flex-start;">
                            <label class="mb-1">Reminder Times:</label>
                            <div id="reminder-times-container">
                                <div class="input-group mb-2" style="width:170px;">
                                    <input type="time" name="reminder_times[]" class="form-control">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeReminderTime(this)">&times;</button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addReminderTime()">Add Time</button>
                        </div>
                        <div class="form-group mb-3">
                            <button type="submit" name="addHabit" class="btn btn-primary">Add Habit</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Habits List -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>Existing Habits</strong>
                </div>
                <div class="card-body table-responsive">
                    <table id="habitTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Habit Title</th>
                                <th>Description</th>
                                <th>Upload Type</th>
                                <th>Auto Approve</th>
                                <th>Reminder Times</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($habit = $habits->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($habit['title']); ?></td>
                                    <td><?php echo htmlspecialchars($habit['description']); ?></td>
                                    <td>
                                        <?php foreach (explode(',', $habit['upload_type']) as $type): ?>
                                            <span class="badge badge-info mr-1"><?php echo ucfirst($type); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($habit['auto_approve']) ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $times = !empty($habit['reminder_times']) ? json_decode($habit['reminder_times'], true) : [];
                                        foreach ($times as $t) {
                                            echo '<span class="badge badge-warning mr-1">'.htmlspecialchars($t).'</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="edit-habit.php?id=<?php echo $habit['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <a href="?delete_id=<?php echo $habit['id']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this habit?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- End card -->
        </div> <!-- End container-fluid -->
    </main>
</div> <!-- End wrapper -->

<?php include 'includes/footer.php'; ?>

<!-- DataTables -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#habitTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });

    function addReminderTime() {
        var container = $('#reminder-times-container');
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
