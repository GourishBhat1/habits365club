<?php
// admin/bulk-upload-parents.php

session_start();
require_once '../connection.php';

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

$upload_success = "";
$error_message = "";
$errors = [];
$uploaded_parents = 0;

// 📌 **Function to Download CSV Template**
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="parent_upload_template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Username', 'Full Name', 'Standard', 'Phone', 'Location', 'Course Name']);
    fclose($output);
    exit();
}

// 📌 **Process CSV Upload**
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        fgetcsv($handle); // Skip header row

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $username = trim($data[0]);
            $full_name = trim($data[1]);
            $standard = trim($data[2] ?? '');
            $phone = trim($data[3]);
            $location = strtoupper(trim($data[4] ?? '')); // Capitalized center name now `location`
            $course_name = trim($data[5] ?? '');

            // Validate required fields
            if (empty($username) || empty($full_name) || empty($phone)) {
                $errors[] = "Skipping row: Missing required fields.";
                continue;
            }

            // Check if username exists
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $errors[] = "Skipping row: Username '$username' already exists.";
                continue;
            }
            $checkStmt->close();

            // 📌 **Auto-Generated Password: First 4 digits of username + First 4 digits of phone**
            $password_part1 = substr($username, 0, 4);
            $password_part2 = substr($phone, 0, 4);
            $password = password_hash($password_part1 . $password_part2, PASSWORD_BCRYPT);

            // Insert into database
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, standard, phone, location, course_name, password, role, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'parent', NOW())");
            $stmt->bind_param("sssssss", $username, $full_name, $standard, $phone, $location, $course_name, $password);

            if ($stmt->execute()) {
                $uploaded_parents++;
            } else {
                $errors[] = "Failed to insert '$username' due to a database error.";
            }
            $stmt->close();
        }
        fclose($handle);
    } else {
        $error_message = "Failed to read the uploaded CSV file.";
    }

    if ($uploaded_parents > 0) {
        $upload_success = "Successfully uploaded $uploaded_parents parents.";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Bulk Parent Upload - Habits365Club</title>
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
</head>
<body class="vertical light">
<div class="wrapper">

    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Bulk Parent Upload</h2>

            <!-- Success/Error Messages -->
            <?php if (!empty($upload_success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($upload_success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header">
                    <strong>Upload CSV File</strong>
                </div>
                <div class="card-body">
                    <form action="bulk-upload-parents.php" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="csv_file">Select CSV File</label>
                            <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload</button>
                        <a href="bulk-upload-parents.php?download_template=1" class="btn btn-success">📥 Download CSV Template</a>
                    </form>

                    <hr>

                    <h5>CSV Format:</h5>
                    <p>Ensure your file has the following columns:</p>
                    <pre>Username, Full Name, Standard, Phone, Location, Course Name</pre>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="card shadow">
                    <div class="card-header">
                        <strong>Errors Found</strong>
                    </div>
                    <div class="card-body">
                        <ul class="text-danger">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
