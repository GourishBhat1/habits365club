<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/parent_count_cron_error.log');

require_once '../connection.php';
$db = (new Database())->getConnection();

$date = date('Y-m-d');

// Get all locations from centers table
$locations = [];
$res = $db->query("SELECT location FROM centers");
if (!$res) {
    error_log("Failed to fetch locations: " . $db->error);
}
while ($row = $res->fetch_assoc()) {
    $locations[] = $row['location'];
}

// For each location, count active parents and insert into history table
foreach ($locations as $location) {
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE role = 'parent' AND status = 'active' AND location = ?");
    if (!$stmt) {
        error_log("Prepare failed for count: " . $db->error);
        continue;
    }
    $stmt->bind_param("s", $location);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    // Insert into history table
    $insert = $db->prepare("INSERT INTO parent_counts_history (date, location, active_parent_count) VALUES (?, ?, ?)");
    if (!$insert) {
        error_log("Prepare failed for insert: " . $db->error);
        continue;
    }
    $insert->bind_param("ssi", $date, $location, $count);
    if (!$insert->execute()) {
        error_log("Insert failed: " . $insert->error);
    }
    $insert->close();
}

// Optionally, record system-wide total
$stmt = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'parent' AND status = 'active'");
if (!$stmt) {
    error_log("Failed to fetch total: " . $db->error);
} else {
    $total = $stmt->fetch_assoc()['cnt'];
    $insert = $db->prepare("INSERT INTO parent_counts_history (date, location, active_parent_count) VALUES (?, ?, ?)");
    if (!$insert) {
        error_log("Prepare failed for system insert: " . $db->error);
    } else {
        $system = 'ALL';
        $insert->bind_param("ssi", $date, $system, $total);
        if (!$insert->execute()) {
            error_log("System insert failed: " . $insert->error);
        }
        $insert->close();
    }
}