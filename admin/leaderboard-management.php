<?php
// admin/leaderboard-management.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// require_once '../connection.php'; // For DB

$error = '';
$success = '';

// $db = (new Database())->getConnection();

// Handle form submission for updating leaderboard configs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Example: updating score weights for certain habits
    // $weightReading = $_POST['weight_reading'] ?? 1;
//  ...
    $success = "Leaderboard configuration updated (placeholder).";
}

// Fetch any existing leaderboard config
// e.g., $config = fetch from DB
$config = [
    'weight_reading' => 2,
    'weight_exercise' => 1,
    // ...
];

// Fetch top scorers globally (placeholder data)
$topScorers = [
    ['parent_name' => 'John Doe', 'total_score' => 1200, 'batch_name' => 'Batch A'],
    ['parent_name' => 'Alice Smith', 'total_score' => 1150, 'batch_name' => 'Batch B'],
    // ...
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leaderboard Management - Admin</title>

    <!-- CSS includes from admin/dashboard.php -->
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
            <h2 class="page-title">Leaderboard Management</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Config Form -->
            <div class="card shadow mb-4">
                <div class="card-header"><strong>Score Weights & Config</strong></div>
                <div class="card-body">
                    <form action="" method="POST" class="form-inline">
                        <label class="mr-2">Daily Reading Weight</label>
                        <input type="number" step="0.1" name="weight_reading" class="form-control mr-4"
                               value="<?php echo htmlspecialchars($config['weight_reading'] ?? 1); ?>">

                        <label class="mr-2">Exercise Weight</label>
                        <input type="number" step="0.1" name="weight_exercise" class="form-control mr-4"
                               value="<?php echo htmlspecialchars($config['weight_exercise'] ?? 1); ?>">

                        <!-- Add more config fields as needed -->
                        <button type="submit" class="btn btn-primary">Update Config</button>
                    </form>
                </div>
            </div>

            <!-- Top Scorers Table -->
            <div class="card shadow">
                <div class="card-header"><strong>Global Top Scorers</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead>
                        <tr>
                            <th>Parent Name</th>
                            <th>Batch</th>
                            <th>Total Score</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topScorers as $scorer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($scorer['parent_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($scorer['total_score']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- End card -->
        </div>
    </main>
</div><!-- End wrapper -->

<?php include 'includes/footer.php'; ?>
</body>
</html>
