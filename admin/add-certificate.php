<?php
// admin/add-certificate.php

// Start session
session_start();

// Check if the admin is authenticated
if (!isset($_SESSION['admin_email']) && !isset($_COOKIE['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Include the database connection
require_once __DIR__ . '/../connection.php';

// Instantiate the Database class and get the connection
$database = new Database();
$db = $database->getConnection();

// Initialize variables
$error = '';
$success = '';

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $user_id = trim($_POST['user_id'] ?? '');
        $milestone = trim($_POST['milestone'] ?? '');

        // Basic validation
        if (empty($user_id) || empty($milestone)) {
            $error = "Please fill in all required fields.";
        } else {
            // Fetch user details
            $userQuery = "SELECT username FROM users WHERE id = ?";
            $userStmt = $db->prepare($userQuery);
            if ($userStmt) {
                $userStmt->bind_param("i", $user_id);
                $userStmt->execute();
                $userResult = $userStmt->get_result();

                if ($userResult->num_rows === 1) {
                    $user = $userResult->fetch_assoc();
                    $username = $user['username'];
                } else {
                    $error = "Selected user does not exist.";
                }
                $userStmt->close();
            } else {
                $error = "Database error: " . $db->error;
            }

            if (empty($error)) {
                // Generate the certificate image
                $certificatePath = generateCertificateImage($username, $milestone);

                if ($certificatePath) {
                    // Insert into certificates table
                    $insertQuery = "INSERT INTO certificates (user_id, milestone, certificate_path) VALUES (?, ?, ?)";
                    $insertStmt = $db->prepare($insertQuery);
                    if ($insertStmt) {
                        $insertStmt->bind_param("iss", $user_id, $milestone, $certificatePath);
                        if ($insertStmt->execute()) {
                            $success = "Certificate generated successfully.";
                        } else {
                            $error = "Failed to save certificate to the database: " . $insertStmt->error;
                            // Optionally, delete the generated image if DB insert fails
                            unlink(__DIR__ . '/../' . $certificatePath);
                        }
                        $insertStmt->close();
                    } else {
                        $error = "Database error: " . $db->error;
                        // Optionally, delete the generated image if DB prepare fails
                        unlink(__DIR__ . '/../' . $certificatePath);
                    }
                } else {
                    $error = "Failed to generate certificate image.";
                }
            }
        }
    }
}

/**
 * Function to generate certificate image
 * @param string $username
 * @param string $milestone
 * @return string|false Path to the generated certificate image or false on failure
 */
function generateCertificateImage($username, $milestone) {
    // Define image dimensions
    $width = 1200; // in pixels
    $height = 900; // in pixels

    // Create a blank image
    $image = imagecreatetruecolor($width, $height);

    if (!$image) {
        return false;
    }

    // Define colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gold = imagecolorallocate($image, 212, 175, 55); // For decorative elements

    // Fill the background with white
    imagefilledrectangle($image, 0, 0, $width, $height, $white);

    // Optional: Add border
    imagerectangle($image, 10, 10, $width - 10, $height - 10, $black);

    // Add title
    $titleFont = __DIR__ . '/../fonts/Roboto-Bold.ttf';
    if (!file_exists($titleFont)) {
        $titleFont = __DIR__ . '/../fonts/Arial.ttf'; // Fallback
        if (!file_exists($titleFont)) {
            error_log("Font file not found: Roboto-Bold.ttf or Arial.ttf");
            return false;
        }
    }
    imagettftext($image, 50, 0, 100, 150, $black, $titleFont, 'Certificate of Achievement');

    // Add user name
    $nameFont = __DIR__ . '/../fonts/Roboto-Bold.ttf';
    if (!file_exists($nameFont)) {
        $nameFont = __DIR__ . '/../fonts/Arial.ttf'; // Fallback
        if (!file_exists($nameFont)) {
            error_log("Font file not found: Roboto-Bold.ttf or Arial.ttf");
            return false;
        }
    }
    // Center the text
    $text = $username;
    $fontSize = 40;
    $bbox = imagettfbbox($fontSize, 0, $nameFont, $text);
    if (!$bbox) {
        error_log("Failed to calculate bounding box for username.");
        return false;
    }
    $textWidth = $bbox[2] - $bbox[0];
    $x = ($width - $textWidth) / 2;
    imagettftext($image, $fontSize, 0, $x, 300, $black, $nameFont, $text);

    // Add milestone
    $milestoneFont = __DIR__ . '/../fonts/Roboto-Regular.ttf';
    if (!file_exists($milestoneFont)) {
        $milestoneFont = __DIR__ . '/../fonts/Arial.ttf'; // Fallback
        if (!file_exists($milestoneFont)) {
            error_log("Font file not found: Roboto-Regular.ttf or Arial.ttf");
            return false;
        }
    }
    $text = "for achieving the milestone of";
    $fontSize = 30;
    $bbox = imagettfbbox($fontSize, 0, $milestoneFont, $text);
    if (!$bbox) {
        error_log("Failed to calculate bounding box for milestone text.");
        return false;
    }
    $textWidth = $bbox[2] - $bbox[0];
    $x = ($width - $textWidth) / 2;
    imagettftext($image, $fontSize, 0, $x, 350, $black, $milestoneFont, $text);

    // Add milestone achievement
    $milestoneText = $milestone;
    $fontSize = 35;
    $bbox = imagettfbbox($fontSize, 0, $milestoneFont, $milestoneText);
    if (!$bbox) {
        error_log("Failed to calculate bounding box for milestone achievement.");
        return false;
    }
    $textWidth = $bbox[2] - $bbox[0];
    $x = ($width - $textWidth) / 2;
    imagettftext($image, $fontSize, 0, $x, 400, $gold, $milestoneFont, $milestoneText);

    // Add date
    $dateFont = __DIR__ . '/../fonts/Roboto-Regular.ttf';
    if (!file_exists($dateFont)) {
        $dateFont = __DIR__ . '/../fonts/Arial.ttf'; // Fallback
        if (!file_exists($dateFont)) {
            error_log("Font file not found: Roboto-Regular.ttf or Arial.ttf");
            return false;
        }
    }
    $date = date('F j, Y');
    $text = "Date: " . $date;
    $fontSize = 25;
    $bbox = imagettfbbox($fontSize, 0, $dateFont, $text);
    if (!$bbox) {
        error_log("Failed to calculate bounding box for date.");
        return false;
    }
    $textWidth = $bbox[2] - $bbox[0];
    $x = ($width - $textWidth) / 2;
    imagettftext($image, $fontSize, 0, $x, 500, $black, $dateFont, $text);

    // Add signature line
    $signatureFont = __DIR__ . '/../fonts/Roboto-Italic.ttf';
    if (!file_exists($signatureFont)) {
        $signatureFont = __DIR__ . '/../fonts/Arial.ttf'; // Fallback
        if (!file_exists($signatureFont)) {
            error_log("Font file not found: Roboto-Italic.ttf or Arial.ttf");
            return false;
        }
    }
    $signature = "_________________________";
    $fontSize = 30;
    $bbox = imagettfbbox($fontSize, 0, $signatureFont, $signature);
    if (!$bbox) {
        error_log("Failed to calculate bounding box for signature line.");
        return false;
    }
    $textWidth = $bbox[2] - $bbox[0];
    $x = ($width - $textWidth) / 2;
    imagettftext($image, $fontSize, 0, $x, 700, $black, $signatureFont, $signature);

    // Add signature label
    $signatureLabel = "Authorized Signature";
    $fontSize = 20;
    $bbox = imagettfbbox($fontSize, 0, $signatureFont, $signatureLabel);
    if (!$bbox) {
        error_log("Failed to calculate bounding box for signature label.");
        return false;
    }
    $textWidth = $bbox[2] - $bbox[0];
    $x = ($width - $textWidth) / 2;
    imagettftext($image, $fontSize, 0, $x, 750, $black, $signatureFont, $signatureLabel);

    // Define the path to save the image
    $imageName = 'certificate_' . uniqid() . '.png';
    $imagePath = 'certificates/images/' . $imageName;

    // Ensure the directory exists
    if (!file_exists(__DIR__ . '/../certificates/images/')) {
        mkdir(__DIR__ . '/../certificates/images/', 0755, true);
    }

    // Save the image
    $savePath = __DIR__ . '/../' . $imagePath;
    if (!imagepng($image, $savePath)) {
        error_log("Failed to save image to path: " . $savePath);
        imagedestroy($image);
        return false;
    }

    // Free memory
    imagedestroy($image);

    return $imagePath;
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; // Optional ?>
    <title>Add New Certificate - Habits Web App</title>
    <!-- Select2 CSS -->
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
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Generate New Certificate</h2>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title">Certificate Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    <form action="add-certificate.php" method="POST" class="needs-validation" novalidate>
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-group">
                            <label for="user_id">Select User <span class="text-danger">*</span></label>
                            <select id="user_id" name="user_id" class="form-control select2" required>
                                <option value="">Select a User</option>
                                <?php
                                // Fetch all users (parents and teachers)
                                $userQuery = "SELECT id, username FROM users WHERE role = 'parent' OR role = 'teacher'";
                                $userStmt = $db->prepare($userQuery);
                                if ($userStmt) {
                                    $userStmt->execute();
                                    $users = $userStmt->get_result();

                                    if ($users->num_rows > 0) {
                                        while($user = $users->fetch_assoc()):
                                ?>
                                            <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php if(isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </option>
                                <?php
                                        endwhile;
                                    } else {
                                        echo '<option value="">No users available</option>';
                                    }
                                    $userStmt->close();
                                } else {
                                    echo '<option value="">Failed to load users</option>';
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a user.
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="milestone">Milestone <span class="text-danger">*</span></label>
                            <input type="text" id="milestone" name="milestone" class="form-control" required placeholder="e.g., 30-day Streak" value="<?php echo htmlspecialchars($_POST['milestone'] ?? ''); ?>">
                            <div class="invalid-feedback">
                                Please enter a milestone.
                            </div>
                        </div>
                        <!-- Optional: Add more fields as needed -->
                        <button type="submit" class="btn btn-primary">Generate Certificate</button>
                        <a href="certificate-management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>

<!-- Select2 JS -->
<script src="js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: "Select a user"
        });

        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    });
</script>
</body>
</html>
