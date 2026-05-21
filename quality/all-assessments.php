<?php
session_start();

// AUTH
if (!isset($_SESSION['quality_username']) && !isset($_COOKIE['quality_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// MONTHLY ANALYTICS
$currentMonth = date('Y-m');

$monthly_total = 0;
$monthly_improvement = 0;

$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(progress_status='needs_improvement') as improvement
    FROM quality_assessments
    WHERE DATE_FORMAT(assessment_date, '%Y-%m') = ?
");
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$stmt->bind_result($monthly_total, $monthly_improvement);
$stmt->fetch();
$stmt->close();

// FILTERS
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$status = $_GET['status'] ?? '';
$teacher = $_GET['teacher'] ?? '';
$subject = $_GET['subject'] ?? '';
$center = $_GET['center'] ?? '';

$query = "
    SELECT qa.*, u.location AS center_name
    FROM quality_assessments qa
    LEFT JOIN users u ON qa.user_id = u.id
    WHERE 1
";

$params = [];
$types = "";

// DATE FILTER
if (!empty($from_date)) {
    $query .= " AND qa.assessment_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $query .= " AND qa.assessment_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

// STATUS FILTER
if (!empty($status)) {
    $query .= " AND qa.progress_status = ?";
    $params[] = $status;
    $types .= "s";
}

// TEACHER FILTER
if (!empty($teacher)) {
    $query .= " AND qa.teacher_name = ?";
    $params[] = $teacher;
    $types .= "s";
}

// SUBJECT FILTER
if (!empty($subject)) {
    $query .= " AND qa.subject = ?";
    $params[] = $subject;
    $types .= "s";
}

// CENTER FILTER
if (!empty($center)) {
    $query .= " AND u.location = ?";
    $params[] = $center;
    $types .= "s";
}

$query .= " ORDER BY qa.id DESC";

$stmt = $db->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$assessments = [];
while ($row = $result->fetch_assoc()) {
    $assessments[] = $row;
}

$stmt->close();
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>All Assessments</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">All Assessments</h2>

<div class="row mb-4">

<div class="col-md-6">
<div class="card shadow text-center p-3">
<h6>This Month Assessments</h6>
<h3><?= $monthly_total ?></h3>
</div>
</div>

<div class="col-md-6">
<div class="card shadow text-center p-3">
<h6>Needs Improvement (Month)</h6>
<h3><?= $monthly_improvement ?></h3>
</div>
</div>

</div>

<!-- FILTERS -->
<div class="card shadow mb-3 p-3">
<form method="GET" class="form-inline">

<div class="form-group mr-2">
<label>From:</label>
<input type="date" name="from_date" class="form-control ml-2" value="<?= $from_date ?>">
</div>

<div class="form-group mr-2">
<label>To:</label>
<input type="date" name="to_date" class="form-control ml-2" value="<?= $to_date ?>">
</div>

<div class="form-group mr-2">
<label>Status:</label>
<select name="status" class="form-control ml-2">
<option value="">All</option>
<option value="satisfactory" <?= $status=='satisfactory'?'selected':'' ?>>Satisfactory</option>
<option value="needs_improvement" <?= $status=='needs_improvement'?'selected':'' ?>>Needs Improvement</option>
</select>
</div>

<div class="form-group mr-2">
<label>Teacher:</label>
<input type="text" name="teacher" class="form-control ml-2" value="<?= $teacher ?>">
</div>

<div class="form-group mr-2">
<label>Subject:</label>
<input type="text" name="subject" class="form-control ml-2" value="<?= $subject ?>">
</div>

<div class="form-group mr-2">
<label>Center:</label>
<input type="text" name="center" class="form-control ml-2" value="<?= $center ?>">
</div>

<button class="btn btn-primary">Filter</button>

<a href="all-assessments.php" class="btn btn-secondary ml-2">Reset</a>

</form>
</div>

<!-- TABLE -->
<div class="card shadow">
<div class="card-body">

<table id="assessmentTable" class="table table-bordered">

<thead>
<tr>
<th>Name</th>
<th>Mobile</th>
<th>Date</th>
<th>Assessment</th>
<th>Teacher</th>
<th>Subject</th>
<th>Progress</th>
<th>Course Status</th>
<th>Follow-up</th>
<th>Center</th>
<th>Assessor</th>
<th>Content</th>
<th>Remarks</th>
<th>Video</th>
</tr>
</thead>

<tbody>

<?php foreach($assessments as $a): ?>
<tr>

<td><?= htmlspecialchars($a['child_name']) ?></td>

<td>
<a href="tel:<?= $a['mobile'] ?>">
<?= htmlspecialchars($a['mobile']) ?>
</a>
</td>

<td><?= $a['assessment_date'] ?></td>

<td><?= $a['assessment_number'] == 1 ? '15 Day' : '28 Day' ?></td>

<td><?= htmlspecialchars($a['teacher_name'] ?? '') ?></td>

<td><?= htmlspecialchars($a['subject'] ?? '') ?></td>

<td>
<?php $aps = $a['progress_status'] ?? ''; ?>
<?php if($aps=='needs_improvement'): ?>
<span class="badge badge-danger">Needs Improvement</span>
<?php elseif($aps=='satisfactory'): ?>
<span class="badge badge-success">Satisfactory</span>
<?php else: ?>
<span class="badge badge-secondary">-</span>
<?php endif; ?>
</td>

<td><?= !empty($a['course_completed']) ? ucfirst($a['course_completed']) : '-' ?></td>

<td><?= !empty($a['next_followup']) ? $a['next_followup'] : '-' ?></td>

<td><?= htmlspecialchars($a['center_name'] ?? '') ?></td>

<td><?= htmlspecialchars($a['assessor_name']) ?></td>

<td><?= htmlspecialchars($a['content_covered']) ?></td>

<td><?= htmlspecialchars($a['remarks']) ?></td>

<td>
<?php if(!empty($a['video_path'])): ?>
<a href="<?= $a['video_path'] ?>" target="_blank" class="btn btn-sm btn-info">View</a>
<?php else: ?>
-
<?php endif; ?>
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

$('#assessmentTable').DataTable({
    dom: 'Bfrtip',
    buttons: ['excel','csv','print'],
    pageLength: 25,
    order: [[2, 'desc']]
});

});
</script>

</body>
</html>