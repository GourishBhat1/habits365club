<?php
// admin/add-certificate.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$error = '';
$success = '';

$database = new Database();
$db = $database->getConnection();

// Fetch users (parents) eligible for certificates
$userQuery = "SELECT id, username FROM users WHERE role = 'parent'";
$userStmt = $db->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $milestone = trim($_POST['milestone'] ?? '');

    if (empty($user_id) || empty($milestone)) {
        $error = "Please select a user and enter a milestone.";
    } else {
        $certificate_path = "certificates/" . $user_id . "_certificate.png";
        $insertQuery = "INSERT INTO certificates (user_id, milestone, certificate_path, generated_at) VALUES (?, ?, ?, NOW())";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bind_param("iss", $user_id, $milestone, $certificate_path);

        if ($insertStmt->execute()) {
            $success = "Certificate successfully issued.";
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
    <title>Issue Certificate - Habits Web App</title>
    <link rel="stylesheet" href="css/select2.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Issue Certificate</h2>
            <div class="card shadow">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form action="add-certificate.php" method="POST">
                        <div class="form-group">
                            <label for="user_id">Select User <span class="text-danger">*</span></label>
                            <select id="user_id" name="user_id" class="form-control select2" required>
                                <option value="">Choose a User</option>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="milestone">Milestone <span class="text-danger">*</span></label>
                            <input type="text" id="milestone" name="milestone" class="form-control" required placeholder="e.g., Course Completion">
                        </div>
                        <button type="submit" class="btn btn-primary">Issue Certificate</button>
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
