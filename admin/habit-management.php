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
    $habitTitle = trim($_POST['habit_title'] ?? '');
    $habitDescription = trim($_POST['habit_description'] ?? '');
    
    if (isset($_POST['addHabit'])) {
        if (!empty($habitTitle) && !empty($habitDescription)) {
            $query = "INSERT INTO habits (title, description) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ss", $habitTitle, $habitDescription);
            
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
        if (!empty($habitId) && !empty($habitTitle) && !empty($habitDescription)) {
            $query = "UPDATE habits SET title = ?, description = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ssi", $habitTitle, $habitDescription, $habitId);
            
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
$habitQuery = "SELECT id, title, description FROM habits";
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
                    <form action="" method="POST" class="form-inline">
                        <input type="text" name="habit_title" class="form-control mr-2" placeholder="Habit Title" required>
                        <input type="text" name="habit_description" class="form-control mr-2" placeholder="Description" required>
                        <button type="submit" name="addHabit" class="btn btn-primary">Add Habit</button>
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
                                <th>ID</th>
                                <th>Habit Title</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($habit = $habits->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $habit['id']; ?></td>
                                    <td><?php echo htmlspecialchars($habit['title']); ?></td>
                                    <td><?php echo htmlspecialchars($habit['description']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" data-toggle="modal"
                                                data-target="#updateModal-<?php echo $habit['id']; ?>">Edit</button>
                                        <a href="?delete_id=<?php echo $habit['id']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this habit?');">Delete</a>
                                    </td>
                                </tr>

                                <!-- Update Modal -->
                                <div class="modal fade" id="updateModal-<?php echo $habit['id']; ?>" tabindex="-1" role="dialog">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="" method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Habit</h5>
                                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                                    <div class="form-group">
                                                        <label>Habit Title</label>
                                                        <input type="text" name="habit_title" class="form-control"
                                                               value="<?php echo htmlspecialchars($habit['title']); ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Description</label>
                                                        <input type="text" name="habit_description" class="form-control"
                                                               value="<?php echo htmlspecialchars($habit['description']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" name="updateHabit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Update Modal -->
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
</script>
</body>
</html>
