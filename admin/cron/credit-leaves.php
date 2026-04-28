<?php
require_once __DIR__ . '/../../connection.php';

// Allow only CLI execution (prevents browser misuse)
if (php_sapi_name() !== 'cli') {
    die("Unauthorized access");
}

$db = (new Database())->getConnection();

/*
SCALABLE LEAVE CREDIT CRON

✔ Creates missing leave_balance rows
✔ Credits leaves once per month
✔ Works safely for large datasets
✔ Avoids NOT IN (uses LEFT JOIN)
✔ Idempotent (safe multiple runs)
*/

$currentMonth = date('Y-m');

/* ---------------------------------
   STEP 1: CREATE MISSING BALANCE ROWS
----------------------------------*/
$stmt = $db->prepare("
    INSERT INTO leave_balance (user_id, total_earned, total_used, last_credit_month)
    SELECT u.id, 0, 0, NULL
    FROM users u
    LEFT JOIN leave_balance lb ON u.id = lb.user_id
    WHERE lb.user_id IS NULL
    AND u.role IN ('teacher','incharge','sales','quality')
    AND u.status = 'active'
    AND u.approved = 1
");
$stmt->execute();
$created = $stmt->affected_rows;
$stmt->close();

/* ---------------------------------
   STEP 2: CREDIT MONTHLY LEAVES
----------------------------------*/
$stmt = $db->prepare("
    UPDATE leave_balance lb
    JOIN users u ON lb.user_id = u.id
    SET lb.total_earned = lb.total_earned + 1,
        lb.last_credit_month = ?
    WHERE (lb.last_credit_month IS NULL OR lb.last_credit_month != ?)
    AND u.role IN ('teacher','incharge','sales','quality')
    AND u.status = 'active'
    AND u.approved = 1
");

$stmt->bind_param("ss", $currentMonth, $currentMonth);
$stmt->execute();
$credited = $stmt->affected_rows;
$stmt->close();

/* ---------------------------------
   STEP 3: OPTIONAL CLEANUP (FUTURE SAFE)
----------------------------------*/
// You can add archival / logging here later

echo "✅ Leave cron executed\n";
echo "🆕 Users initialized: $created\n";
echo "📈 Leaves credited: $credited for $currentMonth\n";