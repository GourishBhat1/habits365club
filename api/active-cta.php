

<?php
header('Content-Type: application/json');

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

/*
 |--------------------------------------------------------------------------
 | Fetch Active CTA Campaign
 |--------------------------------------------------------------------------
 | Returns ONLY one active campaign (if exists)
 | Used by frontend carousel to render CTA button
 */

$sql = "
    SELECT
        id,
        button_text,
        action_type,
        action_value
    FROM cta_campaigns
    WHERE is_active = 1
    ORDER BY created_at DESC
    LIMIT 1
";

$result = $db->query($sql);

if ($result && $result->num_rows === 1) {
    $campaign = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$campaign['id'],
            'button_text' => $campaign['button_text'],
            'action_type' => $campaign['action_type'],
            'action_value' => $campaign['action_value']
        ]
    ]);
    exit();
}

/* No active campaign */
echo json_encode([
    'success' => false,
    'data' => null
]);
exit();