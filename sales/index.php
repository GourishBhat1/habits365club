<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../connection.php';

$error = '';

if (isset($_COOKIE['sales_username'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ? AND role = 'sales'");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $db_username, $hashed_password);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    setcookie("sales_username", $db_username, time() + (30 * 24 * 60 * 60), "/", "", false, true);
                    $_SESSION['sales_username'] = $db_username;
                    $_SESSION['sales_id'] = $id;

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or password.";
            }
            $stmt->close();
        } else {
            $error = "SQL Error: Unable to prepare statement.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Login - Habits365Club</title>
    <link rel="stylesheet" href="css/app-light.css">
</head>
<body class="light">
<div class="wrapper vh-100">
<div class="row align-items-center h-100">
<div class="col-lg-3 col-md-4 col-10 mx-auto text-center">

    <img src="../assets/images/habits_logo.png" style="max-width:180px;margin-bottom:20px;">

    <form method="POST">
        <h1 class="h6 mb-3">Sales Login</h1>

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
