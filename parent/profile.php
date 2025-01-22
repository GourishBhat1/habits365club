<?php
// profile.php

// Start session
session_start();

// Check if the parent is authenticated
if (!isset($_SESSION['parent_email']) && !isset($_COOKIE['parent_email'])) {
    header("Location: index.php");
    exit();
}

// Include database connection or any other required files here
// require 'config.php'; // Example only, adjust to your setup

// Suppose we fetch the parent's details from DB to display in the form
// $parent_name = "John Doe";
// $parent_email = $_SESSION['parent_email'] ?? "john@example.com";

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Parent Dashboard - Profile</title>

  <!-- Including all CSS files (same as in dashboard.php) -->
  <link rel="stylesheet" href="css/simplebar.css">
  <link rel="stylesheet" href="css/feather.css">
  <link rel="stylesheet" href="css/select2.css">
  <link rel="stylesheet" href="css/dropzone.css">
  <link rel="stylesheet" href="css/uppy.min.css">
  <link rel="stylesheet" href="css/jquery.steps.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">
  <link rel="stylesheet" href="css/quill.snow.css">
  <link rel="stylesheet" href="css/daterangepicker.css">
  <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
  <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
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
            <div class="row justify-content-center">
                <div class="col-12 col-md-8">
                    <h2 class="page-title">Profile</h2>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong>Update Your Details</strong>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <!-- Name -->
                                <div class="form-group">
                                    <label for="parent_name">Name</label>
                                    <input type="text" name="parent_name" id="parent_name" class="form-control" 
                                           value="<?php echo 'John Doe'; // replace with dynamic value ?>">
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label for="parent_email">Email</label>
                                    <input type="email" name="parent_email" id="parent_email" class="form-control"
                                           value="<?php echo 'john@example.com'; // replace with dynamic value ?>">
                                </div>

                                <!-- Password -->
                                <div class="form-group">
                                    <label for="parent_password">Password</label>
                                    <input type="password" name="parent_password" id="parent_password" class="form-control"
                                           placeholder="Enter new password if changing">
                                </div>

                                <!-- Submit button -->
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                            <!-- On form submission, handle updating the database, etc. -->
                        </div>
                    </div>
                </div>
            </div> <!-- .row -->
        </div> <!-- .container-fluid -->
    </main>
</div> <!-- .wrapper -->

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
