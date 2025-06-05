<?php
// admin/manage-centers.php

session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';

$database = new Database();
$db = $database->getConnection();

// Handle Add, Edit, and Delete Actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_center'])) {
        // Add Center
        $location = trim($_POST['center_location']);

        if (!empty($location)) {
            $query = "INSERT INTO centers (location) VALUES (?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("s", $location);
            if ($stmt->execute()) {
                $success = "Center added successfully!";
            } else {
                $error = "Error adding center.";
            }
            $stmt->close();
        } else {
            $error = "Please enter a location.";
        }
    }

    if (isset($_POST['edit_center'])) {
        // Edit Center
        $id = $_POST['edit_center_id'];
        $location = trim($_POST['edit_center_location']);

        if (!empty($id) && !empty($location)) {
            $query = "UPDATE centers SET location = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("si", $location, $id);
            if ($stmt->execute()) {
                $success = "Center updated successfully!";
            } else {
                $error = "Error updating center.";
            }
            $stmt->close();
        } else {
            $error = "Please enter a location.";
        }
    }

    if (isset($_POST['delete_center'])) {
        // Delete Center
        $id = $_POST['delete_center_id'];
        $query = "DELETE FROM centers WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Center deleted successfully!";
        } else {
            $error = "Error deleting center.";
        }
        $stmt->close();
    }

    if (isset($_POST['enable_center']) || isset($_POST['disable_center'])) {
        // Enable or Disable Center
        $id = $_POST['center_id'];
        $status = isset($_POST['enable_center']) ? 'enabled' : 'disabled';

        $query = "UPDATE centers SET status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            $success = "Center " . ($status === 'enabled' ? 'enabled' : 'disabled') . " successfully!";
        } else {
            $error = "Error updating center status.";
        }
        $stmt->close();
    }
}

// Fetch all centers
$centers = [];
$query = "SELECT * FROM centers ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $centers[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Manage Centers - Habits365Club</title>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Manage Centers</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title">All Centers</h5>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addCenterModal">Add New Center</button>
                </div>
                <div class="card-body">
                    <table id="centersTable" class="table table-hover datatable">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($centers as $center): ?>
                            <tr>
                                <td><?php echo $center['id']; ?></td>
                                <td><?php echo htmlspecialchars($center['location']); ?></td>
                                <td>
                                    <?php if ($center['status'] === 'enabled'): ?>
                                        <span class="badge badge-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($center['status'] === 'enabled'): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="center_id" value="<?php echo $center['id']; ?>">
                                            <button type="submit" name="disable_center" class="btn btn-sm btn-warning">Disable</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="center_id" value="<?php echo $center['id']; ?>">
                                            <button type="submit" name="enable_center" class="btn btn-sm btn-success">Enable</button>
                                        </form>
                                    <?php endif; ?>

                                    <button class="btn btn-sm btn-warning edit-center-btn"
                                            data-id="<?php echo $center['id']; ?>"
                                            data-location="<?php echo htmlspecialchars($center['location']); ?>"
                                            data-toggle="modal" data-target="#editCenterModal">Edit</button>

                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="delete_center_id" value="<?php echo $center['id']; ?>">
                                        <button type="submit" name="delete_center" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this center?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Add Center Modal -->
<div class="modal fade" id="addCenterModal" tabindex="-1" role="dialog" aria-labelledby="addCenterLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCenterLabel">Add New Center</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="center_location" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_center" class="btn btn-primary">Add Center</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Center Modal -->
<div class="modal fade" id="editCenterModal" tabindex="-1" role="dialog" aria-labelledby="editCenterLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCenterLabel">Edit Center</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_center_id" id="editCenterId">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="edit_center_location" id="editCenterLocation" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_center" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('.edit-center-btn').click(function() {
    $('#editCenterId').val($(this).data('id'));
    $('#editCenterLocation').val($(this).data('location'));
});
</script>
</body>
</html>