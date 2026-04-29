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
   FETCH PDF MODE
------------------------------*/
$stmt = $db->prepare("SELECT value FROM website_settings WHERE `key`='assessment_pdf_mode' LIMIT 1");
$stmt->execute();
$modeRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pdf_mode = $modeRow['value'] ?? 'manual';

/* -----------------------------
   HANDLE PDF MODE UPDATE
------------------------------*/
if (isset($_POST['update_pdf_mode'])) {

    $new_mode = $_POST['pdf_mode'] ?? 'manual';

    $stmt = $db->prepare("
        UPDATE website_settings
        SET value = ?
        WHERE `key` = 'assessment_pdf_mode'
    ");
    $stmt->bind_param("s", $new_mode);
    $stmt->execute();
    $stmt->close();

    header("Location: assessments.php");
    exit();
}

/* -----------------------------
   HANDLE PDF DOWNLOAD (ADMIN)
------------------------------*/
if (isset($_GET['download'])) {

    $id = (int)$_GET['download'];

    $stmt = $db->prepare("
        SELECT fa.*, u.full_name AS incharge_name
        FROM first_assessments fa
        LEFT JOIN users u ON fa.assessed_by = u.id
        WHERE fa.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("Invalid assessment");
    }

    require_once '../vendor/autoload.php';

    $dompdf = new Dompdf\Dompdf();

    ob_start();
    ?>

    <h2 style="text-align:center;">Reading Assessment Report</h2>

    <p><strong>Name:</strong> <?= htmlspecialchars($row['child_name']) ?></p>
    <p><strong>Class:</strong> <?= htmlspecialchars($row['class']) ?></p>
    <p><strong>Mobile:</strong> <?= htmlspecialchars($row['mobile']) ?></p>
    <p><strong>Coordinator:</strong> <?= htmlspecialchars($row['incharge_name']) ?></p>
    <p><strong>Language:</strong> <?= htmlspecialchars($row['subject']) ?></p>

    <p><strong>Current Level:</strong><br><?= nl2br(htmlspecialchars($row['assessment'])) ?></p>

    <p><strong>Course Plan:</strong><br><?= nl2br(htmlspecialchars($row['course_plan'])) ?></p>

    <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
    <p><strong>Date:</strong> <?= htmlspecialchars($row['assessment_date']) ?></p>

    <?php

    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();
    $dompdf->stream("assessment_".$id.".pdf", ["Attachment" => true]);

    exit;
}

/* -----------------------------
   HANDLE BULK APPROVE
------------------------------*/
if (isset($_POST['bulk_approve']) && !empty($_POST['ids'])) {

    $ids = $_POST['ids'];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmt = $db->prepare("
        UPDATE first_assessments
        SET pdf_status = 'approved'
        WHERE id IN ($placeholders)
    ");

    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $stmt->close();

    header("Location: assessments.php");
    exit();
}

/* -----------------------------
   FETCH ASSESSMENTS
------------------------------*/
$stmt = $db->prepare("
    SELECT fa.*, u.full_name AS incharge_name, u.location AS center
    FROM first_assessments fa
    LEFT JOIN users u ON fa.assessed_by = u.id
    ORDER BY fa.id DESC
");
$stmt->execute();
$assessments = $stmt->get_result();
$stmt->close();

/* -----------------------------
   HANDLE APPROVE
------------------------------*/
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];

    $stmt = $db->prepare("
        UPDATE first_assessments
        SET pdf_status = 'approved'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: assessments.php");
    exit();
}
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Assessments</title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Assessments</h2>

<div class="card shadow mb-3">
<div class="card-body">

<form method="POST" class="form-inline">

<label class="mr-2"><strong>PDF Mode:</strong></label>

<select name="pdf_mode" class="form-control mr-2">
    <option value="auto" <?= $pdf_mode=='auto'?'selected':'' ?>>Auto Approve</option>
    <option value="manual" <?= $pdf_mode=='manual'?'selected':'' ?>>Manual Approval</option>
    <option value="disabled" <?= $pdf_mode=='disabled'?'selected':'' ?>>Disabled</option>
</select>

<button type="submit" name="update_pdf_mode" class="btn btn-primary">
Update
</button>

</form>

</div>
</div>

<div class="card shadow">
<div class="card-body">

<form method="POST">
<table id="assessmentTable" class="table table-bordered table-striped">

<thead>
<tr>
<th><input type="checkbox" id="selectAll"></th>
<th>Name</th>
<th>Mobile</th>
<th>Subject</th>
<th>Assessment</th>
<th>Course Plan</th>
<th>Status</th>
<th>PDF</th>
<th>Incharge</th>
<th>Center</th>
<th>Date</th>
<th>Action</th>
</tr>
</thead>

<tbody>
<?php while($r = $assessments->fetch_assoc()): ?>
<tr>

<td>
<input type="checkbox" name="ids[]" value="<?= $r['id'] ?>">
</td>

<td><?= htmlspecialchars($r['child_name']) ?></td>

<td>
<a href="tel:<?= htmlspecialchars($r['mobile']) ?>">
<?= htmlspecialchars($r['mobile']) ?>
</a>
</td>

<td><?= htmlspecialchars($r['subject']) ?></td>

<td><?= htmlspecialchars($r['assessment']) ?></td>

<td><?= htmlspecialchars($r['course_plan']) ?></td>

<td><?= htmlspecialchars($r['admission_status']) ?></td>

<td>
<?php if ($pdf_mode === 'auto' || $r['pdf_status'] === 'approved'): ?>
<span class="badge badge-success">Approved</span>
<?php else: ?>
<span class="badge badge-warning">Pending</span>
<?php endif; ?>
</td>

<td><?= htmlspecialchars($r['incharge_name']) ?></td>
<td><?= htmlspecialchars($r['center']) ?></td>
<td><?= htmlspecialchars($r['assessment_date']) ?></td>

<td>

<?php if ($r['pdf_status'] !== 'approved'): ?>
<a href="?approve=<?= $r['id'] ?>" class="btn btn-sm btn-success">
Approve
</a>
<?php endif; ?>

<a href="?download=<?= $r['id'] ?>"
   class="btn btn-sm btn-primary">
Download
</a>

</td>

</tr>
<?php endwhile; ?>
</tbody>

</table>
<br>
<button type="submit" name="bulk_approve" class="btn btn-success">
Bulk Approve Selected
</button>
</form>

</div>
</div>

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
$(function() {
    $('#assessmentTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['excel', 'csv', 'pdf', 'print'],
        pageLength: 10,
        order: [[9, 'desc']]
    });
});

$('#selectAll').on('click', function(){
    $('input[name="ids[]"]').prop('checked', this.checked);
});
</script>

</body>
</html>