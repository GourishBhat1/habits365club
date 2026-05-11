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

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $child_name = trim($_POST['child_name'] ?? '');
    $child_age = trim($_POST['child_age'] ?? '');
    $standard = trim($_POST['standard'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $course_interest = trim($_POST['course_interest'] ?? '');
    $lead_source = trim($_POST['lead_source'] ?? 'manual');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($full_name) || empty($phone)) {
        $error = "Parent name and phone are required.";
    } else {
        $stmt = $db->prepare("
            INSERT INTO leads (full_name, phone, email, child_name, child_age, standard, school_name, location, course_interest, lead_source, assigned_to, notes, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())
        ");
        $stmt->bind_param("ssssssssssis", $full_name, $phone, $email, $child_name, $child_age, $standard, $school_name, $location, $course_interest, $lead_source, $sales_id, $notes);

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
<label>Email</label>
<input type="email" name="email" class="form-control">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Location / Center</label>
<select name="location" class="form-control">
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
</select>
</div>
</div>
</div>

<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Child Name</label>
<input type="text" name="child_name" class="form-control">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Child Age / Standard</label>
<input type="text" name="standard" class="form-control" placeholder="e.g. Class 3">
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
<select name="lead_source" class="form-control">
<option value="manual">Manual</option>
<option value="website">Website</option>
<option value="instagram">Instagram</option>
<option value="referral">Referral</option>
<option value="walk_in">Walk-in</option>
<option value="call">Phone Call</option>
<option value="other">Other</option>
</select>
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
</body>
</html>
