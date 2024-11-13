<?php
// admin/edit-certificate.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Get certificate ID from GET parameter
$certificate_id = $_GET['id'] ?? '';

if (empty($certificate_id)) {
    header("Location: certificate-management.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Fetch certificate details
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, user_id, milestone, certificate_path FROM certificates WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $certificate_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: certificate-management.php");
    exit();
}

$certificate = $result->fetch_assoc();

// Fetch all users for assignment (if you want to allow changing the user)
$userQuery = "SELECT id, username FROM users";
$userStmt = $db->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $milestone = trim($_POST['milestone'] ?? '');

    // Optional: Handle certificate file upload if allowing changes
    // For simplicity, we'll assume certificate_path remains unchanged

    // Basic validation
    if (empty($user_id) || empty($milestone)) {
        $error = "Please fill in all required fields.";
    } else {
        // Update in database
        $updateQuery = "UPDATE certificates SET user_id = ?, milestone = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("isi", $user_id, $milestone, $certificate_id);

        if ($updateStmt->execute()) {
            $success = "Certificate updated successfully.";
            // Refresh certificate details
            $certificate['user_id'] = $user_id;
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
    <?php include 'includes/header.php'; // Optional ?>
    <title>Edit Certificate - Habits Web App</title>
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="css/select2.min.css">
    <style>
        .alert { /* same styles as before */ }
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
            <h2 class="page-title">Edit Certificate</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Certificate Details</h5>
                </div>
                <div class="card-body">
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
                    <form action="edit-certificate.php?id=<?php echo $certificate_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="user_id">Select User <span class="text-danger">*</span></label>
                            <select id="user_id" name="user_id" class="form-control select2" required>
                                <option value="">Select a User</option>
                                <?php while($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($certificate['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a user.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="milestone">Milestone <span class="text-danger">*</span></label>
                            <input type="text" id="milestone" name="milestone" class="form-control" value="<?php echo htmlspecialchars($certificate['milestone']); ?>" required placeholder="e.g., 30-day Streak">
                            <div class="invalid-feedback">
                                Please enter a milestone.
                            </div>
                        </div>
                        <!-- Optional: Add a file input to replace the certificate -->
                        <!--
                        <div class="form-group">
                            <label for="certificate">Replace Certificate (Optional)</label>
                            <input type="file" id="certificate" name="certificate" class="form-control-file">
                        </div>
                        -->
                        <button type="submit" class="btn btn-primary">Update Certificate</button>
                        <a href="certificate-management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select a user"
        });

        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    });
</script>
</body>
</html>
