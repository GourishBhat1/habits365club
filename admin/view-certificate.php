<?php
// admin/view-certificate.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include the database connection
require_once __DIR__ . '/../connection.php';

// Get certificate ID from GET parameter
$certificate_id = $_GET['id'] ?? '';

if (empty($certificate_id)) {
    header("Location: certificate-management.php?error=Certificate ID missing.");
    exit();
}

// Fetch certificate details
$query = "SELECT
            certificates.id,
            users.username,
            certificates.milestone,
            certificates.certificate_path,
            certificates.generated_at
          FROM certificates
          JOIN users ON certificates.user_id = users.id
          WHERE certificates.id = ?";

$stmt = $db->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $certificate = $result->fetch_assoc();
    } else {
        header("Location: certificate-management.php?error=Certificate not found.");
        exit();
    }
    $stmt->close();
} else {
    header("Location: certificate-management.php?error=Database error.");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>View Certificate - Habits Web App</title>
    <style>
        .certificate-container {
            text-align: center;
            margin-top: 50px;
        }
        .certificate-container img {
            max-width: 90%;
            height: auto;
            border: 2px solid #000;
        }
        .btn-back {
            margin-top: 20px;
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
            <h2 class="page-title">View Certificate</h2>
            <div class="card shadow">
                <div class="card-body">
                    <div class="certificate-container">
                        <img src="../<?php echo htmlspecialchars($certificate['certificate_path']); ?>" alt="Certificate">
                        <div class="btn-back">
                            <a href="certificate-management.php" class="btn btn-secondary">Back to Management</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
