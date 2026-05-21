<?php
session_start();
require_once '../connection.php';

if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   DELETE
-------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $db->prepare("DELETE FROM quality_assessments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: quality-assessments.php");
    exit();
}

/* -----------------------------
   FILTERS
-------------------------------*/
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$loc    = $_GET['location'] ?? '';
$status = $_GET['progress_status'] ?? '';
$course_status = $_GET['course_status'] ?? '';

/* FETCH DISTINCT CENTERS */
$locations = [];
$res = $db->query("SELECT DISTINCT location FROM users WHERE role='parent' AND location IS NOT NULL AND location != '' ORDER BY location");
while ($row = $res->fetch_assoc()) {
    $locations[] = $row['location'];
}

/* -----------------------------
   FETCH ASSESSMENTS
-------------------------------*/
$sql = "
    SELECT qa.*, u.full_name AS student_name, u.location AS center_name
    FROM quality_assessments qa
    LEFT JOIN users u ON qa.user_id = u.id
    WHERE 1
";
$params = [];
$types = '';

if ($from) {
    $sql .= " AND qa.assessment_date >= ?";
    $params[] = $from;
    $types .= 's';
}
if ($to) {
    $sql .= " AND qa.assessment_date <= ?";
    $params[] = $to;
    $types .= 's';
}
if ($loc) {
    $sql .= " AND u.location = ?";
    $params[] = $loc;
    $types .= 's';
}
if ($status) {
    $sql .= " AND qa.progress_status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($course_status) {
    $sql .= " AND qa.course_completed = ?";
    $params[] = $course_status;
    $types .= 's';
}

$sql .= " ORDER BY qa.id DESC";

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$assessments = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Quality Assessments</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Quality Assessments</h2>

<!-- FILTERS -->
<div class="card shadow mb-4">
<div class="card-body">
<form method="GET" class="form-row">
    <div class="col-md-2">
        <label>From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <label>To</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <label>Center</label>
        <select name="location" class="form-control">
            <option value="">All</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= htmlspecialchars($l) ?>" <?= $loc===$l?'selected':'' ?>><?= htmlspecialchars($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label>Progress</label>
        <select name="progress_status" class="form-control">
            <option value="">All</option>
            <option value="satisfactory" <?= $status==='satisfactory'?'selected':'' ?>>Satisfactory</option>
            <option value="needs_improvement" <?= $status==='needs_improvement'?'selected':'' ?>>Needs Improvement</option>
        </select>
    </div>
    <div class="col-md-2">
        <label>Course Status</label>
        <select name="course_status" class="form-control">
            <option value="">All</option>
            <option value="active" <?= $course_status==='active'?'selected':'' ?>>Active</option>
            <option value="completed" <?= $course_status==='completed'?'selected':'' ?>>Completed</option>
            <option value="break" <?= $course_status==='break'?'selected':'' ?>>Break</option>
            <option value="stopped" <?= $course_status==='stopped'?'selected':'' ?>>Stopped</option>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary btn-block">Apply</button>
    </div>
</form>
</div>
</div>

<!-- TABLE -->
<div class="card shadow">
<div class="card-body">
<table id="qaTable" class="table table-bordered table-striped">
<thead>
<tr>
    <th>#</th>
    <th>Student</th>
    <th>Phone</th>
    <th>Assessment</th>
    <th>Date</th>
    <th>Teacher</th>
    <th>Subject</th>
    <th>Progress</th>
    <th>Course Status</th>
    <th>Center</th>
    <th>Assessor</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php while ($r = $assessments->fetch_assoc()): ?>
<tr>
    <td><?= $r['id'] ?></td>
    <td><?= htmlspecialchars($r['child_name'] ?: $r['student_name']) ?></td>
    <td><?= htmlspecialchars($r['mobile']) ?></td>
    <td><?= ($r['assessment_number']==1?'15 Day':'28 Day') ?></td>
    <td><?= date('d M Y', strtotime($r['assessment_date'])) ?></td>
    <td><?= htmlspecialchars($r['teacher_name']) ?></td>
    <td><?= htmlspecialchars($r['subject'] ?? '') ?></td>
    <td>
        <?php $ps = $r['progress_status'] ?? ''; ?>
        <span class="badge <?= $ps==='satisfactory'?'badge-success':'badge-warning' ?>">
            <?= $ps ? ucfirst(str_replace('_',' ',$ps)) : '-' ?>
        </span>
    </td>
    <td><?= !empty($r['course_completed']) ? ucfirst($r['course_completed']) : '-' ?></td>
    <td><?= htmlspecialchars($r['center_name'] ?? '') ?></td>
    <td><?= htmlspecialchars($r['assessor_name']) ?></td>
    <td>
        <form method="POST"
              onsubmit="return confirm('Delete assessment #<?= $r['id'] ?> for <?= htmlspecialchars(addslashes($r['child_name'] ?: $r['student_name'])) ?>? This cannot be undone.');"
              style="display:inline;">
            <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">
                Delete
            </button>
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

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
$(function () {
    $('#qaTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['excel','csv','pdf','print'],
        order: [[0, 'desc']],
        pageLength: 25
    });
});
</script>
</body>
</html>
