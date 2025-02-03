<?php
// admin/certificate-management.php

session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT c.id, u.username AS user, c.milestone, c.generated_at, c.certificate_path 
          FROM certificates c 
          JOIN users u ON c.user_id = u.id";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Certificate Management - Habits Web App</title>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Certificate Management</h2>
            <div class="card shadow">
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
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
</body>
</html>
