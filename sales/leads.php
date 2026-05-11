<?php
session_start();
require_once '../connection.php';

if (!isset($_SESSION['sales_username']) && !isset($_COOKIE['sales_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$sales_id = $_SESSION['sales_id'] ?? 0;

// STATUS FILTER
$status_filter = $_GET['status'] ?? '';

$query = "
    SELECT l.*, 
           (SELECT COUNT(*) FROM lead_followups WHERE lead_id = l.id AND status = 'pending') as pending_followups
    FROM leads l
    WHERE l.assigned_to = ?
";
$params = [$sales_id];
$types = "i";

if (!empty($status_filter)) {
    $query .= " AND l.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}
$stmt->close();
?>
<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>All Leads - Sales</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">All Leads</h2>

<div class="card shadow mb-3 p-3">
<form method="GET" class="form-inline">
    <div class="form-group mr-2">
        <label>Status:</label>
        <select name="status" class="form-control ml-2">
            <option value="">All</option>
            <option value="new" <?= $status_filter=='new'?'selected':'' ?>>New</option>
            <option value="contacted" <?= $status_filter=='contacted'?'selected':'' ?>>Contacted</option>
            <option value="follow_up" <?= $status_filter=='follow_up'?'selected':'' ?>>Follow Up</option>
            <option value="assessment_booked" <?= $status_filter=='assessment_booked'?'selected':'' ?>>Assessment Booked</option>
            <option value="registered" <?= $status_filter=='registered'?'selected':'' ?>>Registered</option>
            <option value="not_interested" <?= $status_filter=='not_interested'?'selected':'' ?>>Not Interested</option>
            <option value="lost" <?= $status_filter=='lost'?'selected':'' ?>>Lost</option>
        </select>
    </div>
    <button class="btn btn-primary">Filter</button>
    <a href="leads.php" class="btn btn-secondary ml-2">Reset</a>
    <a href="add-lead.php" class="btn btn-success ml-2">+ Add Lead</a>
</form>
</div>

<div class="card shadow">
<div class="card-body">

<table id="leadsTable" class="table table-bordered">
<thead>
<tr>
    <th>Name</th>
    <th>Phone</th>
    <th>Child</th>
    <th>Source</th>
    <th>Status</th>
    <th>Follow-ups</th>
    <th>Created</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($leads as $l): ?>
<tr>
    <td><?= htmlspecialchars($l['full_name']) ?></td>
    <td><a href="tel:<?= $l['phone'] ?>"><?= htmlspecialchars($l['phone']) ?></a></td>
    <td><?= htmlspecialchars($l['child_name']) ?></td>
    <td><?= htmlspecialchars($l['lead_source']) ?></td>
    <td>
        <?php
        $badge = 'secondary';
        if ($l['status'] == 'new') $badge = 'info';
        elseif ($l['status'] == 'contacted') $badge = 'primary';
        elseif ($l['status'] == 'follow_up') $badge = 'warning';
        elseif ($l['status'] == 'assessment_booked') $badge = 'dark';
        elseif ($l['status'] == 'registered') $badge = 'success';
        elseif ($l['status'] == 'not_interested') $badge = 'danger';
        elseif ($l['status'] == 'lost') $badge = 'secondary';
        ?>
        <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($l['status']) ?></span>
    </td>
    <td><?= $l['pending_followups'] ?></td>
    <td><?= $l['created_at'] ?></td>
    <td>
        <a href="view-lead.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-info">View</a>
        <a href="tel:<?= $l['phone'] ?>" class="btn btn-sm btn-success">Call</a>
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
        order: [[6, 'desc']]
    });
});
</script>
</body>
</html>
