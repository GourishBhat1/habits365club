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

$upload_dir = 'uploads/';
$total_size = 0;
$max_display_size = 2097152000; // 2000 MB limit for display bar

function folderSize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

$total_size = folderSize($upload_dir);
$progress_percent = min(100, ($total_size / $max_display_size) * 100);
$display_size = round($total_size / (1024 * 1024), 2); // MB

if (isset($_GET['download_zip']) && $_GET['download_zip'] === '1') {
    $zip = new ZipArchive();
    $zip_name = '../downloads/evidence_backup_' . date("Ymd_His") . '.zip';

    if (!file_exists('../downloads')) {
        mkdir('../downloads', 0777, true);
    }

    if ($zip->open($zip_name, ZipArchive::CREATE) === TRUE) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir));
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(realpath($upload_dir)) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        
        // Check if ZIP file is valid and not empty
        if (file_exists($zip_name) && filesize($zip_name) > 0) {
            header('Content-Type: application/zip');
            header('Content-disposition: attachment; filename=' . basename($zip_name));
            header('Content-Length: ' . filesize($zip_name));
            flush();
            readfile($zip_name);
            unlink($zip_name);
            exit;
        } else {
            $error = "Zip creation failed or the file is empty. Please try again.";
        }
    }
}

function cleanupEvidence($upload_dir, $log_file) {
    $deleted_files = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        if ($file->isFile()) {
            unlink($file->getRealPath());
            $deleted_files++;
            file_put_contents($log_file, date("[Y-m-d H:i:s]") . " Deleted: " . $file->getRealPath() . "\n", FILE_APPEND);
        }
    }
    return $deleted_files;
}

// Execute cleanup manually via UI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleted_files = cleanupEvidence($upload_dir, $log_file);
    file_put_contents($log_file, date("[Y-m-d H:i:s]") . " Manual cleanup executed. $deleted_files files deleted.\n", FILE_APPEND);
    $success = "$deleted_files old evidence files have been deleted.";
}

// Execute cleanup via cron job (CLI mode)
if (php_sapi_name() === 'cli') {
    $deleted_files = cleanupEvidence($upload_dir, $log_file);
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

            <div class="card shadow mb-4">
              <div class="card-header">
                <strong>Uploads Folder Size</strong>
              </div>
              <div class="card-body">
                <p>Current folder size: <strong><?php echo $display_size; ?> MB</strong></p>
                <div class="progress mb-2" style="height: 20px;">
                  <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $progress_percent; ?>%;">
                    <?php echo round($progress_percent); ?>%
                  </div>
                </div>
                <a href="?download_zip=1" class="btn btn-primary" id="downloadZipBtn">
                  <span id="zipText">Download ZIP Archive</span>
                  <span id="zipSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </a>
              </div>
            </div>

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

        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
<script>
  const zipBtn = document.getElementById('downloadZipBtn');
  if (zipBtn) {
    zipBtn.addEventListener('click', function() {
      document.getElementById('zipText').textContent = 'Processing...';
      document.getElementById('zipSpinner').classList.remove('d-none');
    });
  }
</script>
</body>
</html>
