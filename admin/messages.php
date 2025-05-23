<?php
// admin/messages.php

// Start session
session_start();
require_once '../connection.php';

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// DB connection
$database = new Database();
$db = $database->getConnection();

// Fetch Teachers and Incharges
$teachers = [];
$incharges = [];

$userQuery = "SELECT id, username, role FROM users WHERE role IN ('teacher', 'incharge')";
$stmt = $db->prepare($userQuery);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if ($row['role'] === 'teacher') {
        $teachers[] = $row;
    } else {
        $incharges[] = $row;
    }
}
$stmt->close();

// Handle Message Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $recipients = $_POST['recipients'] ?? [];

    if (!empty($subject) && !empty($message) && !empty($recipients)) {
        // Use internal_messages and internal_message_recipients
        $insert = $db->prepare("INSERT INTO internal_messages (sender_id, sender_role, subject, message, created_at) VALUES (?, 'admin', ?, ?, NOW())");
        $admin_id = 1; // Hardcoded or pulled from session if available
        $insert->bind_param("iss", $admin_id, $subject, $message);
        $insert->execute();
        $message_id = $insert->insert_id;
        $insert->close();

        foreach ($recipients as $recipient_id) {
            $role = '';
            foreach ($teachers as $t) {
                if ($t['id'] == $recipient_id) {
                    $role = 'teacher';
                    break;
                }
            }
            foreach ($incharges as $i) {
                if ($i['id'] == $recipient_id) {
                    $role = 'incharge';
                    break;
                }
            }

            if ($role) {
                $subInsert = $db->prepare("INSERT INTO internal_message_recipients (message_id, recipient_id, recipient_role) VALUES (?, ?, ?)");
                $subInsert->bind_param("iis", $message_id, $recipient_id, $role);
                $subInsert->execute();
                $subInsert->close();
            }
        }
        $success = "Message sent successfully.";
    } else {
        $error = "All fields are required.";
    }
}

// Fetch Sent Messages
$sentMessages = [];
$query = "SELECT m.subject, m.message, m.created_at, u.username,
                 imr.ack_message, imr.ack_at
          FROM internal_messages m
          JOIN internal_message_recipients imr ON imr.message_id = m.id
          JOIN users u ON u.id = imr.recipient_id
          WHERE m.sender_role = 'admin'
          ORDER BY m.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$messagesResult = $stmt->get_result();
while ($row = $messagesResult->fetch_assoc()) {
    $sentMessages[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Admin Messages</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="css/select2.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-bs4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Send Message</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php elseif (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="mb-4">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="5" class="form-control summernote" required></textarea>
                </div>
                <div class="form-group">
                    <label>Recipients (Teachers & Incharges)</label>
                    <select name="recipients[]" class="form-control select2" multiple required>
                        <optgroup label="Teachers">
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['username']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Incharges">
                            <?php foreach ($incharges as $incharge): ?>
                                <option value="<?php echo $incharge['id']; ?>"><?php echo htmlspecialchars($incharge['username']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
            </form>

            <h3>Sent Messages</h3>
            <div class="card">
                <div class="card-body">
                    <table class="table datatable">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Recipient</th>
                                <th>Sent At</th>
                                <th>Reply</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sentMessages as $msg): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                    <td><?php echo $msg['message']; ?></td>
                                    <td><?php echo htmlspecialchars($msg['username']); ?></td>
                                    <td><?php echo htmlspecialchars($msg['created_at']); ?></td>
                                    <td>
                                        <?php if (!empty($msg['ack_message'])): ?>
                                            <?php echo htmlspecialchars($msg['ack_message']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($msg['ack_at']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No reply</span>
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
<script src="js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-bs4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
  $(document).ready(function() {
      $('.datatable').DataTable({
          "order": [[3, "desc"]] // Order by Sent At descending by default
      });
      $('.summernote').summernote({
          height: 200
      });
      $('.select2').select2();
  });
</script>
</body>
</html>