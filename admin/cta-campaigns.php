<?php
session_start();
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

/* -----------------------------
   VALIDATION FUNCTION
------------------------------*/
function validateCtaActionValue($type, $value, &$error) {
    if ($type === 'phone') {
        // Must start with +91 followed by 10 digits
        if (!preg_match('/^\+91[0-9]{10}$/', $value)) {
            $error = "Phone number must start with +91 followed by 10 digits (e.g. +919876543210).";
            return false;
        }
    }
    if ($type === 'whatsapp') {
        // Must contain 91XXXXXXXXXX (number or wa.me link)
        if (
            !preg_match('/^91[0-9]{10}$/', $value) &&
            !preg_match('/wa\.me\/91[0-9]{10}$/', $value) &&
            !preg_match('/https?:\/\/wa\.me\/91[0-9]{10}$/', $value)
        ) {
            $error = "WhatsApp must be a valid Indian number starting with 91 (e.g. 919876543210 or https://wa.me/919876543210).";
            return false;
        }
    }
    if ($type === 'url') {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $error = "Invalid URL.";
            return false;
        }
    }
    return true;
}

/* -----------------------------
   HANDLE CREATE / UPDATE
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {

    $campaign_name = trim($_POST['campaign_name']);
    $button_text   = $_POST['button_text'];
    $action_type   = $_POST['action_type'];
    $action_value  = trim($_POST['action_value']);
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    $error = '';
    if (!validateCtaActionValue($action_type, $action_value, $error)) {
        $_SESSION['error'] = $error;
        header("Location: cta-campaigns.php");
        exit();
    }

    if ($is_active === 1) {
        $db->query("UPDATE cta_campaigns SET is_active = 0");
    }

    $stmt = $db->prepare("
        INSERT INTO cta_campaigns
        (campaign_name, button_text, action_type, action_value, is_active)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssi",
        $campaign_name,
        $button_text,
        $action_type,
        $action_value,
        $is_active
    );
    $stmt->execute();
    $stmt->close();

    header("Location: cta-campaigns.php");
    exit();
}

/* -----------------------------
   HANDLE ACTIVATE
------------------------------*/
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];

    $db->query("UPDATE cta_campaigns SET is_active = 0");

    $stmt = $db->prepare("UPDATE cta_campaigns SET is_active = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: cta-campaigns.php");
    exit();
}

/* -----------------------------
   HANDLE DEACTIVATE
------------------------------*/
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];

    $stmt = $db->prepare("UPDATE cta_campaigns SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: cta-campaigns.php");
    exit();
}

/* -----------------------------
   FETCH CAMPAIGNS + CLICKS
------------------------------*/
$campaigns = [];
$sql = "
    SELECT c.*,
           COUNT(cc.id) AS clicks
    FROM cta_campaigns c
    LEFT JOIN cta_clicks cc ON cc.campaign_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
";
$res = $db->query($sql);
while ($row = $res->fetch_assoc()) {
    $campaigns[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <title>CTA Campaigns</title>
</head>

<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">

            <h2 class="page-title">CTA Campaigns</h2>

            <!-- CREATE CAMPAIGN -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <strong>Create CTA Campaign</strong>
                </div>
                <div class="card-body">
                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Campaign Name</label>
                                <input type="text" name="campaign_name"
                                       class="form-control" required>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Button Text</label>
                                <select name="button_text" class="form-control" required>
                                    <option value="Call Us">Call Us</option>
                                    <option value="Chat With Us">Chat With Us</option>
                                    <option value="Enroll Now">Enroll Now</option>
                                    <option value="Book Free Demo">Book Free Demo</option>
                                    <option value="Contact Now">Contact Now</option>
                                </select>
                            </div>

                            <div class="form-group col-md-2">
                                <label>Action Type</label>
                                <select name="action_type" class="form-control" required>
                                    <option value="phone">Phone</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="url">URL</option>
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Action Value</label>
                                <input type="text" name="action_value"
                                       class="form-control"
                                       placeholder="+9199xxxx / wa.me / URL"
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active">
                                Make this campaign active
                            </label>
                        </div>

                        <button type="submit" name="create_campaign"
                                class="btn btn-primary">
                            Create Campaign
                        </button>
                    </form>
                </div>
            </div>

            <!-- CAMPAIGN LIST -->
            <div class="card shadow">
                <div class="card-header">
                    <strong>All Campaigns</strong>
                </div>
                <div class="card-body">
                    <table id="campaignTable"
                           class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Button Text</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Clicks</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['campaign_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['button_text']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($c['action_type']); ?>
                                    </td>
                                    <td>
                                        <?php if ($c['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$c['clicks']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                                    <td>
                                        <?php if ($c['is_active']): ?>
                                            <a href="?deactivate=<?php echo $c['id']; ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Deactivate this campaign?');">
                                                Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="?activate=<?php echo $c['id']; ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                Activate
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function () {
    $('#campaignTable').DataTable({
        pageLength: 10,
        order: [[5, 'desc']]
    });
});
</script>

</body>
</html>
