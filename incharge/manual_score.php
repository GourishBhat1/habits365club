<?php
// incharge/manual_score.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID
$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];
$stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$result = $stmt->get_result();
$incharge = $result->fetch_assoc();
$incharge_id = $incharge['id'] ?? null;
$stmt->close();

if (!$incharge_id) {
    die("Incharge not found.");
}

// Fetch parents assigned to incharge's batches
$parents = [];
$parentQuery = "
    SELECT u.id, u.full_name, b.name AS batch_name
    FROM users u
    JOIN batches b ON u.batch_id = b.id
    WHERE u.role = 'parent' AND b.incharge_id = ?
";
$parentStmt = $db->prepare($parentQuery);
$parentStmt->bind_param("i", $incharge_id);
$parentStmt->execute();
$parentRes = $parentStmt->get_result();
while ($row = $parentRes->fetch_assoc()) {
    $parents[] = $row;
}
$parentStmt->close();

// Handle manual score submission
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parent_id = $_POST['parent_id'] ?? null;
    $points = $_POST['points'] ?? 0;

    if ($parent_id && is_numeric($points) && $points > 0) {
        // Insert score into `evidence_uploads` with necessary details
        $insertStmt = $db->prepare("
            INSERT INTO evidence_uploads (parent_id, points, status, uploaded_at) 
            VALUES (?, ?, 'approved', NOW())
        ");
        $insertStmt->bind_param("ii", $parent_id, $points);

        if ($insertStmt->execute()) {
            $success = "Score added successfully!";
        } else {
            $error = "Failed to add score.";
        }
        $insertStmt->close();
    } else {
        $error = "Invalid input. Please ensure the score is a positive number.";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Manual Score Entry - Incharge</title>
    <link rel="stylesheet" href="css/select2.min.css">
    <script src="js/jquery.min.js"></script>
    <script src="js/select2.min.js"></script>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Manual Score Entry</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header"><strong>Enter Student Score</strong></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="parent_id">Select Student</label>
                            <select name="parent_id" id="parent_id" class="form-control select2" required>
                                <option value="">Select Student</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['full_name'] . " - " . $parent['batch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="points">Enter Score</label>
                            <input type="number" name="points" id="points" class="form-control" min="1" max="100" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Score</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%',
            placeholder: "Select Student",
            allowClear: true
        });
    });
</script>
</body>
</html>