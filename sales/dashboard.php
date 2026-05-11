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

// STAT CARDS
// 1. New leads today (assigned to me)
$new_today = 0;
$stmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND DATE(created_at) = CURDATE()");
$stmt->bind_param("i", $sales_id);
$stmt->execute();
$stmt->bind_result($new_today);
$stmt->fetch();
$stmt->close();

// 2. Pending follow-ups today (due today or overdue, assigned to me)
$pending_followups = 0;
$stmt = $db->prepare("SELECT COUNT(*) FROM lead_followups WHERE sales_id = ? AND status = 'pending' AND due_date <= NOW() + INTERVAL 1 DAY");
$stmt->bind_param("i", $sales_id);
$stmt->execute();
$stmt->bind_result($pending_followups);
$stmt->fetch();
$stmt->close();

// 3. Registered this month (leads with status 'registered', assigned to me)
$registered_month = 0;
$stmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND status = 'registered' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())");
$stmt->bind_param("i", $sales_id);
$stmt->execute();
$stmt->bind_result($registered_month);
$stmt->fetch();
$stmt->close();

// 4. Total active leads (new + contacted + follow_up, assigned to me)
$active_leads = 0;
$stmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND status IN ('new','contacted','follow_up')");
$stmt->bind_param("i", $sales_id);
$stmt->execute();
$stmt->bind_result($active_leads);
$stmt->fetch();
$stmt->close();

// FOLLOW-UPS DUE TODAY
$followups = [];
$stmt = $db->prepare("
    SELECT lf.id, lf.due_date, lf.type, lf.notes, l.full_name, l.phone, l.id as lead_id
    FROM lead_followups lf
    JOIN leads l ON lf.lead_id = l.id
    WHERE lf.sales_id = ? AND lf.status = 'pending' AND lf.due_date <= NOW() + INTERVAL 1 DAY
    ORDER BY lf.due_date ASC
    LIMIT 20
");
$stmt->bind_param("i", $sales_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $followups[] = $row;
}
$stmt->close();

// RECENT LEADS
$recent_leads = [];
$stmt = $db->prepare("
    SELECT id, full_name, phone, lead_source, status, created_at
    FROM leads
    WHERE assigned_to = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $sales_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_leads[] = $row;
}
$stmt->close();
?>
<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Sales Dashboard</title>
</head>
<body class="vertical light">
<div class="wrapper">
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
<div class="container-fluid">

<h2 class="page-title">Sales Dashboard</h2>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h6>New Leads Today</h6>
            <h3><?= $new_today ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h6>Follow-ups Due</h6>
            <h3><?= $pending_followups ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h6>Registered This Month</h6>
            <h3><?= $registered_month ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h6>Active Leads</h6>
            <h3><?= $active_leads ?></h3>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-body">
                <h5>Follow-ups Due</h5>
                <?php if (empty($followups)): ?>
                    <p class="text-muted">No pending follow-ups.</p>
                <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Due</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($followups as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['full_name']) ?></td>
                            <td><a href="tel:<?= $f['phone'] ?>"><?= htmlspecialchars($f['phone']) ?></a></td>
                            <td><?= $f['due_date'] ?></td>
                            <td>
                                <a href="view-lead.php?id=<?= $f['lead_id'] ?>" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-body">
                <h5>Recent Leads</h5>
                <?php if (empty($recent_leads)): ?>
                    <p class="text-muted">No leads yet.</p>
                <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_leads as $l): ?>
                        <tr>
                            <td><a href="view-lead.php?id=<?= $l['id'] ?>"><?= htmlspecialchars($l['full_name']) ?></a></td>
                            <td><?= htmlspecialchars($l['lead_source']) ?></td>
                            <td><?= htmlspecialchars($l['status']) ?></td>
                            <td><?= $l['created_at'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <a href="leads.php" class="btn btn-primary btn-sm">View All Leads</a>
            </div>
        </div>
    </div>
</div>

</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
