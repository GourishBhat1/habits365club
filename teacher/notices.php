<?php
session_start();
require_once '../connection.php';

// Check teacher authentication
if (!isset($_SESSION['teacher_email']) && !isset($_COOKIE['teacher_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Get teacher ID from session or cookie
$database = new Database();
$conn = $database->getConnection();

$teacher_id = $_SESSION['teacher_id'] ?? null;

if (!$teacher_id && isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_COOKIE['teacher_email'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher'");
    $stmt->bind_param("s", $teacher_email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($teacher_id);
        $stmt->fetch();
        $_SESSION['teacher_id'] = $teacher_id;
    } else {
        header("Location: index.php?message=invalid_cookie");
        exit();
    }
    $stmt->close();
}

// Fetch teacher location
$stmt = $conn->prepare("SELECT location FROM users WHERE id = ? AND role = 'teacher'");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stmt->bind_result($teacher_location);
$stmt->fetch();
$stmt->close();

// Fetch all notices for this location
$notices = [];
$stmt = $conn->prepare("SELECT n.title, n.message, n.location, n.created_at, u.full_name AS created_by_name
                        FROM notices n
                        LEFT JOIN users u ON n.created_by = u.id
                        WHERE n.location = ?
                        ORDER BY n.created_at DESC");
$stmt->bind_param("s", $teacher_location);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notices[] = $row;
}
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Teacher Dashboard - Notices</title>
    <link rel="stylesheet" href="css/app-light.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Notices (<?php echo htmlspecialchars($teacher_location); ?>)</h2>
            <div class="card shadow">
                <div class="card-header">
                    <strong>Location Notices</strong>
                </div>
                <div class="card-body">
                    <?php if (count($notices) > 0): ?>
                        <table id="noticesTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Location</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notices as $notice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($notice['title']); ?></td>
                                        <td><?php echo $notice['message']; ?></td>
                                        <td><?php echo htmlspecialchars($notice['location']); ?></td>
                                        <td><?php echo htmlspecialchars($notice['created_by_name']); ?></td>
                                        <td><?php echo date("d M Y, h:i A", strtotime($notice['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            No notices available for your location.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script>
    $(document).ready(function() {
        $('#noticesTable').DataTable({
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'B>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-sm btn-info mr-1',
                    title: 'Location Notices',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-sm btn-success mr-1',
                    title: 'Location Notices',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-sm btn-danger',
                    title: 'Location Notices',
                    exportOptions: { columns: ':visible' }
                }
            ],
            order: [[4, "desc"]],
            pageLength: 25
        });
    });
</script>
</body>
</html>
<?php