<?php
// admin/certificate-management.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
require_once 'includes/fpdf.php'; // Include FPDF Library

$database = new Database();
$db = $database->getConnection();

// Fetch users
$users = [];
$userQuery = "SELECT id, full_name FROM users WHERE role = 'parent' ORDER BY full_name ASC";
$userStmt = $db->prepare($userQuery);
$userStmt->execute();
$userResult = $userStmt->get_result();
while ($row = $userResult->fetch_assoc()) {
    $users[] = $row;
}
$userStmt->close();

// Fetch batches
$batches = [];
$batchQuery = "SELECT id, name FROM batches";
$batchStmt = $db->prepare($batchQuery);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();
while ($row = $batchResult->fetch_assoc()) {
    $batches[] = $row;
}
$batchStmt->close();

// Fetch certificates
$query = "SELECT c.id, 
                 COALESCE(u.username, 'Deleted User') AS user, 
                 COALESCE(u.full_name, 'N/A') AS full_name,
                 COALESCE(b.name, 'N/A') AS batch, 
                 c.milestone, 
                 c.generated_at, 
                 c.certificate_path 
          FROM certificates c
          LEFT JOIN users u ON c.user_id = u.id
          LEFT JOIN batches b ON c.batch_id = b.id
          ORDER BY c.generated_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

// Handle individual student certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_ids'])) {
    $student_ids = $_POST['student_ids'];
    if (!empty($student_ids) && is_array($student_ids)) {
        foreach ($student_ids as $user_id) {
            // Fetch student details
            $userQuery = "SELECT id, full_name, batch_id FROM users WHERE id = ?";
            $userStmt = $db->prepare($userQuery);
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($user = $userResult->fetch_assoc()) {
                $user_name = $user['full_name'];
                $batch_id = $user['batch_id'];
                $milestone = "Course Completion";
                $certificateFilename = "user_{$user_id}_batch_{$batch_id}_" . time() . ".pdf";
                generateCertificate($user_name, $batch_id, $certificateFilename);
                // Insert into DB
                $insertQuery = "INSERT INTO certificates (user_id, batch_id, milestone, certificate_path) VALUES (?, ?, ?, ?)";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bind_param("iiss", $user_id, $batch_id, $milestone, $certificateFilename);
                $insertStmt->execute();
                $insertStmt->close();
            }
            $userStmt->close();
        }
        header("Location: certificate-management.php?success=Certificates generated for selected students.");
        exit();
    }
}

define('FPDF_FONTPATH', __DIR__ . '/includes/font/');

function generateCertificate($user_name, $batch_id, $certificateFilename) {
    $certDir = dirname(__DIR__) . '/certificates/';
    if (!is_dir($certDir)) {
        mkdir($certDir, 0775, true);
    }
    $savePath = $certDir . basename($certificateFilename);
    $templatePath = __DIR__ . "/includes/cert_template.jpg"; // Background image path

    // Fetch course_name for the user
    global $db;
    $course_name = '';
    $user_id = null;
    if (preg_match('/user_(\d+)_batch_/', $certificateFilename, $matches)) {
        $user_id = (int)$matches[1];
    }
    if ($user_id) {
        $stmt = $db->prepare("SELECT course_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($course_name);
        $stmt->fetch();
        $stmt->close();
    }
    if (!$course_name) {
        $course_name = 'the course';
    }

    // Initialize FPDF
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape A4
    $pdf->AddPage();

    // Set Background Image
    $pdf->Image($templatePath, 0, 0, 297, 210); // A4 dimensions (297mm x 210mm)

    // Set Font Directory and Load Helvetica Font
    $pdf->AddFont('helvetica', '', 'helvetica.php');
    $pdf->AddFont('helvetica', 'B', 'helveticab.php');
    $pdf->AddFont('helvetica', 'I', 'helveticai.php');

    // Name Placement (Centered Below "This is to certify that")
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(50, 100); // Adjusted Y for better alignment
    $pdf->Cell(200, 10, $user_name, 0, 1, 'C');

    // Course / Milestone Placement (centered on dotted lines)
    $pdf->SetFont('helvetica', '', 16);
    $pdf->SetXY(100, 120); // Move down to center on dotted lines (adjust as needed)
    $pdf->Cell(200, 10, $course_name, 0, 1, 'C');

    // Date Placement (bottom right, always on the same page)
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->SetXY(10, 160); // Lower and more left to avoid new page
    $pdf->Cell(200, 10, date("d-m-Y"), 0, 1, 'C');

    // Save PDF
    if ($pdf->Output("F", $savePath)) {
        error_log("✅ PDF Generated: " . $savePath);
    } else {
        error_log("❌ PDF Generation Failed: " . $savePath);
    }
}



?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Certificate Management - Habits Web App</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Certificate Management</h2>

            <!-- Generate Certificates for Selected Students -->
            <div class="card shadow mb-4">
                <div class="card-header"><strong>Generate Certificates for Students</strong></div>
                <div class="card-body">
                    <form action="certificate-management.php" method="POST">
                        <label>Select Students</label>
                        <select name="student_ids[]" class="form-control select2-multiple" multiple required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary mt-2">Generate Certificates</button>
                    </form>
                </div>
            </div>

            <!-- Certificates List -->
            <div class="card shadow">
                <div class="card-body">
                    <table id="certificateTable" class="table table-hover table-bordered">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Full Name</th>
                                <th>Batch</th>
                                <th>Milestone</th>
                                <th>Date Issued</th>
                                <th>Certificate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cert = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cert['user']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['batch']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['milestone']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['generated_at']); ?></td>
                                    <td><a href="<?php echo '../certificates/' . htmlspecialchars($cert['certificate_path']); ?>" target="_blank">View</a></td>
                                    <td>
                                        <a href="delete-certificate.php?id=<?php echo $cert['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DataTables -->
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('#certificateTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "order": [[4, "desc"]] // Sort by Date Issued (5th column, 0-based index)
        });
        $('.select2-multiple').select2({
            placeholder: 'Select students',
            width: '100%'
        });
    });
</script>
</body>
</html>
