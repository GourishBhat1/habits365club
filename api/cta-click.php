<?php
header('Content-Type: application/json');

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

/*
 |--------------------------------------------------------------------------
 | CTA Click Tracking
 |--------------------------------------------------------------------------
 | Records every click (no deduplication)
 | Called before redirect on frontend
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

$campaign_id = isset($_POST['campaign_id'])
    ? (int)$_POST['campaign_id']
    : 0;

if ($campaign_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid campaign id'
    ]);
    exit();
}

/* Capture metadata (optional but useful) */
$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

/* Insert click */
$stmt = $db->prepare("
    INSERT INTO cta_clicks (campaign_id, ip_address, user_agent)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iss", $campaign_id, $ip_address, $user_agent);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true
]);
exit();
