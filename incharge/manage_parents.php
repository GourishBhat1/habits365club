<?php
session_start();
require_once '../connection.php';

// Handle enable/disable toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['new_status'])) {
    $user_id = (int) $_POST['user_id'];
    $new_status = ($_POST['new_status'] === 'active') ? 'active' : 'inactive';

    $update = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $update->bind_param("si", $new_status, $user_id);
    $update->execute();
    $update->close();

    header("Location: manage_parents.php");
    exit();
}

$incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'] ?? '';

$stmt = $conn->prepare("SELECT location FROM users WHERE username = ? AND role = 'incharge'");
$stmt->bind_param("s", $incharge_username);
$stmt->execute();
$location = $stmt->get_result()->fetch_assoc()['location'];
$stmt->close();

$stmt = $conn->prepare("SELECT id, full_name, username, email, status FROM users WHERE role = 'parent' AND location = ?");
$stmt->bind_param("s", $location);
$stmt->execute();
$parents = $stmt->get_result();
?>

<!doctype html>
<html lang="en">
<head>
  <?php include 'includes/header.php'; ?>
  <title>Manage Parent Users - Incharge</title>
  <link rel="stylesheet" href="../admin/css/dataTables.bootstrap4.css">
</head>
<body class="vertical light">
<div class="wrapper">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main role="main" class="main-content">
    <div class="container-fluid">
      <h2 class="page-title">Manage Parent Users</h2>

      <div class="card shadow">
        <div class="card-body">
          <table class="table datatables" id="parentTable">
            <thead>
              <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
                <th>Toggle</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $parents->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td>
                  <span class="badge badge-<?php echo $row['status'] === 'active' ? 'success' : 'secondary'; ?>">
                    <?php echo ucfirst($row['status']); ?>
                  </span>
                </td>
                <td>
                  <form method="POST" onsubmit="return confirm('Are you sure?');">
                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="new_status" value="<?php echo $row['status'] === 'active' ? 'inactive' : 'active'; ?>">
                    <button class="btn btn-sm btn-<?php echo $row['status'] === 'active' ? 'danger' : 'success'; ?>">
                      <?php echo $row['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                    </button>
                  </form>
                </td>
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
<script src="../admin/js/jquery.min.js"></script>
<script src="../admin/js/bootstrap.bundle.min.js"></script>
<script src="../admin/js/jquery.dataTables.min.js"></script>
<script src="../admin/js/dataTables.bootstrap4.min.js"></script>
<script>
  $(document).ready(function() {
    $('#parentTable').DataTable();
  });
</script>
</body>
</html>