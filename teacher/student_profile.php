<?php
// teacher/student_profile.php

session_start();
require_once '../connection.php';

if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    die("Invalid student ID.");
}

$database = new Database();
$db = $database->getConnection();

// Fetch student details
$studentQuery = "SELECT id, name, email FROM students WHERE id = ?";
$stmt = $db->prepare($studentQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$studentResult = $stmt->get_result();
$student = $studentResult->fetch_assoc();
$stmt->close();

// Fetch habits for the student
$habitsQuery = "SELECT habits.id, habits.name, progress.completed, progress.evidence
                FROM habits
                LEFT JOIN progress ON habits.id = progress.habit_id
                WHERE habits.student_id = ?";
$stmt = $db->prepare($habitsQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$habitsResult = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Student Profile - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .profile-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .habit-list {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Student Profile</h2>
            <div class="card profile-card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo htmlspecialchars($student['name']); ?></h5>
                    <span class="text-muted"><?php echo htmlspecialchars($student['email']); ?></span>
                </div>
            </div>
            <h3>Habits</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Habit</th>
                        <th>Completed</th>
                        <th>Evidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($habit = $habitsResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($habit['name']); ?></td>
                        <td><?php echo $habit['completed'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <?php if ($habit['evidence']): ?>
                                <a href="<?php echo htmlspecialchars($habit['evidence']); ?>" target="_blank">View Evidence</a>
                            <?php else: ?>
                                No evidence uploaded
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
