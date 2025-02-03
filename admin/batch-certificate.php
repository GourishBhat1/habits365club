<?php
// admin/batch-certificate.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$error = '';
$success = '';

$database = new Database();
$db = $database->getConnection();

// Fetch all batches
$batchQuery = "SELECT id, name FROM batches";
$batchStmt = $db->prepare($batchQuery);
$batchStmt->execute();
$batches = $batchStmt->get_result();

// Handle certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = trim($_POST['batch_id'] ?? '');

    if (empty($batch_id)) {
        $error = "Please select a batch.";
    } else {
        // Generate certificates for all students in the batch
        $insertQuery = "
            INSERT INTO certificates (user_id, milestone, certificate_path, generated_at)
            SELECT users.id, 'Course Completion', CONCAT('certificates/', users.id, '_certificate.png'), NOW()
            FROM users
            JOIN batches_students ON users.id = batches_students.student_id
            WHERE batches_students.batch_id = ?
        ";

        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bind_param("i", $batch_id);

        if ($insertStmt->execute()) {
            $success = "Certificates successfully generated for the selected batch.";
        } else {
            $error = "An error occurred. Please try again.";
        }
        $insertStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Batch Certificate Generation - Habits Web App</title>
    <link rel="stylesheet" href="css/select2.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Batch Certificate Generation</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Generate Certificates for a Batch</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form action="batch-certificate.php" method="POST">
                        <div class="form-group">
                            <label for="batch_id">Select Batch <span class="text-danger">*</span></label>
                            <select id="batch_id" name="batch_id" class="form-control select2" required>
                                <option value="">Choose a Batch</option>
                                <?php while ($batch = $batches->fetch_assoc()): ?>
                                    <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Certificates</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2();
    });
</script>
</body>
</html>
