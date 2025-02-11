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
$userQuery = "SELECT id, username FROM users WHERE role = 'parent'";
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

// Handle batch certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_batch_certificate'])) {
    $batch_id = $_POST['batch_id'];

    if (!empty($batch_id)) {
        // Fetch all parents in the selected batch
        $batchUsersQuery = "SELECT id, username FROM users WHERE batch_id = ?";
        $batchUsersStmt = $db->prepare($batchUsersQuery);
        $batchUsersStmt->bind_param("i", $batch_id);
        $batchUsersStmt->execute();
        $batchUsersResult = $batchUsersStmt->get_result();

        while ($user = $batchUsersResult->fetch_assoc()) {
            $user_id = $user['id'];
            $user_name = $user['username'];
            $milestone = "Course Completion - Batch $batch_id";
            $certificatePath = "certificates/user_${user_id}_batch_${batch_id}.pdf";

            // Generate PDF Certificate
            generateCertificate($user_name, $batch_id, $certificatePath);

            // Insert into DB
            $insertQuery = "INSERT INTO certificates (user_id, batch_id, milestone, certificate_path) VALUES (?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bind_param("iiss", $user_id, $batch_id, $milestone, $certificatePath);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $batchUsersStmt->close();
        header("Location: certificate-management.php?success=Certificates generated for the batch.");
        exit();
    }
}

define('FPDF_FONTPATH', __DIR__ . '/includes/font/');

function generateCertificate($user_name, $batch_id, $certificatePath) {
    $savePath = __DIR__ . "/certificates/" . basename($certificatePath);
    $templatePath = __DIR__ . "/includes/cert_template.jpg"; // Background image path

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

    // Course / Milestone Placement
    $pdf->SetFont('helvetica', '', 16);
    $pdf->SetXY(50, 130);
    $pdf->Cell(200, 10, "For successfully completing Batch $batch_id", 0, 1, 'C');

    // Date Placement (Aligned with "DATE" Field)
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->SetXY(57, 175); // Adjusted placement for correct positioning
    $pdf->Cell(50, 10, date("d-m-Y"), 0, 1, 'C');

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
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Certificate Management</h2>

            <!-- Generate Certificates for a Batch -->
            <div class="card shadow mb-4">
                <div class="card-header"><strong>Generate Batch Certificates</strong></div>
                <div class="card-body">
                    <form action="certificate-management.php" method="POST">
                        <label>Select Batch</label>
                        <select name="batch_id" class="form-control">
                            <option value="">Select a Batch</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="generate_batch_certificate" class="btn btn-primary mt-2">Generate Certificates</button>
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
                                    <td><?php echo htmlspecialchars($cert['batch']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['milestone']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['generated_at']); ?></td>
                                    <td><a href="<?php echo htmlspecialchars($cert['certificate_path']); ?>" target="_blank">View</a></td>
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
<script>
    $(document).ready(function () {
        $('#certificateTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true
        });
    });
</script>
</body>
</html>
