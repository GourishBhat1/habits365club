<?php
session_start();
require_once '../connection.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$parent_id = $_SESSION['parent_id'] ?? null;

if ($parent_id && !empty($data['endpoint']) && !empty($data['keys']['p256dh']) && !empty($data['keys']['auth'])) {
    $stmt = $conn->prepare("REPLACE INTO push_subscriptions (parent_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $parent_id, $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth']);
    $success = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'error' => 'Missing data or not logged in']);
}
?>