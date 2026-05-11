<?php
session_start();
$_SESSION = array();
session_destroy();

if (isset($_COOKIE['sales_username'])) {
    setcookie("sales_username", "", time() - 3600, "/", "", false, true);
}

header("Location: index.php?message=logged_out");
exit();
