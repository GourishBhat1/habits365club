<?php
// teacher/batch_habits.php

// Start session
session_start();

// Check if the teacher is authenticated via session or cookie
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Include the database connection
require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch teacher ID from session or cookie
$teacher_id = $_SESSION['teacher_id'] ?? null;

if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];

    // Prepare statement to get teacher ID based on email
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
            // Invalid cookie, redirect to login
            header("Location: index.php?message=invalid_cookie");
            exit();
        }
        $stmt->close();
    } else {
        $error = "An error occurred. Please try again later.";
        error_log("Database query failed: " . $db->error);
    }
}

// Fetch batch ID from the query parameter
$batch_id = $_GET['batch_id'] ?? null;

if (!$batch_id) {
    $error = "Invalid batch ID.";
} else {
    // Handle form submission to update habit progress
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $habit_id = $_POST['habit_id'] ?? null;
        $completed = isset($_POST['completed']) ? 1 : 0;

        if ($habit_id) {
            $updateQuery = "UPDATE progress SET completed = ? WHERE habit_id = ?";
            $stmt = $db->prepare($updateQuery);
            if ($stmt) {
                $stmt->bind_param("ii", $completed, $habit_id);
                if ($stmt->execute()) {
                    $success = "Habit progress updated successfully.";
                } else {
                    $error = "Failed to update habit progress.";
                }
                $stmt->close();
            } else {
                $error = "Failed to prepare the update statement.";
            }
        } else {
            $error = "Invalid habit ID.";
        }
    }

    // Fetch students and their habit progress for the batch
    $query = "SELECT students.id AS student_id, students.name AS student_name,
                     habits.id AS habit_id, habits.name AS habit_name, progress.completed
              FROM students
              JOIN habits ON students.id = habits.student_id
              LEFT JOIN progress ON habits.id = progress.habit_id
              WHERE students.batch_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $result = $stmt->get_result();
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
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .batch-card {
            margin-bottom: 20px;
        }
        .habit-list {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Habit Progress</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($result) && $result->num_rows > 0): ?>
                <div class="card batch-card">
                    <div class="card-header">
                        <h5 class="card-title">Habit Progress for Batch ID: <?php echo htmlspecialchars($batch_id); ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Habit</th>
                                    <th>Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['habit_name']); ?></td>
                                    <td>
                                        <form method="POST" action="">
                                            <input type="hidden" name="habit_id" value="<?php echo $row['habit_id']; ?>">
                                            <input type="checkbox" name="completed" value="1" <?php echo $row['completed'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
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
</body>
</html>
