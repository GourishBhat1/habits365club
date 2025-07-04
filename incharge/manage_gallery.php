<?php
// incharge/manage_gallery.php

session_start();
require_once '../connection.php';

// Check if the incharge is authenticated
if (!isset($_SESSION['incharge_username']) && !isset($_COOKIE['incharge_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$incharge_id = $_SESSION['incharge_id'] ?? null;
$current_time = date('Y-m-d H:i:s'); // ✅ Timestamp from PHP

// ✅ Handle Image Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['gallery_image'])) {
    $upload_dir = "../uploads/gallery/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . "_" . basename($_FILES['gallery_image']['name']);
    $file_path = $upload_dir . $file_name;
    $caption = $_POST['caption'] ?? null;

    if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $file_path)) {
        $stmt = $db->prepare("INSERT INTO gallery (image_path, caption, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $file_path, $caption, $incharge_id, $current_time);
        $stmt->execute();
        $stmt->close();
        $success = "Image uploaded successfully!";
    } else {
        $error = "Error uploading image.";
    }
}

// ✅ Handle Image Deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Fetch file path
    $stmt = $db->prepare("SELECT image_path FROM gallery WHERE id = ? AND uploaded_by = ?");
    $stmt->bind_param("ii", $delete_id, $incharge_id);
    $stmt->execute();
    $stmt->bind_result($file_path);
    $stmt->fetch();
    $stmt->close();

    if ($file_path && unlink($file_path)) {
        $stmt = $db->prepare("DELETE FROM gallery WHERE id = ? AND uploaded_by = ?");
        $stmt->bind_param("ii", $delete_id, $incharge_id);
        $stmt->execute();
        $stmt->close();
        $success = "Image deleted successfully!";
    } else {
        $error = "Error deleting image.";
    }
}

// ✅ Fetch Gallery Images
$images = [];
$stmt = $db->prepare("SELECT id, image_path, caption FROM gallery WHERE uploaded_by = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $incharge_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $images[] = $row;
}
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Manage Gallery - Incharge</title>
    <link rel="stylesheet" href="css/app-light.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Manage Gallery</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Image Upload Form -->
            <div class="card shadow mb-4">
                <div class="card-header">Upload Image</div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="gallery_image">Select Image</label>
                            <input type="file" name="gallery_image" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="caption">Caption (optional)</label>
                            <input type="text" name="caption" class="form-control" maxlength="255">
                        </div>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </form>
                    <br>
                    <small class="form-text text-muted">
    🔔 Make sure the image is landscape only, with the student in the centre of the picture. This ensures best fit in the gallery carousel in the parent app.
</small>
                </div>
            </div>

            <!-- Gallery Display -->
            <div class="card shadow">
                <div class="card-header">Gallery</div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($images as $image): ?>
                            <div class="col-md-3">
                                <div class="card mb-3">
                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" class="card-img-top">
                                    <div class="card-body text-center">
                                        <?php if (!empty($image['caption'])): ?>
                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($image['caption']); ?></p>
                                        <?php endif; ?>
                                        <a href="?delete_id=<?php echo $image['id']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this image?')">Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($images)): ?>
                            <p class="text-muted text-center w-100">No images found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>