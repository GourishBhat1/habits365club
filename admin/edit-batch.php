<?php  
// admin/edit-batch.php  

// Start session  
session_start();  

// Check if the admin is authenticated  
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {  
    header("Location: index.php");  
    exit();  
}  

// Get batch ID from GET parameter  
$batch_id = $_GET['id'] ?? '';  

if (empty($batch_id)) {  
    header("Location: batch-management.php");  
    exit();  
}  

require_once '../connection.php';  

// Initialize variables  
$error = '';  
$success = '';  

// Establish database connection  
$database = new Database();  
$db = $database->getConnection();  

// Fetch batch details  
$query = "SELECT id, name, teacher_id, incharge_id FROM batches WHERE id = ?";  
$stmt = $db->prepare($query);  
$stmt->bind_param("i", $batch_id);  
$stmt->execute();  
$result = $stmt->get_result();  

if ($result->num_rows !== 1) {  
    header("Location: batch-management.php");  
    exit();  
}  

$batch = $result->fetch_assoc();  

// Fetch all teachers for assignment  
$teachers = [];  
$teacherQuery = "SELECT id, username FROM users WHERE role = 'teacher'";  
$teacherStmt = $db->prepare($teacherQuery);  
$teacherStmt->execute();  
$teacherResult = $teacherStmt->get_result();  
while ($row = $teacherResult->fetch_assoc()) {  
    $teachers[] = $row;  
}  
$teacherStmt->close();  

// Fetch all incharges for assignment  
$incharges = [];  
$inchargeQuery = "SELECT id, username FROM users WHERE role = 'incharge'";  
$inchargeStmt = $db->prepare($inchargeQuery);  
$inchargeStmt->execute();  
$inchargeResult = $inchargeStmt->get_result();  
while ($row = $inchargeResult->fetch_assoc()) {  
    $incharges[] = $row;  
}  
$inchargeStmt->close();  

// Fetch parents currently assigned to this batch  
$assignedParents = [];  
$parentQuery = "SELECT id, username, full_name FROM users WHERE role = 'parent' AND batch_id = ?";  
$parentStmt = $db->prepare($parentQuery);  
$parentStmt->bind_param("i", $batch_id);  
$parentStmt->execute();  
$parentResult = $parentStmt->get_result();  
while ($row = $parentResult->fetch_assoc()) {  
    $assignedParents[] = $row;  
}  
$parentStmt->close();  

// Fetch parents NOT assigned to any batch  
$unassignedParents = [];  
$unassignedParentQuery = "SELECT id, username, full_name FROM users WHERE role = 'parent' AND batch_id IS NULL";  
$unassignedParentStmt = $db->prepare($unassignedParentQuery);  
$unassignedParentStmt->execute();  
$unassignedParentResult = $unassignedParentStmt->get_result();  
while ($row = $unassignedParentResult->fetch_assoc()) {  
    $unassignedParents[] = $row;  
}  
$unassignedParentStmt->close();  

// Handle form submission  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    $batch_name = trim($_POST['batch_name'] ?? '');  
    $teacher_id = $_POST['teacher_id'] ?? null;  
    $incharge_id = $_POST['incharge_id'] ?? null;  // Added incharge selection  
    $parent_ids = $_POST['parent_ids'] ?? [];  

    // Validation  
    if (empty($batch_name)) {  
        $error = "Please enter a batch name.";  
    } else {  
        // Check if batch name already exists (excluding current batch)  
        $checkQuery = "SELECT id FROM batches WHERE name = ? AND id != ?";  
        $checkStmt = $db->prepare($checkQuery);  
        $checkStmt->bind_param("si", $batch_name, $batch_id);  
        $checkStmt->execute();  
        $checkStmt->store_result();  

        if ($checkStmt->num_rows > 0) {  
            $error = "Batch name already exists.";  
        } else {  
            // Update batch details  
            $updateQuery = "UPDATE batches SET name = ?, teacher_id = ?, incharge_id = ? WHERE id = ?";  
            $updateStmt = $db->prepare($updateQuery);  
            $updateStmt->bind_param("siii", $batch_name, $teacher_id, $incharge_id, $batch_id);  

            if ($updateStmt->execute()) {  
                // Remove existing parent assignments from this batch  
                $clearParentsQuery = "UPDATE users SET batch_id = NULL WHERE batch_id = ?";  
                $clearParentsStmt = $db->prepare($clearParentsQuery);  
                $clearParentsStmt->bind_param("i", $batch_id);  
                $clearParentsStmt->execute();  
                $clearParentsStmt->close();  

                // Assign new parents to this batch  
                if (!empty($parent_ids)) {  
                    foreach ($parent_ids as $parent_id) {  
                        $assignParentQuery = "UPDATE users SET batch_id = ? WHERE id = ?";  
                        $assignParentStmt = $db->prepare($assignParentQuery);  
                        $assignParentStmt->bind_param("ii", $batch_id, $parent_id);  
                        $assignParentStmt->execute();  
                        $assignParentStmt->close();  
                    }  
                }  

                header("Location: batch-management.php?success=Batch updated successfully.");  
                exit();  
            } else {  
                $error = "An error occurred. Please try again.";  
            }  
            $updateStmt->close();  
        }  
        $checkStmt->close();  
    }  
}  
?>  

<!doctype html>  
<html lang="en">  
<head>  
    <?php include 'includes/header.php'; ?>  
    <title>Edit Batch - Habits Web App</title>  
    <link rel="stylesheet" href="css/select2.min.css">  
    <style>  
        .alert {  
            padding: 15px;  
            margin-bottom: 20px;  
            border: 1px solid transparent;  
            border-radius: 4px;  
        }  
        .alert-danger {  
            color: #a94442;  
            background-color: #f2dede;  
            border-color: #ebccd1;  
        }  
        .alert-success {  
            color: #3c763d;  
            background-color: #dff0d8;  
            border-color: #d6e9c6;  
        }  
    </style>  
</head>  
<body class="vertical light">  
<div class="wrapper">  
    <?php include 'includes/navbar.php'; ?>  
    <?php include 'includes/sidebar.php'; ?>  

    <main role="main" class="main-content">  
        <div class="container-fluid">  
            <h2 class="page-title">Edit Batch</h2>  
            <div class="card shadow">  
                <div class="card-header">  
                    <h5 class="card-title">Batch Details</h5>  
                </div>  
                <div class="card-body">  
                    <?php if (!empty($error)): ?>  
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>  
                    <?php endif; ?>  
                    
                    <form action="edit-batch.php?id=<?php echo $batch_id; ?>" method="POST" class="needs-validation" novalidate>  
                        <div class="form-group">  
                            <label for="batch_name">Batch Name <span class="text-danger">*</span></label>  
                            <input type="text" id="batch_name" name="batch_name" class="form-control" value="<?php echo htmlspecialchars($batch['name']); ?>" required>  
                        </div>  
                        <div class="form-group">  
                            <label for="teacher_id">Assign Teacher (Optional)</label>  
                            <select id="teacher_id" name="teacher_id" class="form-control select2"> 
                            <option value="">No Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>  
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($batch['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>  
                                        <?php echo htmlspecialchars($teacher['username']); ?>  
                                    </option>  
                                <?php endforeach; ?>  
                            </select>  
                        </div>  
                        <div class="form-group">  
                            <label for="incharge_id">Assign Incharge (Optional)</label>  
                            <select id="incharge_id" name="incharge_id" class="form-control select2"> 
                            <option value="">No Incharge</option>
                                <?php foreach ($incharges as $incharge): ?>  
                                    <option value="<?php echo $incharge['id']; ?>" <?php echo ($batch['incharge_id'] == $incharge['id']) ? 'selected' : ''; ?>>  
                                        <?php echo htmlspecialchars($incharge['username']); ?>  
                                    </option>  
                                <?php endforeach; ?>  
                            </select>  
                        </div>  
                        <div class="form-group">  
                            <label for="parent_ids">Assign Parents to Batch</label>  
                            <select id="parent_ids" name="parent_ids[]" class="form-control select2" multiple>  
                                <?php foreach ($assignedParents as $parent): ?>  
                                    <option value="<?php echo $parent['id']; ?>" selected><?php echo htmlspecialchars($parent['full_name']); ?></option>  
                                <?php endforeach; ?>  
                                <?php foreach ($unassignedParents as $parent): ?>  
                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['full_name']); ?></option>  
                                <?php endforeach; ?>  
                            </select>  
                        </div>  
                        <button type="submit" class="btn btn-primary">Update Batch</button>  
                        <a href="batch-management.php" class="btn btn-secondary">Cancel</a>  
                    </form>  
                </div>  
            </div>  
        </div>  
    </main>  
</div>  

<?php include 'includes/footer.php'; ?>  

<script src="js/select2.min.js"></script>  
<script>  
    $(document).ready(function () {  
        $('.select2').select2({ theme: 'bootstrap4' });  
    });  
</script>  
</body>  
</html>
