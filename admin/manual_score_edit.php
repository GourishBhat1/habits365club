<?php
session_start();
require_once '../connection.php';

// Check admin authentication
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $new_points = intval($_POST['new_points']);
    if ($new_points == 1) {
        $error = "Score cannot be 1.";
    } else {
        $stmt = $db->prepare("UPDATE evidence_uploads SET points = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_points, $edit_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $stmt = $db->prepare("DELETE FROM evidence_uploads WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch manual scores
$query = "
    SELECT e.id, e.points, e.uploaded_at, u.full_name, b.name AS batch_name, e.parent_id
    FROM evidence_uploads e
    JOIN users u ON e.parent_id = u.id
    JOIN batches b ON u.batch_id = b.id
    WHERE e.points != 1 AND e.status = 'approved'
    ORDER BY e.uploaded_at DESC
";
$result = $db->query($query);
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Manual Score Management - Admin</title>
    <link rel="stylesheet" href="css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title mb-4">Manual Scores Added by Incharge</h2>
            <div class="card shadow">
                <div class="card-body">
                    <small class="text-muted">Score must not be 1. Use 0 for removal or >1 for manual scores.</small>
                    <table id="manualScoresTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Batch</th>
                                <th>Score</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="edit_id" value="<?php echo $row['id']; ?>">
                                        <input type="number" name="new_points" value="<?php echo $row['points']; ?>" min="0" class="form-control d-inline-block" style="width:80px;" required>
                                        <button type="submit" class="btn btn-sm btn-info ml-1">Update</button>
                                    </form>
                                </td>
                                <td><?php echo htmlspecialchars($row['uploaded_at']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this score?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
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
<script src="js/select2.min.js"></script>
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
        $('#manualScoresTable').DataTable({
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'B>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-sm btn-info mr-1',
                    title: 'Manual Scores',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-sm btn-success mr-1',
                    title: 'Manual Scores',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-sm btn-danger',
                    title: 'Manual Scores',
                    exportOptions: { columns: ':visible' }
                }
            ],
            order: [[3, "desc"]],
            pageLength: 25
        });

        document.querySelectorAll('input[name="new_points"]').forEach(function(input) {
            input.addEventListener('input', function() {
                if (this.value == "1") {
                    this.setCustomValidity("Score cannot be 1.");
                } else {
                    this.setCustomValidity("");
                }
            });
        });
    });
</script>
</body>
</html>
<?php
$db->close();
?>