<?php
// incharge/dashboard.php

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../connection.php';

// Check if the incharge is authenticated via session or cookie
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Fetch incharge ID from session or cookie
$incharge_id = $_SESSION['incharge_id'] ?? null;

if (!$incharge_id && isset($_COOKIE['incharge_username'])) {
    $incharge_username = $_COOKIE['incharge_username'];

    // Fetch incharge ID using username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'incharge'");
    if (!$stmt) {
        die("❌ SQL Error: " . $db->error);
    }
    $stmt->bind_param("s", $incharge_username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($incharge_id);
        $stmt->fetch();
        $_SESSION['incharge_id'] = $incharge_id;
    } else {
        header("Location: index.php?message=invalid_cookie");
        exit();
    }
    $stmt->close();
}

// Fetch assigned batches for the incharge
$batches = [];
$stmt = $db->prepare("SELECT id, name, created_at FROM batches WHERE incharge_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($batch = $result->fetch_assoc()) {
        $batches[] = $batch;
    }
    $stmt->close();
} else {
    $error = "Failed to retrieve batches.";
}

// Fetch total active students (parents) assigned under this incharge's batches
$total_students = 0;
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM users 
    WHERE role = 'parent' AND status = 'active' AND batch_id IN (SELECT id FROM batches WHERE incharge_id = ?)
");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $stmt->bind_result($total_students);
    $stmt->fetch();
    $stmt->close();
}

// Fetch total **habits submitted today** for batches assigned to this incharge
$today_habits = 0;
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT id)
    FROM evidence_uploads
    WHERE parent_id IN (SELECT id FROM users WHERE role = 'parent' AND batch_id IN 
    (SELECT id FROM batches WHERE incharge_id = ?))
    AND DATE(uploaded_at) = CURDATE()  -- ✅ Count only today's habit submissions
");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $stmt->bind_result($today_habits);
    $stmt->fetch();
    $stmt->close();
}

// Fetch center info for display (assuming one center per incharge)
$center = null;
$stmt = $db->prepare("SELECT c.id, c.location, c.attendance_start_time, c.attendance_end_time, c.latitude, c.longitude
    FROM centers c
    JOIN users u ON u.location = c.location
    WHERE u.id = ?");
if ($stmt) {
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $center = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch today's attendance for this incharge
$attendance = null;
if ($center) {
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND role = 'incharge'");
    $stmt->bind_param("is", $incharge_id, $today);
    $stmt->execute();
    $attendance = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle attendance punch in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {
    $action = $_POST['attendance_action'];
    $userLat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $userLng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
    $today = date('Y-m-d');

    // Fetch center info for attendance logic
    $stmt = $db->prepare("SELECT c.id, c.latitude, c.longitude, c.attendance_start_time FROM centers c JOIN users u ON u.location = c.location WHERE u.id = ?");
    $stmt->bind_param("i", $incharge_id);
    $stmt->execute();
    $center_for_attendance = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Geofence check (within 50 meters)
    function isWithinRadius($centerLat, $centerLng, $userLat, $userLng, $radiusMeters = 50) {
        $earthRadius = 6371000;
        $dLat = deg2rad($userLat - $centerLat);
        $dLng = deg2rad($userLng - $centerLng);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($centerLat)) * cos(deg2rad($userLat)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;
        return $distance <= $radiusMeters;
    }

    if (!$center_for_attendance || !$userLat || !$userLng) {
        $error = "Center or location not found.";
    /* TEMP DEBUG: disabling geofence check
    } elseif (!isWithinRadius($center_for_attendance['latitude'], $center_for_attendance['longitude'], $userLat, $userLng)) {
        $error = "You are not at the center location!";
    */
    } else { // skipping geofence check for debugging
        $now = date('H:i:s');
        $status = 'present';
        if ($action === 'in') {
            if ($now > $center_for_attendance['attendance_start_time']) $status = 'late';

            // Check if already punched in today
            $stmt = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? AND role = 'incharge'");
            $stmt->bind_param("is", $incharge_id, $today);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "Already punched in for today.";
            } else {
                $stmt->close();
                // Simple insert for punch in
                $stmt = $db->prepare("INSERT INTO attendance (user_id, role, center_id, punch_in_time, punch_in_lat, punch_in_lng, date, status)
                    VALUES (?, 'incharge', ?, NOW(), ?, ?, ?, ?)");
                $stmt->bind_param(
                    "iiddss",
                    $incharge_id,
                    $center_for_attendance['id'],
                    $userLat,
                    $userLng,
                    $today,
                    $status
                );
                if ($stmt->execute()) {
                    $success = "✅ Punch in recorded!";
                } else {
                    $error = "Error recording punch in: " . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'out') {
            // Update punch out for today's attendance
            $stmt = $db->prepare("UPDATE attendance SET punch_out_time=NOW(), punch_out_lat=?, punch_out_lng=? WHERE user_id=? AND date=? AND role='incharge'");
            $stmt->bind_param("ddis", $userLat, $userLng, $incharge_id, $today);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $success = "✅ Punch out recorded!";
            } else {
                $error = "Error recording punch out or not punched in yet.";
            }
            $stmt->close();
        }
        // Refresh attendance data after update
        $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND role = 'incharge'");
        $stmt->bind_param("is", $incharge_id, $today);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Incharge Dashboard - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .batch-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }
        .batch-icon {
            font-size: 40px;
            color: #007bff;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Incharge Dashboard</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Total Students -->
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <h6 class="mb-0">Total Students</h6>
                            <h3><?php echo $total_students; ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Total Batches -->
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <h6 class="mb-0">Total Batches</h6>
                            <h3><?php echo count($batches); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Today's Habits -->
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <h6 class="mb-0">Today's Habits</h6>
                            <h3><?php echo $today_habits; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Attendance Section -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <h5>Attendance (<?php echo htmlspecialchars($center['location'] ?? ''); ?>)</h5>
                    <div>
                        <strong>Start Time:</strong> <?php echo htmlspecialchars($center['attendance_start_time'] ?? '-'); ?> &nbsp;
                        <strong>End Time:</strong> <?php echo htmlspecialchars($center['attendance_end_time'] ?? '-'); ?>
                    </div>
                    <div class="mt-2">
                        <strong>Status:</strong>
                        <?php
                        if ($attendance && $attendance['punch_in_time']) {
                            echo "Punched In at " . htmlspecialchars($attendance['punch_in_time']);
                            if ($attendance['punch_out_time']) {
                                echo " | Punched Out at " . htmlspecialchars($attendance['punch_out_time']);
                            } else {
                                echo " | <span class='text-warning'>Not yet punched out</span>";
                            }
                        } else {
                            echo "<span class='text-danger'>Not yet punched in</span>";
                        }
                        ?>
                    </div>
                    <form method="POST" id="attendance-form">
                        <input type="hidden" name="attendance_action" id="attendance_action" value="">
                        <input type="hidden" name="lat" id="lat">
                        <input type="hidden" name="lng" id="lng">
                        <button type="button" class="btn btn-success" onclick="getLocationAndSubmit('in')" <?php if ($attendance && $attendance['punch_in_time']) echo 'disabled'; ?>>Punch In</button>
                        <button type="button" class="btn btn-danger" onclick="getLocationAndSubmit('out')" <?php if (!$attendance || !$attendance['punch_in_time'] || $attendance['punch_out_time']) echo 'disabled'; ?>>Punch Out</button>
                    </form>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Batches -->
            <div class="row mt-4">
                <?php if (!empty($batches)): ?>
                    <?php foreach ($batches as $batch): ?>
                        <div class="col-md-4">
                            <div class="card batch-card text-center">
                                <div class="card-header">
                                    <i class="fas fa-users batch-icon"></i>
                                    <h5 class="card-title"><?php echo htmlspecialchars($batch['name']); ?></h5>
                                    <span class="text-muted">Created on: <?php echo htmlspecialchars($batch['created_at']); ?></span>
                                </div>
                                <div class="card-body">
                                    <a href="view_students.php?batch_id=<?php echo $batch['id']; ?>" class="btn btn-primary">View Students</a>
                                    <a href="batch_habits.php?batch_id=<?php echo $batch['id']; ?>" class="btn btn-info">View Habits</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">You have no batches assigned. Please contact the admin.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function getLocationAndSubmit(action) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('attendance_action').value = action;
            document.getElementById('lat').value = position.coords.latitude;
            document.getElementById('lng').value = position.coords.longitude;
            document.getElementById('attendance-form').submit();
        }, function() {
            document.getElementById('attendance-msg').innerHTML = '<span class="text-danger">Geolocation is required for attendance.</span>';
        });
    } else {
        document.getElementById('attendance-msg').innerHTML = '<span class="text-danger">Geolocation not supported.</span>';
    }
}
</script>
</body>
</html>