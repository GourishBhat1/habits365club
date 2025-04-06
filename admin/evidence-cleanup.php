<?php
// admin/evidence-cleanup.php

// Start session for manual execution
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
        header("Location: index.php");
        exit();
    }
}

require_once '../connection.php';

// Establish database connection
$database = new Database();
$db = $database->getConnection();

// Set cleanup parameters (delete evidence older than X days)
$error = '';
$success = '';
$log_file = "../logs/evidence_cleanup.log"; // Log file for tracking cleanup

/**
 * Cleanup old evidence files
 *
 * @param mysqli $db
 * @param string $log_file
 * @return int Number of deleted files
 */
function cleanupEvidence($db, $log_file) {
    $deleted_files = 0;

    // Select old evidence files
    $query = "SELECT id, file_path FROM evidence_uploads";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $file_path = '../uploads/' . $row['file_path']; // Adjust path if needed
        if (file_exists($file_path) && unlink($file_path)) {
            $deleted_files++;

            // Log the deleted file
            file_put_contents($log_file, date("[Y-m-d H:i:s]") . " Deleted: " . $file_path . "\n", FILE_APPEND);
        }
    }

    $stmt->close();
    return $deleted_files;
}

// Execute cleanup manually via UI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleted_files = cleanupEvidence($db, $log_file);
    file_put_contents($log_file, date("[Y-m-d H:i:s]") . " Manual cleanup executed. $deleted_files files deleted.\n", FILE_APPEND);
    $success = "$deleted_files old evidence files have been deleted.";
}

// Execute cleanup via cron job (CLI mode)
if (php_sapi_name() === 'cli') {
    $deleted_files = cleanupEvidence($db, $log_file);
    file_put_contents($log_file, date("[Y-m-d H:i:s]") . " Cron cleanup executed. $deleted_files files deleted.\n", FILE_APPEND);
    echo "$deleted_files old evidence files have been deleted.\n";
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
                    <p>Click the button below to delete <strong>all</strong> uploaded evidence files from the server. Database records will remain untouched.</p>
                    <form action="evidence-cleanup.php" method="POST">
                        <button type="submit" class="btn btn-danger">Run Manual Cleanup</button>
                    </form>
                </div>
            </div>

            <div class="card shadow mt-3">
                <div class="card-header">
                    <strong>Automated Cleanup via Cron</strong>
                </div>
                <div class="card-body">
                    <p>To set up automatic evidence cleanup, add the following cron job:</p>
                    <pre><code>0 3 * * 0 /usr/bin/php /path_to_your_project/admin/evidence-cleanup.php</code></pre>
                    <p>This will run the script every <strong>Sunday at 3 AM</strong> and delete <strong>all</strong> uploaded evidence files. Database records will remain untouched.</p>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
