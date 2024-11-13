<?php
// admin/add-certificate.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Initialize variables
$error = '';
$success = '';

// Fetch all users
$database = new Database();
$db = $database->getConnection();

$userQuery = "SELECT id, username FROM users";
$userStmt = $db->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $milestone = trim($_POST['milestone'] ?? '');

    // Basic validation
    if (empty($user_id) || empty($milestone)) {
        $error = "Please fill in all required fields.";
    } else {
        // Generate certificate (for simplicity, we'll assume it's a PDF generated elsewhere)
        // Here, we'll just store the path to the certificate
        // In a real application, you would generate the certificate file dynamically

        // Sample certificate path
        $certificate_path = "../certificates/certificate_" . uniqid() . ".pdf";

        // For demonstration, we'll copy a template certificate
        // Ensure you have a template certificate at "../certificates/template.pdf"
        if (!copy("../certificates/template.pdf", $certificate_path)) {
            $error = "Error generating certificate.";
        } else {
            // Insert into certificates table
            $insertQuery = "INSERT INTO certificates (user_id, milestone, certificate_path, generated_at) VALUES (?, ?, ?, NOW())";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bind_param("iss", $user_id, $milestone, $certificate_path);

            if ($insertStmt->execute()) {
                $success = "Certificate generated successfully.";
            } else {
                $error = "An error occurred. Please try again.";
                // Optionally, delete the copied certificate if DB insert fails
                unlink($certificate_path);
            }
            $insertStmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Add New Certificate - Habits Web App</title>
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="css/select2.min.css">
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
            <h2 class="page-title">Generate New Certificate</h2>
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
                    <form action="add-certificate.php" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="user_id">Select User <span class="text-danger">*</span></label>
                            <select id="user_id" name="user_id" class="form-control select2" required>
                                <option value="">Select a User</option>
                                <?php while($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a user.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="milestone">Milestone <span class="text-danger">*</span></label>
                            <input type="text" id="milestone" name="milestone" class="form-control" required placeholder="e.g., 30-day Streak">
                            <div class="invalid-feedback">
                                Please enter a milestone.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Certificate</button>
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
