<?php
require_once '../../connection.php'; // Include DB connection

$logFile = "connection_monitor.log"; // Log file

// 🟢 Get total active connections
$statusQuery = "SHOW STATUS LIKE 'Threads_connected'";
$statusResult = $conn->query($statusQuery);
$statusRow = $statusResult->fetch_assoc();
$activeConnections = $statusRow['Value'] ?? 0;

// 🟢 Get details of all connections
$processQuery = "SHOW PROCESSLIST";
$processResult = $conn->query($processQuery);

$logData = date("Y-m-d H:i:s") . " | Active Connections: " . $activeConnections . PHP_EOL;

// 🟡 Kill long-running idle connections (More than 180s)
$killCount = 0;
while ($row = $processResult->fetch_assoc()) {
    $id = $row['Id'];
    $time = $row['Time'];
    $command = $row['Command'];

    // If connection is sleeping for > 180 seconds, kill it
    if ($command == "Sleep" && $time > 180) {
        $conn->query("KILL $id");
        $killCount++;
        $logData .= "🔴 Killed Connection ID: $id | Time: $time sec" . PHP_EOL;
    }
}

// Log details
$logData .= "🔹 Killed $killCount old connections." . PHP_EOL;
file_put_contents($logFile, $logData, FILE_APPEND);

// Close the connection
$conn->close();
?>