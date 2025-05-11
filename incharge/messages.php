<?php
session_start();
require_once '../connection.php';

if (!isset($_SESSION['incharge_id']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$incharge_id = $_SESSION['incharge_id'] ?? null;

// Fetch messages sent to this incharge
$messages = [];
$stmt = $db->prepare("
    SELECT m.subject, m.message, m.created_at, r.is_read, r.read_at, r.ack_message, r.ack_at, m.id as message_id, r.id as recipient_id
    FROM internal_messages m
    JOIN internal_message_recipients r ON m.id = r.message_id
    WHERE r.recipient_id = ? AND r.recipient_role = 'incharge'
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $incharge_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

// Handle acknowledgment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ack_message'], $_POST['recipient_id'])) {
    $ackMessage = trim($_POST['ack_message']);
    $recipientId = (int)$_POST['recipient_id'];

    $stmt = $db->prepare("UPDATE internal_message_recipients SET ack_message = ?, ack_at = NOW(), is_read = 1, read_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $ackMessage, $recipientId);
    $stmt->execute();
    $stmt->close();

    header("Location: messages.php?status=acknowledged");
    exit();
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Incharge Messages</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Messages from Admin</h2>

            <div class="card shadow">
                <div class="card-body">
                    <!-- Desktop Table View -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover datatable">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Received At</th>
                                    <th>Acknowledgment</th>
                                    <th>Reply</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $msg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                        <td><?php echo $msg['message']; ?></td>
                                        <td><?php echo htmlspecialchars($msg['created_at']); ?></td>
                                        <td>
                                            <?php if ($msg['ack_at']): ?>
                                                <span class="text-success">Acknowledged on <?php echo htmlspecialchars($msg['ack_at']); ?></span><br>
                                                <small><?php echo htmlspecialchars($msg['ack_message']); ?></small>
                                            <?php else: ?>
                                                <span class="text-danger">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$msg['ack_at']): ?>
                                                <form method="POST" class="form-inline">
                                                    <input type="hidden" name="recipient_id" value="<?php echo $msg['recipient_id']; ?>">
                                                    <input type="text" name="ack_message" class="form-control mr-2 mb-2 mb-sm-0" placeholder="Your message..." required>
                                                    <button type="submit" class="btn btn-sm btn-primary">Send</button>
                                                </form>
                                            <?php else: ?>
                                                <i class="text-muted">Replied</i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="d-block d-md-none">
                        <?php foreach ($messages as $msg): ?>
                            <div class="card mb-3 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($msg['subject']); ?></h5>
                                    <p class="card-text"><?php echo $msg['message']; ?></p>
                                    <p class="mb-1"><strong>Received:</strong> <?php echo htmlspecialchars($msg['created_at']); ?></p>
                                    <p class="mb-1">
                                        <strong>Status:</strong>
                                        <?php if ($msg['ack_at']): ?>
                                            <span class="text-success">Acknowledged on <?php echo htmlspecialchars($msg['ack_at']); ?></span><br>
                                            <small><?php echo htmlspecialchars($msg['ack_message']); ?></small>
                                        <?php else: ?>
                                            <span class="text-danger">Pending</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$msg['ack_at']): ?>
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="recipient_id" value="<?php echo $msg['recipient_id']; ?>">
                                            <div class="form-group">
                                                <input type="text" name="ack_message" class="form-control" placeholder="Your message..." required>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-primary mt-1">Send</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
<?php include 'includes/footer.php'; ?>
<script src="js/jquery.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>
<script>
  $(document).ready(function() {
    $('.datatable').DataTable();
  });
</script>
</body>
</html>