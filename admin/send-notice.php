<?php
session_start();
require_once '../connection.php';
require_once '../vendor/autoload.php'; // web-push-php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// Check admin authentication
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch locations, batches, and parents for selection
$locations = [];
$location_stmt = $db->prepare("SELECT DISTINCT location FROM centers WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
$location_stmt->execute();
$location_result = $location_stmt->get_result();
while ($row = $location_result->fetch_assoc()) {
    $locations[] = $row['location'];
}
$location_stmt->close();

$batches = [];
$batch_stmt = $db->prepare("SELECT id, name FROM batches ORDER BY name ASC");
$batch_stmt->execute();
$batch_result = $batch_stmt->get_result();
while ($row = $batch_result->fetch_assoc()) {
    $batches[] = $row;
}
$batch_stmt->close();

$parents = [];
$parent_stmt = $db->prepare("SELECT id, full_name, username FROM users WHERE role = 'parent' AND status = 'active' ORDER BY full_name ASC");
$parent_stmt->execute();
$parent_result = $parent_stmt->get_result();
while ($row = $parent_result->fetch_assoc()) {
    $parents[] = $row;
}
$parent_stmt->close();

// Handle form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['message'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $created_by = $_SESSION['admin_id'] ?? 0;

    // Collect selected recipients
    $selected_parents = $_POST['parent_ids'] ?? [];
    $selected_locations = $_POST['locations'] ?? [];
    $selected_batches = $_POST['batch_ids'] ?? [];

    // Build parent list based on selection
    $recipient_ids = [];

    // By parent
    if (!empty($selected_parents)) {
        $recipient_ids = array_merge($recipient_ids, $selected_parents);
    }

    // By location
    if (!empty($selected_locations)) {
        $in = implode("','", array_map('addslashes', $selected_locations));
        $res = $db->query("SELECT id FROM users WHERE role='parent' AND status='active' AND location IN ('$in')");
        while ($row = $res->fetch_assoc()) {
            $recipient_ids[] = $row['id'];
        }
    }

    // By batch
    if (!empty($selected_batches)) {
        $in = implode(",", array_map('intval', $selected_batches));
        $res = $db->query("SELECT id FROM users WHERE role='parent' AND status='active' AND batch_id IN ($in)");
        while ($row = $res->fetch_assoc()) {
            $recipient_ids[] = $row['id'];
        }
    }

    // Remove duplicates
    $recipient_ids = array_unique($recipient_ids);

    // Save notice in DB (optional, for record)
    $stmt = $db->prepare("INSERT INTO notices (title, message, location, created_by) VALUES (?, ?, ?, ?)");
    $loc = 'custom';
    $stmt->bind_param("sssi", $title, $message, $loc, $created_by);
    $stmt->execute();
    $stmt->close();

    // Send push notifications
    $auth = [
    'VAPID' => [
        'subject' => 'https://habits365club.com',
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
];
    $webPush = new WebPush($auth);

    foreach ($recipient_ids as $parent_id) {
        $sub = $db->query("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE parent_id = $parent_id");
        if ($sub && $sub->num_rows > 0) {
            $subData = $sub->fetch_assoc();
            $subscription = Subscription::create([
                'endpoint' => $subData['endpoint'],
                'publicKey' => $subData['p256dh'],
                'authToken' => $subData['auth'],
            ]);
            $payload = json_encode([
                'title' => $title,
                'body' => $message,
            ]);
            $webPush->sendNotification($subscription, $payload);
        }
    }
    foreach ($webPush->flush() as $report) {
        // Optionally log/report delivery
    }

    $success = true;
}

?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Send Notice - Admin</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Send Push Notice</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">Notice sent successfully!</div>
            <?php elseif (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="title">Notice Title</label>
                            <input type="text" class="form-control" name="title" id="title" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Notice Message</label>
                            <textarea class="form-control" name="message" id="message" rows="4" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Select Parents</label>
                            <select name="parent_ids[]" class="form-control" multiple>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['full_name']); ?> (<?php echo htmlspecialchars($parent['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Locations</label>
                            <select name="locations[]" class="form-control" multiple>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Batches</label>
                            <select name="batch_ids[]" class="form-control" multiple>
                                <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Notice</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>