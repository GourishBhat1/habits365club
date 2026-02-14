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
    $language = trim($_POST['language']);
    $assessment_date = $_POST['assessment_date'];

    $current_level = trim($_POST['current_level']);
    $reading_ability = trim($_POST['reading_ability']);
    $phonics_understanding = trim($_POST['phonics_understanding']);
    $writing_ability = trim($_POST['writing_ability']);
    $comprehension_level = trim($_POST['comprehension_level']);
    $recommended_course = trim($_POST['recommended_course']);

    $admission_status = trim($_POST['admission_status']);
    $lead_source = trim($_POST['lead_source']);
    $detailed_notes = trim($_POST['detailed_notes']);

    if (empty($child_name) || empty($mobile) || empty($assessment_date)) {
        $errorMsg = "Child name, mobile and assessment date are required.";
    }

    if (!$errorMsg) {

        $stmt = $db->prepare("
            INSERT INTO first_assessments (
                child_name, class, mobile, school_name, language,
                current_level, reading_ability, phonics_understanding,
                writing_ability, comprehension_level, recommended_course,
                admission_status, lead_source, detailed_notes,
                assessed_by, location, assessment_date
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "ssssssssssssssiss",
            $child_name,
            $class,
            $mobile,
            $school_name,
            $language,
            $current_level,
            $reading_ability,
            $phonics_understanding,
            $writing_ability,
            $comprehension_level,
            $recommended_course,
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
<label>Language</label>
<select name="language" class="form-control">
<option value="">Select</option>
<option>English</option>
<option>Marathi</option>
<option>Other</option>
</select>
</div>

<div class="form-group">
<label>Assessment Date *</label>
<input type="date" name="assessment_date"
value="<?php echo date('Y-m-d'); ?>"
class="form-control" required>
</div>

<hr>

<h5 class="mb-3">Academic Evaluation</h5>

<?php
function dropdownField($name, $label, $options) {
    echo '<div class="form-group">';
    echo '<label>'.$label.'</label>';
    echo '<select name="'.$name.'" class="form-control" onchange="toggleOther(this, \''.$name.'_other\')">';
    echo '<option value="">Select</option>';
    foreach ($options as $opt) {
        echo '<option>'.$opt.'</option>';
    }
    echo '<option value="Other">Other</option>';
    echo '</select>';
    echo '<input type="text" name="'.$name.'" id="'.$name.'_other" class="form-control mt-2" placeholder="Enter custom value" style="display:none;">';
    echo '</div>';
}

dropdownField("current_level", "Current Level", ["Below Average","Average","Good","Excellent"]);
dropdownField("reading_ability", "Reading Ability", ["Cannot identify alphabets","Identifies alphabets","Reads 2 letter words","Reads 3 letter words","Reads fluently"]);
dropdownField("phonics_understanding", "Phonics Understanding", ["No phonics knowledge","Basic phonics","Understands blends","Understands digraphs"]);
dropdownField("writing_ability", "Writing Ability", ["Cannot write","Writes alphabets","Writes words","Writes sentences"]);
dropdownField("comprehension_level", "Comprehension Level", ["Cannot comprehend","Understands simple sentences","Understands paragraphs"]);
dropdownField("recommended_course", "Recommended Course", ["Phonics Level 1","Phonics Level 2","Advanced Reading","Bridge Course"]);
?>

<hr>

<h5 class="mb-3">Admission Tracking</h5>

<?php
dropdownField("admission_status", "Admission Status", ["Admitted","Follow Up","Not Interested"]);
dropdownField("lead_source", "Lead Source", ["Walk In","Referral","Instagram","Google","Sales"]);
?>

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

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleOther(selectObj, inputId) {
    var input = document.getElementById(inputId);
    if (selectObj.value === "Other") {
        input.style.display = "block";
        selectObj.name = selectObj.name + "_temp";
    } else {
        input.style.display = "none";
        input.value = "";
    }
}
</script>

</body>
</html>