<?php
// admin/evidence-cleanup.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Set cleanup parameters (e.g., delete evidence older than 7 days)
$days_old = 7;
$error = '';
$success = '';

function cleanupEvidence($db, $days_old) {
    // Select old evidence files
    $query = "SELECT id, file_path FROM evidence_uploads WHERE uploaded_at < NOW() - INTERVAL ? DAY";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $days_old);
    $stmt->execute();
    $result = $stmt->get_result();

    $deleted_files = 0;
    while ($row = $result->fetch_assoc()) {
        $file_path = '../uploads/' . $row['file_path']; // Adjust path if needed
        if (file_exists($file_path) && unlink($file_path)) {
            // Delete record from DB
            $deleteQuery = "DELETE FROM evidence_uploads WHERE id = ?";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $row['id']);
            $deleteStmt->execute();
            $deleted_files++;
        }
    }

    $stmt->close();
    return $deleted_files;
}

// Execute cleanup if manually triggered
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleted_files = cleanupEvidence($db, $days_old);
    $success = "$deleted_files old evidence files have been deleted.";
}

// CRON execution (No UI output)
if (php_sapi_name() === 'cli') {
    cleanupEvidence($db, $days_old);
    exit();
}

?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Evidence Cleanup - Admin</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Evidence Cleanup</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header">
                    <strong>Manual Cleanup</strong>
                </div>
                <div class="card-body">
                    <p>Click the button below to delete habit evidence older than <?php echo $days_old; ?> days.</p>
                    <form action="evidence-cleanup.php" method="POST">
                        <button type="submit" class="btn btn-danger">Run Manual Cleanup</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
