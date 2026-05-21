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
    ) t
    WHERE t.days_since IN (15,28)
    AND NOT EXISTS (
        SELECT 1 FROM quality_assessments qa
        WHERE qa.user_id = t.id
        AND qa.assessment_number = t.required_assessment
    )
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
        WHERE u.role='parent' AND u.status='active'
    ) t
    WHERE t.days_since > 28
    AND NOT EXISTS (
        SELECT 1 FROM quality_assessments qa
        WHERE qa.user_id = t.id
        AND qa.assessment_number = t.required_assessment
    )
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
    SELECT * FROM (
        SELECT 
            u.id,
            u.full_name,
            u.phone,
            u.created_at,
            u.location,
            DATEDIFF(CURDATE(), u.created_at) as days_since,
            CASE 
                WHEN DATEDIFF(CURDATE(), u.created_at) >= 28 THEN 2
                ELSE 1
            END as required_assessment
        FROM users u
        WHERE u.role='parent' AND u.status='active'
    ) t
    WHERE t.days_since >= 15
    AND NOT EXISTS (
        SELECT 1 FROM quality_assessments qa
        WHERE qa.user_id = t.id
        AND qa.assessment_number = t.required_assessment
    )
    ORDER BY t.days_since DESC
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
    SELECT qa.child_name, qa.mobile, qa.assessment_date, qa.assessment_number,
           qa.teacher_name, qa.subject,
           qa.progress_status, qa.course_completed, qa.next_followup, qa.assessor_name,
           u.location
    FROM quality_assessments qa
    LEFT JOIN users u ON qa.user_id = u.id
    ORDER BY qa.id DESC
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
<th>Center</th>
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
<td><?= htmlspecialchars($s['location'] ?? '') ?></td>
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

<table id="recentTable" class="table table-bordered table-striped">

<thead>
<tr>
<th>Child</th>
<th>Mobile</th>
<th>Center</th>
<th>Date</th>
<th>Assessment</th>
<th>Teacher</th>
<th>Subject</th>
<th>Progress</th>
<th>Course Status</th>
<th>Follow-up</th>
<th>Assessor</th>
</tr>
</thead>

<tbody>

<?php foreach($recent as $r): ?>
<tr>
<td><?= htmlspecialchars($r['child_name']) ?></td>
<td><?= htmlspecialchars($r['mobile'] ?? '') ?></td>
<td><?= htmlspecialchars($r['location'] ?? '') ?></td>
<td><?= $r['assessment_date'] ?></td>
<td><?= ($r['assessment_number']==2 ? '28 Day' : '15 Day') ?></td>
<td><?= htmlspecialchars($r['teacher_name'] ?? '') ?></td>
<td><?= htmlspecialchars($r['subject'] ?? '') ?></td>
<td>
<?php $ps = $r['progress_status'] ?? ''; ?>
<?php if($ps=='needs_improvement'): ?>
<span class="badge badge-danger">Needs Improvement</span>
<?php elseif($ps=='satisfactory'): ?>
<span class="badge badge-success">Satisfactory</span>
<?php else: ?>
<span class="badge badge-secondary">-</span>
<?php endif; ?>
</td>
<td><?= !empty($r['course_completed']) ? ucfirst($r['course_completed']) : '-' ?></td>
<td><?= !empty($r['next_followup']) ? $r['next_followup'] : '-' ?></td>
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
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
$(function(){

$('#dueTable').DataTable({
    dom: 'Bfrtip',
    buttons: ['excel','csv','print']
});

$('#recentTable').DataTable({
    dom: 'Bfrtip',
    buttons: ['excel','csv','print'],
    pageLength: 10,
    order: [[2, 'desc']]
});

});
</script>

</body>
</html>