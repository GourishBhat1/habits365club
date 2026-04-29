<?php
session_start();
require_once '../connection.php';

// AUTH CHECK
if (!isset($_SESSION['quality_username']) && !isset($_COOKIE['quality_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   FETCH COUNTS (CARDS)
------------------------------*/

// Due Today (15 / 28 days)
$due_today = 0;
$stmt = $db->prepare("
    SELECT COUNT(*) FROM (
        SELECT u.id,
               DATEDIFF(CURDATE(), u.created_at) as days_since,
               CASE 
                   WHEN DATEDIFF(CURDATE(), u.created_at) >= 28 THEN 2
                   ELSE 1
               END as required_assessment
        FROM users u
        WHERE u.role='parent' AND u.status='active'
        HAVING days_since IN (15,28)
        AND NOT EXISTS (
            SELECT 1 FROM quality_assessments qa
            WHERE qa.user_id = u.id
            AND qa.assessment_number = required_assessment
        )
    ) t
");
$stmt->execute();
$stmt->bind_result($due_today);
$stmt->fetch();
$stmt->close();

// Overdue
$overdue = 0;
$stmt = $db->prepare("
    SELECT COUNT(*) FROM (
        SELECT u.id,
               DATEDIFF(CURDATE(), u.created_at) as days_since,
               CASE 
                   WHEN DATEDIFF(CURDATE(), u.created_at) >= 28 THEN 2
                   ELSE 1
               END as required_assessment
        FROM users u
        WHERE u.role='parent' 
        AND u.status='active'
        HAVING days_since > 28
        AND NOT EXISTS (
            SELECT 1 FROM quality_assessments qa
            WHERE qa.user_id = u.id
            AND qa.assessment_number = required_assessment
        )
    ) t
");
$stmt->execute();
$stmt->bind_result($overdue);
$stmt->fetch();
$stmt->close();

// Completed Today
$completed_today = 0;
$stmt = $db->prepare("
    SELECT COUNT(*) FROM quality_assessments
    WHERE DATE(created_at) = CURDATE()
");
$stmt->execute();
$stmt->bind_result($completed_today);
$stmt->fetch();
$stmt->close();

// Needs Improvement
$needs_improvement = 0;
$stmt = $db->prepare("
    SELECT COUNT(*) FROM quality_assessments
    WHERE progress_status = 'needs_improvement'
");
$stmt->execute();
$stmt->bind_result($needs_improvement);
$stmt->fetch();
$stmt->close();


/* -----------------------------
   STUDENTS DUE TABLE
------------------------------*/
$students_due = [];
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.phone,
        u.created_at,
        DATEDIFF(CURDATE(), u.created_at) as days_since,
        CASE 
            WHEN DATEDIFF(CURDATE(), u.created_at) >= 28 THEN 2
            ELSE 1
        END as required_assessment
    FROM users u
    WHERE u.role='parent' AND u.status='active'
    HAVING days_since >= 15
    AND NOT EXISTS (
        SELECT 1 FROM quality_assessments qa
        WHERE qa.user_id = u.id
        AND qa.assessment_number = required_assessment
    )
    ORDER BY days_since DESC
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['due_type'] = ($row['required_assessment'] == 2) ? '28 Day' : '15 Day';
    if ($row['days_since'] > 45) {
        $row['status'] = 'Escalation';
    } elseif ($row['days_since'] > 28) {
        $row['status'] = 'Overdue';
    } else {
        $row['status'] = 'Due';
    }
    $students_due[] = $row;
}
$stmt->close();


/* -----------------------------
   RECENT ASSESSMENTS
------------------------------*/
$recent = [];
$stmt = $db->prepare("
    SELECT child_name, assessment_date, progress_status, assessor_name
    FROM quality_assessments
    ORDER BY id DESC
    LIMIT 20
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent[] = $row;
}
$stmt->close();
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Quality Dashboard</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Quality Dashboard</h2>

<!-- CARDS -->
<div class="row mb-4">

<div class="col-md-3">
<div class="card shadow text-center p-3">
<h6>Due Today</h6>
<h3><?= $due_today ?></h3>
</div>
</div>

<div class="col-md-3">
<div class="card shadow text-center p-3">
<h6>Overdue</h6>
<h3><?= $overdue ?></h3>
</div>
</div>

<div class="col-md-3">
<div class="card shadow text-center p-3">
<h6>Completed Today</h6>
<h3><?= $completed_today ?></h3>
</div>
</div>

<div class="col-md-3">
<div class="card shadow text-center p-3">
<h6>Needs Improvement</h6>
<h3><?= $needs_improvement ?></h3>
</div>
</div>

</div>


<!-- STUDENTS DUE TABLE -->
<div class="card shadow mb-4">
<div class="card-body">

<h5>Students Due for Assessment</h5>

<table id="dueTable" class="table table-bordered">

<thead>
<tr>
<th>Name</th>
<th>Mobile</th>
<th>Start Date</th>
<th>Days Since</th>
<th>Due</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($students_due as $s): 
    $rowClass = '';
    if ($s['status'] == 'Escalation') {
        $rowClass = 'table-dark';
    } elseif ($s['status'] == 'Overdue') {
        $rowClass = 'table-danger';
    }
?>
<tr class="<?= $rowClass ?>">

<td><?= htmlspecialchars($s['full_name']) ?></td>
<td><a href="tel:<?= $s['phone'] ?>"><?= $s['phone'] ?></a></td>
<td><?= $s['created_at'] ?></td>
<td><?= $s['days_since'] ?></td>

<td>
<span class="badge badge-info"><?= $s['due_type'] ?></span>
</td>

<td>
<?php if($s['status']=='Escalation'): ?>
<span class="badge badge-dark">Escalation</span>
<?php elseif($s['status']=='Overdue'): ?>
<span class="badge badge-danger">Overdue</span>
<?php else: ?>
<span class="badge badge-warning">Due</span>
<?php endif; ?>
</td>

<td>
<a href="add-assessment.php?user_id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">
Start
</a>
</td>

</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>
</div>


<!-- RECENT ASSESSMENTS -->
<div class="card shadow">
<div class="card-body">

<h5>Recent Assessments</h5>

<table id="recentTable" class="table table-bordered">

<thead>
<tr>
<th>Child</th>
<th>Date</th>
<th>Status</th>
<th>Assessor</th>
</tr>
</thead>

<tbody>

<?php foreach($recent as $r): ?>
<tr>
<td><?= htmlspecialchars($r['child_name']) ?></td>
<td><?= $r['assessment_date'] ?></td>
<td>
<?php if($r['progress_status']=='needs_improvement'): ?>
<span class="badge badge-danger">Needs Improvement</span>
<?php else: ?>
<span class="badge badge-success">Satisfactory</span>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($r['assessor_name']) ?></td>
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

$('#dueTable').DataTable({
    dom: 'Bfrtip',
    buttons: ['excel','csv','print']
});

$('#recentTable').DataTable({
    pageLength: 10
});

});
</script>

</body>
</html>