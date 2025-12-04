<?php
session_start();
require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

$where = "1";
if (!empty($_GET['center_id'])) $where .= " AND a.center_id=".(int)$_GET['center_id'];
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $from = addslashes($_GET['from_date']);
    $to = addslashes($_GET['to_date']);
    $where .= " AND a.date BETWEEN '$from' AND '$to'";
} elseif (!empty($_GET['from_date'])) {
    $from = addslashes($_GET['from_date']);
    $where .= " AND a.date >= '$from'";
} elseif (!empty($_GET['to_date'])) {
    $to = addslashes($_GET['to_date']);
    $where .= " AND a.date <= '$to'";
}

$query = "SELECT a.*, u.full_name, u.username, c.location 
          FROM attendance a
          JOIN users u ON a.user_id = u.id
          JOIN centers c ON a.center_id = c.id
          WHERE $where
          ORDER BY a.date DESC, a.punch_in_time ASC";
$res = $db->query($query);

if (isset($_POST['add_manual_attendance'])) {
    $role = $_POST['role'];
    $user_id = (int)$_POST['user_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?: $_POST['start_date'];
    $status = $_POST['status'];

    // Get user's center
    $center_id = null;
    $stmt = $db->prepare("SELECT location FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($location);
    $stmt->fetch();
    $stmt->close();
    $center = $db->query("SELECT id FROM centers WHERE location='$location'")->fetch_assoc();
    if ($center) $center_id = $center['id'];

    // Insert attendance for each day in range
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );
    foreach ($period as $dt) {
        $date = $dt->format('Y-m-d');
        // Check if already exists
        $exists = $db->query("SELECT id FROM attendance WHERE user_id=$user_id AND date='$date' AND role='$role'")->fetch_assoc();
        if (!$exists) {
            $stmt = $db->prepare("INSERT INTO attendance (user_id, role, center_id, punch_in_time, date, status) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("isiss", $user_id, $role, $center_id, $date, $status);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo '<div class="alert alert-success">Manual attendance added!</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Attendance Report - Habits365Club</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="js/select2.min.css">
    <style>
        .info-card {
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.07);
            text-align: center;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        .info-card h5 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        .info-card h3 {
            margin: 5px 0 0;
            font-size: 22px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title mb-4">Attendance Report</h2>
            <div class="card shadow mb-4">
                <div class="card-header">
                    <form method="get" class="form-inline">
                        <label class="mr-2">Center:
                            <select name="center_id" class="form-control ml-2">
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
                        <label class="ml-3 mr-2">From:
                            <input type="date" name="from_date" class="form-control ml-2" value="<?php echo htmlspecialchars($_GET['from_date'] ?? ''); ?>">
                        </label>
                        <label class="ml-3 mr-2">To:
                            <input type="date" name="to_date" class="form-control ml-2" value="<?php echo htmlspecialchars($_GET['to_date'] ?? ''); ?>">
                        </label>
                        <button type="submit" class="btn btn-primary ml-3">Filter</button>
                    </form>
                    <button class="btn btn-success mb-3" data-toggle="modal" data-target="#manualAttendanceModal">
                        <i class="fe fe-plus"></i> Add Manual Attendance
                    </button>
                </div>
                <div class="card-body">
                    <table id="attendanceTable" class="table table-striped table-bordered datatable" style="width:100%">
                        <thead>
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
                        </thead>
                        <tbody>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<!-- Manual Attendance Modal -->
<div class="modal fade" id="manualAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="manualAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="post" action="">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="manualAttendanceModalLabel">Add Manual Attendance</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>User Type</label>
            <select name="role" class="form-control" required>
              <option value="incharge">Incharge</option>
              <option value="teacher">Teacher</option>
            </select>
          </div>
          <div class="form-group">
            <label>User</label>
            <select name="user_id" class="form-control select2" required>
              <option value="">Select User</option>
              <?php
              $users = $db->query("SELECT id, full_name, role FROM users WHERE role IN ('incharge','teacher')");
              while ($u = $users->fetch_assoc()) {
                  echo "<option value='{$u['id']}'>{$u['full_name']} ({$u['role']})</option>";
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label>End Date <small>(leave blank for single day)</small></label>
            <input type="date" name="end_date" class="form-control">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control" required>
              <option value="present">Present</option>
              <option value="late">Late</option>
              <option value="manual">Manual</option>
              <option value="absent">Absent</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="add_manual_attendance" class="btn btn-primary">Add Attendance</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#attendanceTable').DataTable({
            order: [[3, 'desc'], [4, 'asc']],
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ]
        });
        $('.select2').select2({ theme: 'bootstrap4' });
    });
</script>
</body>
</html>