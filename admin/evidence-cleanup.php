<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Set cleanup parameters
$error = '';
$success = '';
$log_file = "../logs/evidence_cleanup.log"; // Log file for tracking cleanup
$upload_dir = 'uploads/';
$downloads_dir = __DIR__ . '/../downloads/';
$total_size = 0;
$max_display_size = 26843545600; // 25 GB limit for display bar (in bytes)

// Function to calculate folder size
function folderSize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

// Calculate folder size and progress
$total_size = folderSize($upload_dir);
$progress_percent = min(100, ($total_size / $max_display_size) * 100);
$display_size = round($total_size / (1024 * 1024), 2); // MB

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    switch ($_GET['action']) {
        case 'start_zip':
            try {
                // Clean downloads folder
                if (file_exists($downloads_dir)) {
                    foreach (new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($downloads_dir, FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    ) as $file) {
                        if ($file->isDir()) {
                            rmdir($file->getRealPath());
                        } else {
                            unlink($file->getRealPath());
                        }
                    }
                }

                // Create new zip operation record
                $stmt = $db->prepare("INSERT INTO zip_operations (status) VALUES ('processing')");
                if (!$stmt) {
                    throw new Exception("Database prepare error: " . $db->error);
                }
                if (!$stmt->execute()) {
                    throw new Exception("Database execute error: " . $stmt->error);
                }
                $operation_id = $db->insert_id;
                $stmt->close();

                // Start zipping process
                $zip = new ZipArchive();
                $zip_name = $downloads_dir . 'evidence_backup_' . date("Ymd_His") . '.zip';

                if ($zip->open($zip_name, ZipArchive::CREATE) !== TRUE) {
                    throw new Exception("Cannot create zip file");
                }

                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($upload_dir),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen(realpath($upload_dir)) + 1);
                        if (!$zip->addFile($filePath, $relativePath)) {
                            throw new Exception("Failed to add file to zip: " . $filePath);
                        }
                    }
                }

                if (!$zip->close()) {
                    throw new Exception("Failed to close zip file");
                }

                if (file_exists($zip_name) && filesize($zip_name) > 0) {
                    $stmt = $db->prepare("UPDATE zip_operations SET status = 'ready', zip_file = ? WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception("Database prepare error: " . $db->error);
                    }
                    $stmt->bind_param("si", $zip_name, $operation_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Database execute error: " . $stmt->error);
                    }
                    $stmt->close();

                    echo json_encode([
                        'status' => 'ready',
                        'operation_id' => $operation_id
                    ]);
                } else {
                    throw new Exception("Zip file creation failed");
                }
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;

        case 'download':
            $operation_id = (int)$_GET['operation_id'];
            $stmt = $db->prepare("SELECT zip_file FROM zip_operations WHERE id = ? AND status = 'ready'");
            $stmt->bind_param("i", $operation_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result && file_exists($result['zip_file'])) {
                header('Content-Type: application/zip');
                header('Content-disposition: attachment; filename=' . basename($result['zip_file']));
                header('Content-Length: ' . filesize($result['zip_file']));
                readfile($result['zip_file']);

                // Update status and cleanup
                $stmt = $db->prepare("UPDATE zip_operations SET status = 'downloaded' WHERE id = ?");
                $stmt->bind_param("i", $operation_id);
                $stmt->execute();

                unlink($result['zip_file']); // Delete the ZIP file after download
                exit;
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'ZIP file not found or operation not ready']);
                exit;
            }
    }
}

// Manual cleanup logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleted_files = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        if ($file->isFile()) {
            unlink($file->getRealPath());
            $deleted_files++;
        }
    }
    $success = "$deleted_files old evidence files have been deleted.";
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
    <?php include 'includes/navbar.php'; ?>
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
                    <a href="?action=start_zip" class="btn btn-primary" id="downloadZipBtn">
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
<?php include 'includes/footer.php'; ?>
<script>
let currentOperationId = null;
const zipBtn = document.getElementById('downloadZipBtn');
const zipText = document.getElementById('zipText');
const zipSpinner = document.getElementById('zipSpinner');

if (zipBtn) {
    zipBtn.addEventListener('click', async function (e) {
        e.preventDefault();

        if (!zipBtn.dataset.ready) {
            // Start zip operation
            zipText.textContent = 'Creating ZIP...';
            zipSpinner.classList.remove('d-none');
            zipBtn.classList.add('disabled');

            try {
                const response = await fetch('?action=start_zip');
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                if (data.status === 'ready') {
                    currentOperationId = data.operation_id;
                    zipText.textContent = 'Download ZIP';
                    zipSpinner.classList.add('d-none');
                    zipBtn.classList.remove('disabled');
                    zipBtn.dataset.ready = 'true';
                }
            } catch (error) {
                alert('Failed to create zip: ' + error.message);
                resetButton();
            }
        } else if (zipBtn.dataset.ready === 'true') {
            // Trigger download
            window.location.href = `?action=download&operation_id=${currentOperationId}`;
            resetButton();
        }
    });
}

function resetButton() {
    currentOperationId = null;
    zipText.textContent = 'Download ZIP Archive';
    zipSpinner.classList.add('d-none');
    zipBtn.classList.remove('disabled');
    delete zipBtn.dataset.ready;
}
</script>
</body>
</html>
