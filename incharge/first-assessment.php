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

        $successMsg = "Assessment recorded successfully.";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<?php include 'includes/header.php'; ?>
</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<title>First Assessment</title>
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

<div class="card shadow">
<div class="card-body">

<form method="POST">

<h5 class="mb-3">Child Details</h5>

<div class="form-group">
<label>Child Name *</label>
<input type="text" name="child_name" class="form-control" required>
</div>

<div class="form-group">
<label>Class</label>
<input type="text" name="class" class="form-control">
</div>

<div class="form-group">
<label>Mobile *</label>
<input type="text" name="mobile" class="form-control" required>
</div>

<div class="form-group">
<label>School Name</label>
<input type="text" name="school_name" class="form-control">
</div>

<div class="form-group">
<label>Subject</label>
<select name="subject_main" class="form-control" onchange="toggleSubjectOther(this)">
<option value="" disabled selected>Select Subject</option>
<option value="English">English</option>
<option value="Other">Other Language</option>
</select>

<input type="text" name="subject_other" id="subject_other" class="form-control mt-2" placeholder="Enter subject" style="display:none;">
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
<td><?= htmlspecialchars($r['admission_status']) ?></td>
<td><?= htmlspecialchars($r['lead_source']) ?></td>
<td><?= htmlspecialchars($r['detailed_notes']) ?></td>
<td><?= htmlspecialchars($r['assessment_date']) ?></td>
</tr>
<?php endwhile; $stmt->close(); ?>
</tbody>
</table>

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
</script>

<script>
$(document).ready(function() {


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