<?php
// admin/edit-certificate.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$certificate_id = $_GET['id'] ?? '';
if (empty($certificate_id)) {
    header("Location: certificate-management.php");
    exit();
}

$error = '';
$success = '';

$database = new Database();
$db = $database->getConnection();

// Fetch certificate details
$query = "SELECT id, user_id, milestone FROM certificates WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $certificate_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: certificate-management.php");
    exit();
}

$certificate = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $milestone = trim($_POST['milestone'] ?? '');

    if (empty($milestone)) {
        $error = "Please enter a milestone.";
    } else {
        $updateQuery = "UPDATE certificates SET milestone = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("si", $milestone, $certificate_id);

        if ($updateStmt->execute()) {
            $success = "Certificate updated successfully.";
            $certificate['milestone'] = $milestone;
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
    <title>Edit Certificate - Habits Web App</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Edit Certificate</h2>
            <div class="card shadow">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form action="edit-certificate.php?id=<?php echo $certificate_id; ?>" method="POST">
                        <div class="form-group">
                            <label for="milestone">Milestone <span class="text-danger">*</span></label>
                            <input type="text" id="milestone" name="milestone" class="form-control" value="<?php echo htmlspecialchars($certificate['milestone']); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Certificate</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
