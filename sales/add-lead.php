<?php
session_start();
require_once '../connection.php';

if (!isset($_SESSION['sales_username']) && !isset($_COOKIE['sales_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$sales_username = $_SESSION['sales_username'] ?? $_COOKIE['sales_username'] ?? '';
$sales_id = $_SESSION['sales_id'] ?? 0;

if ($sales_id === 0 && !empty($sales_username)) {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'sales'");
    $stmt->bind_param("s", $sales_username);
    $stmt->execute();
    $stmt->bind_result($sales_id);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['sales_id'] = $sales_id;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $child_name = trim($_POST['child_name'] ?? '');
    $child_age = trim($_POST['child_age'] ?? '');
    $standard = trim($_POST['standard'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $location_other = trim($_POST['location_other'] ?? '');
    $course_interest = trim($_POST['course_interest'] ?? '');
    $lead_source = trim($_POST['lead_source'] ?? 'website');
    $lead_source_other = trim($_POST['lead_source_other'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($full_name) || empty($phone)) {
        $error = "Parent name and phone are required.";
    } elseif (empty($sales_id)) {
        $error = "Sales user not found. Please log in again.";
    } else {
        if ($location === 'Other' && !empty($location_other)) {
            $location = $location_other;
        }
        if ($lead_source === 'other' && !empty($lead_source_other)) {
            $lead_source = $lead_source_other;
        }

        $stmt = $db->prepare("
            INSERT INTO leads (full_name, phone, child_name, child_age, standard, school_name, location, course_interest, lead_source, assigned_to, notes, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())
        ");
        $stmt->bind_param("sssssssssis", $full_name, $phone, $child_name, $child_age, $standard, $school_name, $location, $course_interest, $lead_source, $sales_id, $notes);

        if ($stmt->execute()) {
            $success = "Lead added successfully!";
        } else {
            $error = "Error adding lead: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Add Lead - Sales</title>
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Add Lead</h2>

<div class="card shadow p-4">

<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Parent Full Name *</label>
<input type="text" name="full_name" class="form-control" required>
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Phone *</label>
<input type="tel" name="phone" class="form-control" required>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Location / Center</label>
<select name="location" class="form-control" onchange="toggleOther(this, 'location_other_wrapper')">
<option value="">Select</option>
<?php
$cstmt = $db->prepare("SELECT location FROM centers WHERE status = 'enabled' ORDER BY location ASC");
$cstmt->execute();
$cresult = $cstmt->get_result();
while ($crow = $cresult->fetch_assoc()) {
    echo '<option value="' . htmlspecialchars($crow['location']) . '">' . htmlspecialchars($crow['location']) . '</option>';
}
$cstmt->close();
?>
<option value="Other">Other</option>
</select>
<div id="location_other_wrapper" style="display:none; margin-top:8px;">
<input type="text" name="location_other" class="form-control" placeholder="Enter location / center name">
</div>
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Child Name</label>
<input type="text" name="child_name" class="form-control">
</div>
</div>
</div>

<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Standard *</label>
<select name="standard" class="form-control" required>
<option value="">Select</option>
<option value="Play Group">Play Group</option>
<option value="Nursery">Nursery</option>
<option value="Jr.KG">Jr.KG</option>
<option value="Sr.KG">Sr.KG</option>
<option value="1st">1st</option>
<option value="2nd">2nd</option>
<option value="3rd">3rd</option>
<option value="4th">4th</option>
<option value="5th">5th</option>
<option value="6th">6th</option>
<option value="7th">7th</option>
<option value="8th">8th</option>
<option value="9th">9th</option>
<option value="10th">10th</option>
</select>
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Child Age</label>
<input type="text" name="child_age" class="form-control" placeholder="e.g. 5 years">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>School Name</label>
<input type="text" name="school_name" class="form-control">
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Course Interested In</label>
<input type="text" name="course_interest" class="form-control">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Lead Source</label>
<select name="lead_source" class="form-control" onchange="toggleOther(this, 'lead_source_other_wrapper')">
<option value="website">Website</option>
<option value="instagram">Instagram</option>
<option value="facebook">Facebook</option>
<option value="referral">Referral</option>
<option value="walk_in">Walk-in</option>
<option value="existing_parent">Existing Parent</option>
<option value="other">Other</option>
</select>
<div id="lead_source_other_wrapper" style="display:none; margin-top:8px;">
<input type="text" name="lead_source_other" class="form-control" placeholder="Enter lead source">
</div>
</div>
</div>
</div>

<div class="form-group">
<label>Notes</label>
<textarea name="notes" class="form-control" rows="3"></textarea>
</div>

<button class="btn btn-primary">Add Lead</button>
<a href="leads.php" class="btn btn-secondary">Cancel</a>

</form>

</div>

</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>

<script>
function toggleOther(select, wrapperId) {
    var wrapper = document.getElementById(wrapperId);
    if (select.value === 'Other' || select.value === 'other') {
        wrapper.style.display = 'block';
    } else {
        wrapper.style.display = 'none';
        wrapper.querySelector('input').value = '';
    }
}
</script>

</body>
</html>
