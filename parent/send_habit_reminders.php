<?php
require_once '../connection.php';
require_once '../vendor/autoload.php'; // web-push-php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// VAPID keys (replace with your actual keys)
$auth = [
    'VAPID' => [
        'subject' => 'https://habits365club.com',
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
];

$webPush = new WebPush($auth);

$now = date('H:i');

// Get all habits with reminder_times set
$habits = $conn->query("SELECT id, title, reminder_times FROM habits WHERE reminder_times IS NOT NULL");
while ($habit = $habits->fetch_assoc()) {
    $times = json_decode($habit['reminder_times'], true);
    if (is_array($times) && in_array($now, $times)) {
        // Get all active parents assigned to this habit (customize as needed)
        $parents = $conn->query("SELECT id FROM users WHERE role='parent' AND status='active'");
        while ($parent = $parents->fetch_assoc()) {
            // Get push subscription for this parent
            $sub = $conn->query("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE parent_id = {$parent['id']}");
            if ($sub && $sub->num_rows > 0) {
                $subData = $sub->fetch_assoc();
                $subscription = Subscription::create([
                    'endpoint' => $subData['endpoint'],
                    'publicKey' => $subData['p256dh'],
                    'authToken' => $subData['auth'],
                ]);
                $payload = json_encode([
                    'title' => 'Habit Reminder',
                    'body' => "Don't forget to submit evidence for '{$habit['title']}'!",
                ]);
                $webPush->sendNotification($subscription, $payload);
            }
        }
    }
}

// Flush all notifications
foreach ($webPush->flush() as $report) {
    // Optionally log delivery status
    // $endpoint = $report->getRequest()->getUri()->__toString();
    // if ($report->isSuccess()) {
    //     error_log("Notification sent successfully to {$endpoint}");
    // } else {
    //     error_log("Notification failed for {$endpoint}: {$report->getReason()}");
    // }
}
?>