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

$lead_id = (int)($_GET['lead_id'] ?? $_POST['lead_id'] ?? 0);
$lead = null;

if ($lead_id) {
    $stmt = $db->prepare("SELECT * FROM leads WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("ii", $lead_id, $sales_id);
    $stmt->execute();
    $lead = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    $child_name = trim($_POST['child_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $assessment = trim($_POST['assessment'] ?? '');
    $course_plan = trim($_POST['course_plan'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $lead_source = 'sales_' . $sales_id;

    if (empty($child_name) || empty($mobile)) {
        $error = "Child name and mobile are required.";
    } else {
        $stmt = $db->prepare("
            INSERT INTO first_assessments (child_name, class, mobile, school_name, subject, assessment, course_plan, lead_source, detailed_notes, assessed_by, location, assessment_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
        ");
        $stmt->bind_param("sssssssssss", $child_name, $class, $mobile, $school_name, $subject, $assessment, $course_plan, $lead_source, $notes, $sales_username, $location);

        if ($stmt->execute()) {
            $assessment_id = $stmt->insert_id;
            $stmt->close();

            // Update lead status
            if ($lead_id) {
                $ustmt = $db->prepare("UPDATE leads SET status = 'assessment_booked', updated_at = NOW(), notes = CONCAT(IFNULL(notes,''), '\nAssessment booked (ID: $assessment_id) on ', NOW()) WHERE id = ?");
                $ustmt->bind_param("i", $lead_id);
                $ustmt->execute();
                $ustmt->close();
            }

            $success = "Assessment booked successfully!";
        } else {
            $error = "Error booking assessment: " . $stmt->error;
        }
    }
}
?>
<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Book Assessment - Sales</title>
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Book Assessment</h2>

<div class="card shadow p-4">

<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<?php if ($lead): ?>
<input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
<div class="alert alert-info">
    Prefilled from lead: <strong><?= htmlspecialchars($lead['full_name']) ?></strong>
</div>
<?php endif; ?>

<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Child Name *</label>
<input type="text" name="child_name" class="form-control" value="<?= $lead ? htmlspecialchars($lead['child_name'] ?: $lead['full_name']) : '' ?>" required>
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Class / Standard</label>
<input type="text" name="class" class="form-control" value="<?= $lead ? htmlspecialchars($lead['standard']) : '' ?>">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Mobile *</label>
<input type="tel" name="mobile" class="form-control" value="<?= $lead ? htmlspecialchars($lead['phone']) : '' ?>" required>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>School Name</label>
<input type="text" name="school_name" class="form-control" value="<?= $lead ? htmlspecialchars($lead['school_name']) : '' ?>">
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
        $sel = ($lead && strtoupper($lead['location']) === $crow['location']) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($crow['location']) . '" ' . $sel . '>' . htmlspecialchars($crow['location']) . '</option>';
    }
    $cstmt->close();
    ?>
</select>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Subject</label>
<select name="subject" class="form-control">
    <option value="">Select</option>
    <option value="English">English</option>
    <option value="Math">Math</option>
    <option value="Science">Science</option>
    <option value="General">General</option>
</select>
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Course Plan</label>
<select name="course_plan" class="form-control">
    <option value="">Select</option>
    <option value="monthly">Monthly</option>
    <option value="quarterly">Quarterly</option>
    <option value="yearly">Yearly</option>
</select>
</div>
</div>
</div>

<div class="form-group">
<label>Assessment Details</label>
<textarea name="assessment" class="form-control" rows="3"></textarea>
</div>

<div class="form-group">
<label>Notes</label>
<textarea name="notes" class="form-control" rows="2"></textarea>
</div>

<button class="btn btn-primary" name="book" value="1">Book Assessment</button>
<a href="leads.php" class="btn btn-secondary">Cancel</a>

</form>

</div>

</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
