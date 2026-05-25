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
    $location = trim($_POST['location'] ?? '');
    $time_slot = trim($_POST['time_slot'] ?? '');
    $payment_status = trim($_POST['payment_status'] ?? '');
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($child_name) || empty($mobile)) {
        $error = "Child name and mobile are required.";
    } elseif (empty($sales_id)) {
        $error = "Sales user not found. Please log in again.";
    } else {
        $stmt = $db->prepare("
            INSERT INTO assessment_bookings (lead_id, child_name, class, mobile, school_name, subject, location, time_slot, payment_status, transaction_id, notes, booked_by, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("issssssssssi", $lead_id, $child_name, $class, $mobile, $school_name, $subject, $location, $time_slot, $payment_status, $transaction_id, $notes, $sales_id);

        if ($stmt->execute()) {
            $booking_id = $stmt->insert_id;
            $stmt->close();

            // Update lead status
            if ($lead_id) {
                $ustmt = $db->prepare("UPDATE leads SET status = 'assessment_booked', updated_at = NOW(), notes = CONCAT(IFNULL(notes,''), '\nAssessment booked (Booking ID: $booking_id) on ', NOW()) WHERE id = ?");
                $ustmt->bind_param("i", $lead_id);
                $ustmt->execute();
                $ustmt->close();
            }

            $success = "Assessment booked successfully! Booking #$booking_id";
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
<label>Subject</label>
                    <select name="subject" class="form-control">
                        <option value="">Select</option>
                        <option value="English">English</option>
                        <option value="Marathi">Marathi</option>
                        <option value="Hindi">Hindi</option>
                        <option value="Konkani">Konkani</option>
                        <option value="Maths">Maths</option>
                    </select>
</div>
</div>
</div>

<div class="row">
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
<div class="col-md-6">
<div class="form-group">
<label>Time Slot</label>
<select name="time_slot" class="form-control">
    <option value="">Select</option>
    <option value="4pm to 5pm">4pm to 5pm</option>
    <option value="5pm to 6pm">5pm to 6pm</option>
</select>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Payment Status</label>
<select name="payment_status" class="form-control" onchange="toggleTransactionId(this)">
    <option value="">Select</option>
    <option value="Paid">Paid</option>
    <option value="To Be Paid at Center">To Be Paid at Center</option>
</select>
</div>
</div>
<div class="col-md-6">
<div class="form-group" id="transaction_id_group" style="display:none;">
<label>UPI Transaction ID</label>
<input type="text" name="transaction_id" class="form-control" placeholder="Enter UPI reference / transaction ID">
</div>
</div>
</div>

<div class="form-group">
<label>Notes</label>
<textarea name="notes" class="form-control" rows="3"></textarea>
</div>

<button class="btn btn-primary" name="book" value="1">Book Assessment</button>
<a href="leads.php" class="btn btn-secondary">Cancel</a>

</form>

</div>

</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>

<script>
function toggleTransactionId(select) {
    var group = document.getElementById('transaction_id_group');
    if (select.value === 'Paid') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        group.querySelector('input').value = '';
    }
}
</script>

</body>
</html>
