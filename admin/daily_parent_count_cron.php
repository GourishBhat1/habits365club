<?php
require_once '../connection.php';
$db = (new Database())->getConnection();

$date = date('Y-m-d');

// Get all locations from centers table
$locations = [];
$res = $db->query("SELECT location FROM centers");
while ($row = $res->fetch_assoc()) {
    $locations[] = $row['location'];
}

// For each location, count active parents and insert into history table
foreach ($locations as $location) {
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE role = 'parent' AND status = 'active' AND location = ?");
    $stmt->bind_param("s", $location);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    // Insert into history table
    $insert = $db->prepare("INSERT INTO parent_counts_history (date, location, active_parent_count) VALUES (?, ?, ?)");
    $insert->bind_param("ssi", $date, $location, $count);
    $insert->execute();
    $insert->close();
}

// Optionally, record system-wide total
$stmt = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'parent' AND status = 'active'");
$total = $stmt->fetch_assoc()['cnt'];
$insert = $db->prepare("INSERT INTO parent_counts_history (date, location, active_parent_count) VALUES (?, ?, ?)");
$system = 'ALL';
$insert->bind_param("ssi", $date, $system, $total);
$insert->execute();
$insert->close();