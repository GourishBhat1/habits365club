<?php
// config/connection.php

/**
 * Database Connection Class
 *
 * This class establishes a connection to the MySQL database using MySQLi.
 * It provides a method to retrieve the connection object for use in other parts of the application.
 */

date_default_timezone_set("Asia/Kolkata");   // India time (GMT+5:30)

class Database {
    // Database configuration parameters
    private $host = "srv1666.hstgr.io";       // Hostname of the MySQL server
    private $dbName = "u606682085_habits_app";    // Name of the database
    private $userName = "u606682085_habits_app";  // MySQL username
    private $password = "iW#3pZD2!!I}"; // MySQL password

    // Connection object
    public $conn;

    /**
     * Establishes a database connection
     *
     * @return mysqli The MySQLi connection object
     */
    public function getConnection(){
        // Enable error reporting (for debugging)
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            // Create a new MySQLi connection
            $this->conn = new mysqli($this->host, $this->userName, $this->password, $this->dbName);

            // Set the character set to UTF-8 for proper encoding
            $this->conn->set_charset("utf8");

            // Enable strict mode to catch all errors
            $this->conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

            return $this->conn;
        } catch (mysqli_sql_exception $e) {
            // Output SQL error with detailed message
            die("❌ Database Connection Failed: " . $e->getMessage());
        }
    }
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create a database connection instance
$database = new Database();
$conn = $database->getConnection();

// Function to check user status
function checkUserStatus($conn, $user_column, $user_value, $role) {
    $stmt = $conn->prepare("SELECT status FROM users WHERE $user_column = ? AND role = ?");
    $stmt->bind_param("ss", $user_value, $role);
    $stmt->execute();
    $stmt->bind_result($status);
    $stmt->fetch();
    $stmt->close();

    // If user is inactive, force logout
    if ($status === 'inactive') {
        session_destroy();
        setcookie("{$role}_username", "", time() - 3600, "/");
        setcookie("{$role}_email", "", time() - 3600, "/");
        header("Location: index.php");
        exit();
    }
}

// Check status for Admin
if (isset($_SESSION['admin_email']) || isset($_COOKIE['admin_email'])) {
    $admin_email = $_SESSION['admin_email'] ?? $_COOKIE['admin_email'];
    checkUserStatus($conn, 'email', $admin_email, 'admin');
}

// Check status for Teacher
if (isset($_SESSION['teacher_email']) || isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_SESSION['teacher_email'] ?? $_COOKIE['teacher_email'];
    checkUserStatus($conn, 'email', $teacher_email, 'teacher');
}

// Check status for Parent
if (isset($_SESSION['parent_username']) || isset($_COOKIE['parent_username'])) {
    $parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];
    checkUserStatus($conn, 'username', $parent_username, 'parent');
}
?>
