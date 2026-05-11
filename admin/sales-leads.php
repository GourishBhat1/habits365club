<?php
session_start();
require_once '../connection.php';

if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Handle reassign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign'])) {
    $lead_id = (int)($_POST['lead_id'] ?? 0);
    $new_sales_id = (int)($_POST['new_sales_id'] ?? 0);

    if ($lead_id && $new_sales_id) {
        $stmt = $db->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $new_sales_id, $lead_id);
        if ($stmt->execute()) {
            $success = "Lead reassigned successfully.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all sales users
$sales_users = [];
$stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE role = 'sales' AND status = 'active' ORDER BY full_name ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sales_users[] = $row;
}
$stmt->close();

// Get all leads with their assigned sales info
$leads = [];
$stmt = $db->prepare("
    SELECT l.*, u.full_name as sales_name, u.username as sales_username
    FROM leads l
    LEFT JOIN users u ON l.assigned_to = u.id
    ORDER BY l.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link rel="stylesheet" href="css/simplebar.css">
<link rel="stylesheet" href="css/feather.css">
<link rel="stylesheet" href="css/select2.css">
<link rel="stylesheet" href="css/dropzone.css">
<link rel="stylesheet" href="css/uppy.min.css">
<link rel="stylesheet" href="css/jquery.steps.css">
<link rel="stylesheet" href="css/jquery.timepicker.css">
<link rel="stylesheet" href="css/quill.snow.css">
<link rel="stylesheet" href="css/daterangepicker.css">
<link rel="stylesheet" href="css/dataTables.bootstrap4.css">
<link rel="stylesheet" href="css/app-light.css" id="lightTheme">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<title>Sales Leads - Admin</title>
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Sales Lead Management</h2>

<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card shadow">
<div class="card-body">

<table id="leadsTable" class="table table-bordered">
<thead>
<tr>
    <th>Name</th>
    <th>Phone</th>
    <th>Status</th>
    <th>Source</th>
    <th>Assigned To</th>
    <th>Created</th>
    <th>Reassign</th>
</tr>
</thead>
<tbody>
<?php foreach ($leads as $l): ?>
<tr>
    <td><?= htmlspecialchars($l['full_name']) ?></td>
    <td><a href="tel:<?= $l['phone'] ?>"><?= htmlspecialchars($l['phone']) ?></a></td>
    <td><?= htmlspecialchars($l['status']) ?></td>
    <td><?= htmlspecialchars($l['lead_source']) ?></td>
    <td><?= htmlspecialchars($l['sales_name'] ?: 'Unassigned') ?></td>
    <td><?= $l['created_at'] ?></td>
    <td>
        <form method="POST" class="form-inline">
            <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
            <select name="new_sales_id" class="form-control form-control-sm mr-1" required>
                <option value="">Select Sales</option>
                <?php foreach ($sales_users as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $l['assigned_to'] == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['username']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="reassign" class="btn btn-sm btn-primary">Update</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>

</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script>
$(function(){
    $('#leadsTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['excel','csv','print'],
        pageLength: 25,
        order: [[5, 'desc']]
    });
});
</script>
</body>
</html>
