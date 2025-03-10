<?php
// ✅ Set PHP Timezone to IST
date_default_timezone_set("Asia/Kolkata");

// ✅ Define Database Credentials as Constants
define("DB_HOST", "srv1666.hstgr.io");
define("DB_NAME", "u606682085_habits_app");
define("DB_USER", "u606682085_habits_app");
define("DB_PASS", "iW#3pZD2!!I}"); 

class Database {
    // Connection object
    public $conn;

    /**
     * Establishes a database connection with retry logic.
     * 
     * @return mysqli The MySQLi connection object
     */
    public function getConnection(){
        // Enable error reporting (for debugging)
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $maxRetries = 3;
        $attempts = 0;
        $retryDelay = 5; // Wait time before retrying (in seconds)

        while ($attempts < $maxRetries) {
            try {
                // ✅ Create a new MySQLi connection (NO persistent connection)
                $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

                // ✅ Set MySQL Timezone to IST (UTC+5:30)
                $this->conn->query("SET time_zone = '+05:30'");

                // ✅ Set UTF-8 Character Encoding
                $this->conn->set_charset("utf8mb4");

                // ✅ Enable Strict Mode
                $this->conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

                return $this->conn;
            } catch (mysqli_sql_exception $e) {
                // Retry on "Too Many Connections" Error
                if (strpos($e->getMessage(), 'max_connections_per_hour') !== false || 
                    strpos($e->getMessage(), 'Too many connections') !== false) {
                    sleep($retryDelay);
                    $attempts++;
                } else {
                    die("❌ Database Connection Failed: " . $e->getMessage());
                }
            }
        }

        die("❌ Max Connection Attempts Exceeded. Try again later.");
    }
}

// ✅ Start session (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Create a database connection instance
$database = new Database();
$conn = $database->getConnection();

/**
 * Function to check user status and enforce logout for inactive users
 */
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

// ✅ **Check Status for Admin**
if (isset($_SESSION['admin_email']) || isset($_COOKIE['admin_email'])) {
    $admin_email = $_SESSION['admin_email'] ?? $_COOKIE['admin_email'];
    checkUserStatus($conn, 'email', $admin_email, 'admin');
}

// ✅ **Check Status for Teacher**
if (isset($_SESSION['teacher_email']) || isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_SESSION['teacher_email'] ?? $_COOKIE['teacher_email'];
    checkUserStatus($conn, 'email', $teacher_email, 'teacher');
}

// ✅ **Check Status for Parent**
if (isset($_SESSION['parent_username']) || isset($_COOKIE['parent_username'])) {
    $parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];
    checkUserStatus($conn, 'username', $parent_username, 'parent');
}

// ✅ **Check Status for Incharge**
if (isset($_SESSION['incharge_username']) || isset($_COOKIE['incharge_username'])) {
    $incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];
    checkUserStatus($conn, 'username', $incharge_username, 'incharge');
}
?>