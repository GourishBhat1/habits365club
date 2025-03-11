<?php
require_once 'connection.php'; // Adjust path as needed

$database = new Database();
$conn = $database->getConnection();

// Get the current connection ID to prevent self-kill
$selfQuery = "SELECT CONNECTION_ID() AS conn_id";
$selfResult = $conn->query($selfQuery);
$selfRow = $selfResult->fetch_assoc();
$selfConnectionID = (int)$selfRow['conn_id'];

// Find connections older than 3 minutes (180 seconds) but exclude the current one
$query = "SELECT ID FROM information_schema.processlist WHERE TIME > 180 AND COMMAND='Sleep' AND ID != $selfConnectionID";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $killQuery = "KILL " . (int)$row['ID'];
    $conn->query($killQuery);
}
?>