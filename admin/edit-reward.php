<?php
// admin/edit-reward.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

// Get reward ID from GET parameter
$reward_id = $_GET['id'] ?? '';

if (empty($reward_id)) {
    header("Location: reward-management.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Fetch reward details
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, user_id, points, badges, certificates FROM rewards WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $reward_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: reward-management.php");
    exit();
}

$reward = $result->fetch_assoc();

// Fetch all users for assignment (if you want to allow changing the user)
$userQuery = "SELECT id, username FROM users";
$userStmt = $db->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $points = trim($_POST['points'] ?? 0);
    $badges = trim($_POST['badges'] ?? 0);
    $certificates = trim($_POST['certificates'] ?? 0);

    // Basic validation
    if (empty($user_id)) {
        $error = "Please select a user.";
    } elseif (!is_numeric($points) || !is_numeric($badges) || !is_numeric($certificates)) {
        $error = "Points, badges, and certificates must be numeric.";
    } else {
        // Update in database
        $updateQuery = "UPDATE rewards SET user_id = ?, points = ?, badges = ?, certificates = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("iiiii", $user_id, $points, $badges, $certificates, $reward_id);

        if ($updateStmt->execute()) {
            $success = "Reward updated successfully.";
            // Refresh reward details
            $reward['user_id'] = $user_id;
            $reward['points'] = $points;
            $reward['badges'] = $badges;
            $reward['certificates'] = $certificates;
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
    <title>Edit Reward - Habits Web App</title>
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
            <h2 class="page-title">Edit Reward</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Reward Details</h5>
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
                    <form action="edit-reward.php?id=<?php echo $reward_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="user_id">Select User <span class="text-danger">*</span></label>
                            <select id="user_id" name="user_id" class="form-control select2" required>
                                <option value="">Select a User</option>
                                <?php while($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($reward['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a user.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="points">Points</label>
                            <input type="number" id="points" name="points" class="form-control" min="0" value="<?php echo htmlspecialchars($reward['points']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="badges">Badges</label>
                            <input type="number" id="badges" name="badges" class="form-control" min="0" value="<?php echo htmlspecialchars($reward['badges']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="certificates">Certificates</label>
                            <input type="number" id="certificates" name="certificates" class="form-control" min="0" value="<?php echo htmlspecialchars($reward['certificates']); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Reward</button>
                        <a href="reward-management.php" class="btn btn-secondary">Cancel</a>
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
