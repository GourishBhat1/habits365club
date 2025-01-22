<?php
// admin/habit-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include DB connection or other necessary files
// require_once '../connection.php'; // Example only, adjust path as needed

$error = '';
$success = '';

// If you have a database class, instantiate and get connection:
// $database = new Database();
// $db = $database->getConnection();

// Handle form submission for creating/updating habits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Example fields: habit_title, habit_description, etc.
    $habitTitle = $_POST['habit_title'] ?? '';
    $habitDescription = $_POST['habit_description'] ?? '';

    if (isset($_POST['addHabit'])) {
        // INSERT logic here
        // $query = "INSERT INTO habits (title, description) VALUES (?, ?)";
        // $stmt = $db->prepare($query);
        // ...
        $success = "Habit added successfully (placeholder).";
    }

    if (isset($_POST['updateHabit'])) {
        // UPDATE logic here
        $habitId = $_POST['habit_id'] ?? '';
        // $query = "UPDATE habits SET title = ?, description = ? WHERE id = ?";
        // ...
        $success = "Habit updated successfully (placeholder).";
    }
}

// Handle delete action if needed (via GET or POST)
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    // $deleteQuery = "DELETE FROM habits WHERE id = ?";
    // ...
    $success = "Habit deleted successfully (placeholder).";
}

// Retrieve the list of all global habits (placeholder data)
$habits = [
    ['id' => 1, 'title' => 'Daily Reading', 'description' => 'Read 20 pages each day'],
    ['id' => 2, 'title' => 'Morning Exercise', 'description' => '15 minutes of exercise'],
    // ... replace with real DB data
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Habit Management - Admin</title>
    <!-- Include the same CSS as in admin/dashboard.php -->
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
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Navbar & Sidebar -->
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Habit Management</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Add New Habit Form -->
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

            <!-- Existing Habits Table -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>Existing Global Habits</strong>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Habit Title</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($habits as $habit): ?>
                            <tr>
                                <td><?php echo $habit['id']; ?></td>
                                <td><?php echo htmlspecialchars($habit['title']); ?></td>
                                <td><?php echo htmlspecialchars($habit['description']); ?></td>
                                <td>
                                    <!-- Update / Delete buttons (placeholder) -->
                                    <button class="btn btn-sm btn-info" data-toggle="modal"
                                            data-target="#updateModal-<?php echo $habit['id']; ?>">Edit
                                    </button>
                                    <a href="?delete_id=<?php echo $habit['id']; ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this habit?');">
                                        Delete
                                    </a>

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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- End card -->
        </div> <!-- End container-fluid -->
    </main>
</div> <!-- End wrapper -->

<!-- Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
