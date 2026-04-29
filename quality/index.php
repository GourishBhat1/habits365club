<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connection.php';

$error = '';

// If already logged in
if (isset($_COOKIE['quality_username'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "❌ Please fill in both username and password.";
    } else {

        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            die("❌ Database connection failed: " . mysqli_connect_error());
        }

        // Fetch quality user
        $stmt = $db->prepare("
            SELECT id, username, password 
            FROM users 
            WHERE username = ? AND role = 'quality'
        ");

        if (!$stmt) {
            die("❌ SQL Error: " . $db->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {

            $stmt->bind_result($id, $db_username, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {

                // Cookie (30 days)
                setcookie("quality_username", $db_username, time() + (30 * 24 * 60 * 60), "/", "", false, true);

                // Session
                $_SESSION['quality_username'] = $db_username;
                $_SESSION['quality_id'] = $id;

                header("Location: dashboard.php");
                exit();

            } else {
                $error = "❌ Invalid username or password.";
            }

        } else {
            $error = "❌ Invalid username or password.";
        }

        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quality Login</title>

    <link rel="stylesheet" href="css/app-light.css">
</head>

<body class="light">

<div class="wrapper vh-100">
<div class="row align-items-center h-100">

<div class="col-lg-3 col-md-4 col-10 mx-auto text-center">

    <img src="../assets/images/habits_logo.png" style="max-width:180px;margin-bottom:20px;">

    <form method="POST">

        <h1 class="h6 mb-3">Quality Login</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control form-control-lg" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control form-control-lg" required>
        </div>

        <button class="btn btn-lg btn-primary btn-block">Login</button>

    </form>

</div>
</div>
</div>

</body>
</html>
