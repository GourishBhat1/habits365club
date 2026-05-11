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
$sales_username = $_SESSION['sales_username'] ?? $_COOKIE['sales_username'] ?? '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $standard = trim($_POST['standard'] ?? '');
    $center_name = strtoupper(trim($_POST['center_name'] ?? ''));
    $course_name = trim($_POST['course_name'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $home_address = trim($_POST['home_address'] ?? '');
    $username = $phone;

    if (empty($full_name) || empty($phone) || empty($school_name) || empty($center_name)) {
        $error = "Name, phone, school, and center are required.";
    } else {
        // Check if phone already exists
        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $phone);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This phone number is already registered as a user.";
        } else {
            // Generate a random password for the parent
            $random_pass = substr(bin2hex(random_bytes(4)), 0, 8);
            $hashed_pass = password_hash($random_pass, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, phone, standard, location, course_name, school_name, home_address, role, status, approved, terms_accepted, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'parent', 'inactive', 0, 1, NOW())
            ");
            $stmt->bind_param("sssssssss", $username, $hashed_pass, $full_name, $phone, $standard, $center_name, $course_name, $school_name, $home_address);

            if ($stmt->execute()) {
                $parent_id = $stmt->insert_id;
                $stmt->close();

                // Update lead status to registered
                if ($lead_id) {
                    $ustmt = $db->prepare("UPDATE leads SET status = 'registered', updated_at = NOW(), notes = CONCAT(IFNULL(notes,''), '\nRegistered as parent (ID: $parent_id) on ', NOW()) WHERE id = ?");
                    $ustmt->bind_param("i", $lead_id);
                    $ustmt->execute();
                    $ustmt->close();
                }

                $success = "Parent registered successfully! Username: $phone, Password: $random_pass";
            } else {
                $error = "Error registering parent: " . $stmt->error;
            }
        }
        $check->close();
    }
}
?>
<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Register Parent - Sales</title>
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Register Parent</h2>

<div class="card shadow p-4">

<?php if ($success): ?>
<div class="alert alert-success">
    <strong><?= $success ?></strong><br>
    <small>Share these credentials with the parent. Account is pending admin approval.</small>
</div>
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
<div class="col-md-6">
<div class="form-group">
<label>Child Full Name *</label>
<input type="text" name="full_name" class="form-control" value="<?= $lead ? htmlspecialchars($lead['child_name'] ?: $lead['full_name']) : '' ?>" required>
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Mobile Number * (also username)</label>
<input type="tel" name="phone" class="form-control" value="<?= $lead ? htmlspecialchars($lead['phone']) : '' ?>" required>
</div>
</div>
</div>

<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Standard</label>
<input type="text" name="standard" class="form-control" value="<?= $lead ? htmlspecialchars($lead['standard']) : '' ?>">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Center Name *</label>
<select name="center_name" class="form-control" required>
    <option value="">Select Center</option>
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
<div class="col-md-4">
<div class="form-group">
<label>Course Name</label>
<input type="text" name="course_name" class="form-control" value="<?= $lead ? htmlspecialchars($lead['course_interest']) : '' ?>">
</div>
</div>
</div>

<div class="form-group">
<label>School Name *</label>
<input type="text" name="school_name" class="form-control" value="<?= $lead ? htmlspecialchars($lead['school_name']) : '' ?>" required>
</div>

<div class="form-group">
<label>Home Address</label>
<textarea name="home_address" class="form-control" rows="2"></textarea>
</div>

<button class="btn btn-primary" name="register" value="1">Register Parent</button>
<a href="leads.php" class="btn btn-secondary">Cancel</a>

</form>

</div>

</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
