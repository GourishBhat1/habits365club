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
$lead_id = (int)($_GET['id'] ?? 0);

// Fetch lead (must belong to this sales person)
$stmt = $db->prepare("SELECT * FROM leads WHERE id = ? AND assigned_to = ?");
$stmt->bind_param("ii", $lead_id, $sales_id);
$stmt->execute();
$lead = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lead) {
    die("Lead not found or access denied.");
}

// Handle status update
$statusMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = trim($_POST['new_status'] ?? '');
    if (!empty($new_status)) {
        $stmt = $db->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $lead_id);
        if ($stmt->execute()) {
            $statusMsg = "Status updated to: " . $new_status;
            $lead['status'] = $new_status;
        }
        $stmt->close();
    }
}

// Handle add follow-up
$followupMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_followup'])) {
    $fu_type = trim($_POST['fu_type'] ?? 'call');
    $fu_notes = trim($_POST['fu_notes'] ?? '');
    $fu_due = trim($_POST['fu_due'] ?? '');

    if (empty($fu_due)) {
        $followupMsg = "Follow-up date is required.";
    } else {
        $stmt = $db->prepare("INSERT INTO lead_followups (lead_id, sales_id, type, notes, due_date, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iisss", $lead_id, $sales_id, $fu_type, $fu_notes, $fu_due);
        if ($stmt->execute()) {
            $followupMsg = "Follow-up added successfully.";
        } else {
            $followupMsg = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle complete follow-up
if (isset($_GET['complete_fu'])) {
    $fu_id = (int)$_GET['complete_fu'];
    $stmt = $db->prepare("UPDATE lead_followups SET status = 'completed', completed_at = NOW() WHERE id = ? AND lead_id = ? AND sales_id = ?");
    $stmt->bind_param("iii", $fu_id, $lead_id, $sales_id);
    $stmt->execute();
    $stmt->close();
    header("Location: view-lead.php?id=" . $lead_id);
    exit();
}

// Fetch follow-ups
$followups = [];
$stmt = $db->prepare("SELECT * FROM lead_followups WHERE lead_id = ? ORDER BY due_date DESC");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $followups[] = $row;
}
$stmt->close();
?>
<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Lead - <?= htmlspecialchars($lead['full_name']) ?></title>
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Lead: <?= htmlspecialchars($lead['full_name']) ?></h2>

<?php if ($statusMsg): ?>
<div class="alert alert-success"><?= $statusMsg ?></div>
<?php endif; ?>
<?php if ($followupMsg): ?>
<div class="alert alert-<?= strpos($followupMsg, 'Error') !== false ? 'danger' : 'success' ?>"><?= $followupMsg ?></div>
<?php endif; ?>

<div class="row">
<div class="col-md-6">
<div class="card shadow mb-4">
<div class="card-body">
<h5>Lead Information</h5>
<table class="table table-bordered">
    <tr><td><strong>Name</strong></td><td><?= htmlspecialchars($lead['full_name']) ?></td></tr>
    <tr><td><strong>Phone</strong></td><td><a href="tel:<?= $lead['phone'] ?>"><?= htmlspecialchars($lead['phone']) ?></a></td></tr>
    <tr><td><strong>Email</strong></td><td><?= htmlspecialchars($lead['email']) ?></td></tr>
    <tr><td><strong>Child Name</strong></td><td><?= htmlspecialchars($lead['child_name']) ?></td></tr>
    <tr><td><strong>Standard</strong></td><td><?= htmlspecialchars($lead['standard']) ?></td></tr>
    <tr><td><strong>School</strong></td><td><?= htmlspecialchars($lead['school_name']) ?></td></tr>
    <tr><td><strong>Location</strong></td><td><?= htmlspecialchars($lead['location']) ?></td></tr>
    <tr><td><strong>Course Interest</strong></td><td><?= htmlspecialchars($lead['course_interest']) ?></td></tr>
    <tr><td><strong>Source</strong></td><td><?= htmlspecialchars($lead['lead_source']) ?></td></tr>
    <tr><td><strong>Status</strong></td><td><?= htmlspecialchars($lead['status']) ?></td></tr>
    <tr><td><strong>Created</strong></td><td><?= $lead['created_at'] ?></td></tr>
</table>

<?php if (!empty($lead['notes'])): ?>
<p><strong>Notes:</strong><br><?= nl2br(htmlspecialchars($lead['notes'])) ?></p>
<?php endif; ?>
</div>
</div>

<div class="card shadow mb-4">
<div class="card-body">
<h5>Quick Actions</h5>
<div class="btn-group" role="group">
    <a href="tel:<?= $lead['phone'] ?>" class="btn btn-success">Call</a>
    <?php if ($lead['status'] != 'registered'): ?>
    <a href="register-parent.php?lead_id=<?= $lead['id'] ?>" class="btn btn-primary">Register as Parent</a>
    <?php endif; ?>
    <?php if ($lead['status'] != 'assessment_booked' && $lead['status'] != 'registered'): ?>
    <a href="book-assessment.php?lead_id=<?= $lead['id'] ?>" class="btn btn-dark">Book Assessment</a>
    <?php endif; ?>
</div>
</div>
</div>
</div>

<div class="col-md-6">
<div class="card shadow mb-4">
<div class="card-body">
<h5>Update Status</h5>
<form method="POST">
    <input type="hidden" name="update_status" value="1">
    <div class="form-group">
        <select name="new_status" class="form-control">
            <option value="new" <?= $lead['status']=='new'?'selected':'' ?>>New</option>
            <option value="contacted" <?= $lead['status']=='contacted'?'selected':'' ?>>Contacted</option>
            <option value="follow_up" <?= $lead['status']=='follow_up'?'selected':'' ?>>Follow Up</option>
            <option value="assessment_booked" <?= $lead['status']=='assessment_booked'?'selected':'' ?>>Assessment Booked</option>
            <option value="registered" <?= $lead['status']=='registered'?'selected':'' ?>>Registered</option>
            <option value="not_interested" <?= $lead['status']=='not_interested'?'selected':'' ?>>Not Interested</option>
            <option value="lost" <?= $lead['status']=='lost'?'selected':'' ?>>Lost</option>
        </select>
    </div>
    <button class="btn btn-warning">Update Status</button>
</form>
</div>
</div>

<div class="card shadow mb-4">
<div class="card-body">
<h5>Add Follow-up</h5>
<form method="POST">
    <input type="hidden" name="add_followup" value="1">
    <div class="form-group">
        <label>Type</label>
        <select name="fu_type" class="form-control">
            <option value="call">Call</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="visit">Visit</option>
            <option value="email">Email</option>
            <option value="note">Note</option>
        </select>
    </div>
    <div class="form-group">
        <label>Notes</label>
        <textarea name="fu_notes" class="form-control" rows="2"></textarea>
    </div>
    <div class="form-group">
        <label>Due Date *</label>
        <input type="datetime-local" name="fu_due" class="form-control" required>
    </div>
    <button class="btn btn-info">Add Follow-up</button>
</form>
</div>
</div>
</div>
</div>

<div class="card shadow">
<div class="card-body">
<h5>Follow-up History</h5>
<?php if (empty($followups)): ?>
<p class="text-muted">No follow-ups recorded.</p>
<?php else: ?>
<table class="table table-bordered">
<thead>
<tr><th>Type</th><th>Notes</th><th>Due Date</th><th>Status</th><th>Completed</th><th>Action</th></tr>
</thead>
<tbody>
<?php foreach ($followups as $fu): ?>
<tr>
    <td><?= htmlspecialchars($fu['type']) ?></td>
    <td><?= htmlspecialchars($fu['notes']) ?></td>
    <td><?= $fu['due_date'] ?></td>
    <td>
        <?php if ($fu['status'] == 'completed'): ?>
            <span class="badge badge-success">Completed</span>
        <?php else: ?>
            <span class="badge badge-warning">Pending</span>
        <?php endif; ?>
    </td>
    <td><?= $fu['completed_at'] ?? '-' ?></td>
    <td>
        <?php if ($fu['status'] != 'completed'): ?>
            <a href="view-lead.php?id=<?= $lead_id ?>&complete_fu=<?= $fu['id'] ?>" class="btn btn-sm btn-success">Mark Done</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>

</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
