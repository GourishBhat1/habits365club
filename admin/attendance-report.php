<?php
session_start();
require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

$where = "1";
if (!empty($_GET['center_id'])) $where .= " AND a.center_id=".(int)$_GET['center_id'];
if (!empty($_GET['date'])) $where .= " AND a.date='".addslashes($_GET['date'])."'";

$query = "SELECT a.*, u.full_name, u.username, c.location 
          FROM attendance a
          JOIN users u ON a.user_id = u.id
          JOIN centers c ON a.center_id = c.id
          WHERE $where
          ORDER BY a.date DESC, a.punch_in_time ASC";
$res = $db->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attendance Report</title>
</head>
<body>
    <h2>Attendance Report</h2>
    <form method="get">
        <label>Center:
            <select name="center_id">
                <option value="">All</option>
                <?php
                $centers = $db->query("SELECT id, location FROM centers WHERE status='enabled'");
                while ($center = $centers->fetch_assoc()) {
                    $sel = (isset($_GET['center_id']) && $_GET['center_id'] == $center['id']) ? 'selected' : '';
                    echo "<option value='{$center['id']}' $sel>".htmlspecialchars($center['location'])."</option>";
                }
                ?>
            </select>
        </label>
        <label>Date: <input type="date" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>"></label>
        <button type="submit">Filter</button>
    </form>
    <table border="1" cellpadding="8">
        <tr>
            <th>User</th>
            <th>Role</th>
            <th>Center</th>
            <th>Date</th>
            <th>Punch In</th>
            <th>Punch In Location</th>
            <th>Punch Out</th>
            <th>Punch Out Location</th>
            <th>Status</th>
        </tr>
        <?php while ($row = $res->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['full_name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)</td>
            <td><?php echo htmlspecialchars($row['role']); ?></td>
            <td><?php echo htmlspecialchars($row['location']); ?></td>
            <td><?php echo htmlspecialchars($row['date']); ?></td>
            <td><?php echo htmlspecialchars($row['punch_in_time']); ?></td>
            <td>
                <?php if ($row['punch_in_lat'] && $row['punch_in_lng']): ?>
                    <a href="https://maps.google.com/?q=<?php echo $row['punch_in_lat']; ?>,<?php echo $row['punch_in_lng']; ?>" target="_blank">
                        <?php echo $row['punch_in_lat'].', '.$row['punch_in_lng']; ?>
                    </a>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['punch_out_time']); ?></td>
            <td>
                <?php if ($row['punch_out_lat'] && $row['punch_out_lng']): ?>
                    <a href="https://maps.google.com/?q=<?php echo $row['punch_out_lat']; ?>,<?php echo $row['punch_out_lng']; ?>" target="_blank">
                        <?php echo $row['punch_out_lat'].', '.$row['punch_out_lng']; ?>
                    </a>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['status']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>