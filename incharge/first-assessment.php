<?php
session_start();
require_once '../connection.php';

if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/* ---------------------------------
   FETCH INCHARGE DETAILS
----------------------------------*/
$inchargeUsername = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];

$stmt = $db->prepare("
    SELECT id, full_name, location
    FROM users
    WHERE username = ?
      AND role = 'incharge'
");
$stmt->bind_param("s", $inchargeUsername);
$stmt->execute();
$incharge = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$incharge) {
    die("Invalid incharge");
}

$incharge_id = (int)$incharge['id'];
$location = $incharge['location'] ?? '';
$successMsg = $errorMsg = null;

/* ---------------------------------
   FETCH PENDING BOOKINGS FOR INCHARGE'S CENTER
----------------------------------*/
$pendingBookings = [];
$stmt = $db->prepare("
    SELECT ab.*, u.full_name AS booked_by_name
    FROM assessment_bookings ab
    LEFT JOIN users u ON ab.booked_by = u.id
    WHERE ab.location = ? AND ab.status = 'pending'
    ORDER BY ab.created_at DESC
");
$stmt->bind_param("s", $location);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pendingBookings[] = $row;
}
$stmt->close();

/* ---------------------------------
   PREFILL FROM BOOKING
----------------------------------*/
$prefill_booking = null;
$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id) {
    $stmt = $db->prepare("
        SELECT ab.*, u.full_name AS booked_by_name
        FROM assessment_bookings ab
        LEFT JOIN users u ON ab.booked_by = u.id
        WHERE ab.id = ? AND ab.location = ? AND ab.status = 'pending'
    ");
    $stmt->bind_param("is", $booking_id, $location);
    $stmt->execute();
    $prefill_booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ---------------------------------
   AJAX: UPDATE ADMISSION STATUS
----------------------------------*/
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {

    header('Content-Type: application/json');

    $id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    // error_log("DEBUG: Update Status | ID: $id | Status: $status | Incharge: $incharge_id");

    if (!$id || !$status) {
        echo json_encode(['success' => false, 'msg' => 'Invalid input']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE first_assessments 
        SET admission_status=? 
        WHERE id=? AND assessed_by=?
    ");

    if (!$stmt) {
        // error_log("DEBUG: Prepare failed - " . $db->error);
        echo json_encode(['success' => false, 'msg' => 'Prepare failed']);
        exit;
    }

    $stmt->bind_param("sii", $status, $id, $incharge_id);

    if ($stmt->execute()) {
        // error_log("DEBUG: Update SUCCESS for ID: $id");
        echo json_encode(['success' => true]);
    } else {
        // error_log("DEBUG: Update FAILED - " . $stmt->error);
        echo json_encode(['success' => false, 'msg' => 'Update failed']);
    }

    $stmt->close();
    exit;
}

/* ---------------------------------
   FETCH PDF MODE
----------------------------------*/
$stmt = $db->prepare("SELECT value FROM website_settings WHERE `key`='assessment_pdf_mode' LIMIT 1");
$stmt->execute();
$modeRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$mode = $modeRow['value'] ?? 'manual';

/* ---------------------------------
   HANDLE PDF DOWNLOAD
----------------------------------*/
if (isset($_GET['download'])) {

    $id = (int)$_GET['download'];

    $stmt = $db->prepare("SELECT * FROM first_assessments WHERE id=? AND assessed_by=?");
    $stmt->bind_param("ii", $id, $incharge_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("Invalid access");
    }

    // Permission check
    $allowed = false;

    if ($mode === 'auto') {
        $allowed = true;
    } elseif ($mode === 'manual' && $row['pdf_status'] === 'approved') {
        $allowed = true;
    }

    if (!$allowed || $mode === 'disabled') {
        die("Download not allowed");
    }

    require_once '../vendor/autoload.php';

    $dompdf = new Dompdf\Dompdf();

    ob_start();
    ?>

    <h2 style="text-align:center;">Reading Assessment Report</h2>

    <p><strong>Name:</strong> <?= htmlspecialchars($row['child_name']) ?></p>
    <p><strong>Class:</strong> <?= htmlspecialchars($row['class']) ?></p>
    <p><strong>Mobile:</strong> <?= htmlspecialchars($row['mobile']) ?></p>
    <p><strong>Coordinator:</strong> <?= htmlspecialchars($incharge['full_name']) ?></p>
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

/* ---------------------------------
   HANDLE FORM SUBMIT
----------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $child_name = trim($_POST['child_name']);
    $class = trim($_POST['class']);
    $mobile = trim($_POST['mobile']);
    $school_name = trim($_POST['school_name']);
    $subject = ($_POST['subject_main'] === 'Other')
        ? trim($_POST['subject_other'])
        : trim($_POST['subject_main']);
    $assessment = trim($_POST['assessment']);
    $course_plan = trim($_POST['course_plan']);
    $admission_status = trim($_POST['admission_status']);
    $lead_source = trim($_POST['lead_source']);
    $detailed_notes = trim($_POST['detailed_notes']);
    $assessment_date = $_POST['assessment_date'];

    if (empty($child_name) || empty($mobile) || empty($assessment_date)) {
        $errorMsg = "Child name, mobile and assessment date are required.";
    }

    if (!$errorMsg) {

        $stmt = $db->prepare("
            INSERT INTO first_assessments (
                child_name, class, mobile, school_name, subject,
                assessment, course_plan,
                admission_status, lead_source, detailed_notes,
                assessed_by, location, assessment_date
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "ssssssssssiss",
            $child_name,
            $class,
            $mobile,
            $school_name,
            $subject,
            $assessment,
            $course_plan,
            $admission_status,
            $lead_source,
            $detailed_notes,
            $incharge_id,
            $location,
            $assessment_date
        );

        $stmt->execute();
        $stmt->close();

        // Mark source booking as completed
        $prefilled_from = (int)($_POST['prefilled_from'] ?? 0);
        if ($prefilled_from) {
            $ustmt = $db->prepare("UPDATE assessment_bookings SET status = 'completed', updated_at = NOW() WHERE id = ? AND location = ?");
            $ustmt->bind_param("is", $prefilled_from, $location);
            $ustmt->execute();
            $ustmt->close();
        }

        $successMsg = "Assessment recorded successfully.";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<?php include 'includes/header.php'; ?>
<title>First Assessment</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
</head>

<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main role="main" class="main-content">
<div class="container-fluid">

<h2 class="page-title">First Assessment</h2>

<?php if ($successMsg): ?>
<div class="alert alert-success"><?php echo $successMsg; ?></div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger"><?php echo $errorMsg; ?></div>
<?php endif; ?>

<?php if ($prefill_booking): ?>
<div class="alert alert-info">
    <strong>Taking Assessment for:</strong>
    <?= htmlspecialchars($prefill_booking['child_name']) ?>
    — Booked by <?= htmlspecialchars($prefill_booking['booked_by_name'] ?? 'Sales') ?>
    <?php if (!empty($prefill_booking['time_slot'])): ?>
        &middot; Slot: <?= htmlspecialchars($prefill_booking['time_slot']) ?>
    <?php endif; ?>
    <?php if (!empty($prefill_booking['payment_status'])): ?>
        &middot; Payment: <?= htmlspecialchars($prefill_booking['payment_status']) ?>
        <?php if ($prefill_booking['payment_status'] === 'Paid' && !empty($prefill_booking['transaction_id'])): ?>
            (Ref: <?= htmlspecialchars($prefill_booking['transaction_id']) ?>)
        <?php endif; ?>
    <?php endif; ?>
    <a href="first-assessment.php" class="float-right">Cancel &amp; view all bookings</a>
</div>
<?php elseif (!empty($pendingBookings)): ?>
<div class="card shadow mb-4">
<div class="card-body">
<h5 class="mb-3">Pending Bookings from Sales</h5>
<div class="table-responsive">
<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>Child Name</th>
    <th>Mobile</th>
    <th>Class</th>
    <th>Subject</th>
    <th>Time Slot</th>
    <th>Payment</th>
    <th>Booked By</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($pendingBookings as $pb): ?>
<tr>
    <td><?= htmlspecialchars($pb['child_name']) ?></td>
    <td><a href="tel:<?= htmlspecialchars($pb['mobile']) ?>"><?= htmlspecialchars($pb['mobile']) ?></a></td>
    <td><?= htmlspecialchars($pb['class']) ?></td>
    <td><?= htmlspecialchars($pb['subject']) ?></td>
    <td><?= htmlspecialchars($pb['time_slot']) ?></td>
    <td>
        <?= htmlspecialchars($pb['payment_status']) ?>
        <?php if ($pb['payment_status'] === 'Paid' && !empty($pb['transaction_id'])): ?>
            <br><small>Ref: <?= htmlspecialchars($pb['transaction_id']) ?></small>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($pb['booked_by_name']) ?></td>
    <td>
        <a href="first-assessment.php?booking_id=<?= $pb['id'] ?>" class="btn btn-sm btn-success">Take Assessment</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php endif; ?>

<div class="card shadow">
<div class="card-body">

<form method="POST">

<?php if ($prefill_booking): ?>
<input type="hidden" name="prefilled_from" value="<?= $prefill_booking['id'] ?>">
<?php endif; ?>

<h5 class="mb-3">Child Details</h5>

<div class="form-group">
<label>Child Name *</label>
<input type="text" name="child_name" class="form-control" value="<?= $prefill_booking ? htmlspecialchars($prefill_booking['child_name']) : '' ?>" required>
</div>

<div class="form-group">
<label>Class</label>
<input type="text" name="class" class="form-control" value="<?= $prefill_booking ? htmlspecialchars($prefill_booking['class']) : '' ?>">
</div>

<div class="form-group">
<label>Mobile *</label>
<input type="text" name="mobile" class="form-control" value="<?= $prefill_booking ? htmlspecialchars($prefill_booking['mobile']) : '' ?>" required>
</div>

<div class="form-group">
<label>School Name</label>
<input type="text" name="school_name" class="form-control" value="<?= $prefill_booking ? htmlspecialchars($prefill_booking['school_name']) : '' ?>">
</div>

<div class="form-group">
<label>Subject</label>
<select name="subject_main" class="form-control" onchange="toggleSubjectOther(this)">
<option value="" <?= !$prefill_booking || $prefill_booking['subject'] === '' ? 'selected' : '' ?> disabled>Select Subject</option>
<option value="English" <?= $prefill_booking && $prefill_booking['subject'] === 'English' ? 'selected' : '' ?>>English</option>
<option value="Other" <?= $prefill_booking && $prefill_booking['subject'] !== 'English' && $prefill_booking['subject'] !== '' ? 'selected' : '' ?>>Other Language</option>
</select>

<input type="text" name="subject_other" id="subject_other" class="form-control mt-2" placeholder="Enter subject" value="<?= $prefill_booking && $prefill_booking['subject'] !== 'English' ? htmlspecialchars($prefill_booking['subject']) : '' ?>" style="<?= $prefill_booking && $prefill_booking['subject'] !== 'English' && $prefill_booking['subject'] !== '' ? 'display:block;' : 'display:none;' ?>">
</div>

<div class="form-group">
<label>Assessment Date *</label>
<input type="date" name="assessment_date"
value="<?php echo date('Y-m-d'); ?>"
class="form-control" required>
</div>

<hr>

<h5 class="mb-3">Assessment</h5>

<div class="form-group">
<label>Assessment</label>
<select name="assessment" id="assessment" class="form-control" onchange="setCoursePlan()">
<option value="">Select</option>
</select>
</div>

<div class="form-group">
<label>Course Plan</label>
<select name="course_plan" id="course_plan" class="form-control">
<option value="">Select Course Plan</option>
</select>
</div>

<hr>

<h5 class="mb-3">Admission Tracking</h5>

<div class="form-group">
<label>Admission Status</label>
<select name="admission_status" class="form-control">
<option value="">Select</option>
<option>Admitted</option>
<option>Follow Up</option>
<option>Not Interested</option>
</select>
</div>

<div class="form-group">
<label>Lead Source</label>
<select name="lead_source" class="form-control">
<option value="">Select</option>
<option>Walk In</option>
<option>Referral</option>
<option>Instagram</option>
<option>Google</option>
<option>Sales</option>
<?php
// Add sales users dynamically
$salesUsers = [];
$salesStmt = $db->prepare("SELECT full_name FROM users WHERE role='sales'");
$salesStmt->execute();
$salesRes = $salesStmt->get_result();
while($sales = $salesRes->fetch_assoc()) {
    echo '<option>'.htmlspecialchars($sales['full_name']).'</option>';
}
$salesStmt->close();
?>
</select>
</div>

<div class="form-group">
<label>Detailed Notes</label>
<textarea name="detailed_notes" class="form-control" rows="5"></textarea>
</div>

<button class="btn btn-primary">
Save Assessment
</button>

</form>

</div>
</div>

<hr>
<h4>My Assessments</h4>

<div class="row mb-3">
    <div class="col-md-2">
        <label>From Date</label>
        <input type="date" id="filterFromDate" class="form-control">
    </div>
    <div class="col-md-2">
        <label>To Date</label>
        <input type="date" id="filterToDate" class="form-control">
    </div>
    <div class="col-md-2">
        <label>Subject</label>
        <select id="filterSubject" class="form-control">
            <option value="">All</option>
            <option value="English">English</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <div class="col-md-3">
        <label>Admission Status</label>
        <select id="filterStatus" class="form-control">
            <option value="">All</option>
            <option>Admitted</option>
            <option>Follow Up</option>
            <option>Not Interested</option>
        </select>
    </div>
</div>

<table id="assessmentTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Name</th>
<th>Mobile</th>
<th>Subject</th>
<th>Assessment</th>
<th>Course</th>
<th>Admission Status</th>
<th>Lead Source</th>
<th>Notes</th>
<th>Date</th>
<th>Report</th>
</tr>
</thead>
<tbody>
<?php
$stmt = $db->prepare("SELECT * FROM first_assessments WHERE assessed_by=? ORDER BY id DESC");
$stmt->bind_param("i",$incharge_id);
$stmt->execute();
$res = $stmt->get_result();
while($r=$res->fetch_assoc()):
?>
<tr>
<td><?= htmlspecialchars($r['child_name']) ?></td>
<td>
    <a href="tel:<?= htmlspecialchars($r['mobile']) ?>">
        <?= htmlspecialchars($r['mobile']) ?>
    </a>
</td>
<td><?= htmlspecialchars($r['subject']) ?></td>
<td><?= htmlspecialchars($r['assessment']) ?></td>
<td><?= htmlspecialchars($r['course_plan']) ?></td>
<td>
<select class="form-control form-control-sm status-dropdown" data-id="<?= $r['id'] ?>">
    <option value="">Select</option>
    <option <?= $r['admission_status']=='Admitted'?'selected':'' ?>>Admitted</option>
    <option <?= $r['admission_status']=='Follow Up'?'selected':'' ?>>Follow Up</option>
    <option <?= $r['admission_status']=='Not Interested'?'selected':'' ?>>Not Interested</option>
</select>
</td>
<td><?= htmlspecialchars($r['lead_source']) ?></td>
<td><?= htmlspecialchars($r['detailed_notes']) ?></td>
<td><?= htmlspecialchars($r['assessment_date']) ?></td>
<td>
<?php
$canDownload = false;

if ($mode === 'auto') {
    $canDownload = true;
} elseif ($mode === 'manual' && $r['pdf_status'] === 'approved') {
    $canDownload = true;
}

if ($mode === 'disabled') {
    echo '<span class="text-muted">Disabled</span>';
} elseif ($canDownload) {
?>
    <a href="?download=<?= $r['id'] ?>" class="btn btn-sm btn-primary">
        Download
    </a>
<?php
} else {
    echo '<span class="text-warning">Pending</span>';
}
?>
</td>
</tr>
<?php endwhile; $stmt->close(); ?>
</tbody>
</table>

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
function toggleSubjectOther(sel){
    const input = document.getElementById('subject_other');
    if(sel.value === 'Other'){
        input.style.display='block';
    } else {
        input.style.display='none';
        input.value='';
    }
    populateAssessment(sel.value);
}

const assessmentMap = {
    "English": {
        "Can write lowercase letters. Does not know phonics sounds. Can read two- and three-letter words but not phonetically.": "The Habits 365 course will begin with systematic teaching of phonics sounds, focusing on sound recognition and sound–letter association.",
        "Can write lowercase letters. Knows phonics sounds but requires revision. Can read three-letter words and a few blending/digraph words.": "The Habits 365 course will begin with phonics revision, followed by structured blending practice and word formation.",
        "Can write lowercase letters. Does not have a clear understanding of phonics concepts.": "The Habits 365 course will begin with foundational sound teaching, followed by introduction to phonics concepts and blending.",
        "Can write lowercase letters. Can read short three-letter-word stories and some longer words, but not phonetically.": "The Habits 365 course will begin with revision of sounds, followed by structured phonics techniques to improve decoding skills.",
        "Can write lowercase letters but shows minor confusion in letter formation or recognition.": "The Habits 365 course will begin with letter formation practice, clarity in sound–symbol connection, and gradual phonics reinforcement.",
        "Can write lowercase letters. Knows most phonics sounds except a few (e.g., q, u). Unable to read two- and three-letter words.": "The Habits 365 course will begin with a quick revision of phonics sounds, followed by structured teaching of two- and three-letter words."
    },
    "Other": {
        "Can identify swar and vyanjan and can read simple words": "Habits 365 course will begin from teaching of matras",
        "Can identify some vyanjan and swar but cannot read words": "Habits 365 course will begin from revision of vyanjan identification followed by swar",
        "Cannot identify swar and vyanjan": "Habits 365 course will begin from vyanjan identification followed by swar",
        "Can identify swar but has difficulty in vyanjan identification": "Habits 365 course will begin from vyanjan identification followed by swar",
        "Can identify swar and vyanjan and can read few matra words but cannot read jodhakshars": "Habits 365 course will begin from revision of matras followed by jodhakshars"
    }
};

function populateAssessment(subject){
    const assessSelect = document.getElementById('assessment');
    const courseSelect = document.getElementById('course_plan');

    assessSelect.innerHTML = '<option value="">Select</option>';
    courseSelect.innerHTML = '<option value="">Select Course Plan</option>';

    if(!assessmentMap[subject]) return;

    Object.entries(assessmentMap[subject]).forEach(([assessment, course])=>{
        let opt1 = document.createElement('option');
        opt1.value = assessment;
        opt1.text = assessment;
        assessSelect.appendChild(opt1);

        let opt2 = document.createElement('option');
        opt2.value = course;
        opt2.text = course;
        courseSelect.appendChild(opt2);
    });
}

function setCoursePlan(){
    const subject = document.querySelector('[name="subject_main"]').value;
    const assessment = document.getElementById('assessment').value;
    const course = assessmentMap[subject]?.[assessment] || '';

    const courseSelect = document.getElementById('course_plan');
    Array.from(courseSelect.options).forEach(opt=>{
        if(opt.value === course){
            opt.selected = true;
        }
    });
}
// Admission Status AJAX handler
$(document).on('change', '.status-dropdown', function() {
    var id = $(this).data('id');
    var status = $(this).val();

    // console.log("✅ Status change triggered");
    // console.log("👉 ID:", id);
    // console.log("👉 New Status:", status);

    $.post('', {
        action: 'update_status',
        id: id,
        status: status
    }, function(res) {
        // console.log("📦 Server response:", res);
    }).fail(function(err){
        // console.error("❌ AJAX error:", err);
    });
});
</script>

<script>
$(document).ready(function() {

    <?php if ($prefill_booking && !empty($prefill_booking['subject'])): ?>
    // Auto-trigger subject selection for prefill
    var subjectSel = document.querySelector('[name="subject_main"]');
    if (subjectSel) {
        subjectSel.dispatchEvent(new Event('change'));
    }
    <?php endif; ?>

    var table = $('#assessmentTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['excel', 'csv', 'pdf', 'print'],
        pageLength: 10,
        order: [[8, 'desc']]
    });

    $('#filterSubject').on('change', function() {
        table.column(2).search(this.value).draw();
    });

    // Admission status filter
    $('#filterStatus').on('change', function() {
        table.column(5).search(this.value).draw();
    });


    // Date range filter
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var min = $('#filterFromDate').val();
        var max = $('#filterToDate').val();
        var date = data[8]; // Date column

        if (!date) return true;

        var rowDate = new Date(date);

        if (min) {
            var minDate = new Date(min);
            if (rowDate < minDate) return false;
        }

        if (max) {
            var maxDate = new Date(max);
            if (rowDate > maxDate) return false;
        }

        return true;
    });

    $('#filterFromDate, #filterToDate').on('change', function() {
        table.draw();
    });

});
</script>

</body>
</html>