<?php
// ✅ Set PHP Timezone to IST
date_default_timezone_set("Asia/Kolkata");

// ✅ Define CDN URL as a constant
define("CDN_URL", "https://habits-storage.blr1.digitaloceanspaces.com/");

// ✅ Define Database Credentials as Constants (dynamic for local/server)
if (
    $_SERVER['SERVER_ADDR'] === '127.0.0.1' ||
    $_SERVER['SERVER_ADDR'] === '::1' ||
    (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false)
) {
    // Localhost settings
    define("DB_HOST", "157.245.100.199");
    define("DB_NAME", "habits365");
    define("DB_USER", "habitsuser");
    define("DB_PASS", "6LPiwrat?ub830TroHar");
} else {
    // Production server settings
    define("DB_HOST", "localhost");
    define("DB_NAME", "habits365");
    define("DB_USER", "habitsuser");
    define("DB_PASS", "6LPiwrat?ub830TroHar");
}

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

                $this->conn->query("SET SESSION wait_timeout = 30");  // Auto-close idle connections in 60s
                $this->conn->query("SET SESSION interactive_timeout = 30");  // Idle sessions timeout

                return $this->conn;
            } catch (mysqli_sql_exception $e) {
                // Retry on "Too Many Connections" Error
                if (strpos($e->getMessage(), 'max_connections_per_hour') !== false || 
                    strpos($e->getMessage(), 'Too many connections') !== false || 
                    strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
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
    // ✅ Check if connection is still alive
    if (!$conn->ping()) {
        $database = new Database();
        $conn = $database->getConnection(); // Reconnect
    }

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

    return $conn;
}

// ✅ **Check Status for Admin**
if (isset($_SESSION['admin_email']) || isset($_COOKIE['admin_email'])) {
    $admin_email = $_SESSION['admin_email'] ?? $_COOKIE['admin_email'];
    $conn = checkUserStatus($conn, 'email', $admin_email, 'admin');
}

// ✅ **Check Status for Teacher**
if (isset($_SESSION['teacher_email']) || isset($_COOKIE['teacher_email'])) {
    $teacher_email = $_SESSION['teacher_email'] ?? $_COOKIE['teacher_email'];
    $conn = checkUserStatus($conn, 'email', $teacher_email, 'teacher');
}

// ✅ **Check Status for Parent**
if (isset($_SESSION['parent_username']) || isset($_COOKIE['parent_username'])) {
    $parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];
    $conn = checkUserStatus($conn, 'username', $parent_username, 'parent');
}

// ✅ **Check Status for Incharge**
if (isset($_SESSION['incharge_username']) || isset($_COOKIE['incharge_username'])) {
    $incharge_username = $_SESSION['incharge_username'] ?? $_COOKIE['incharge_username'];
    $conn = checkUserStatus($conn, 'username', $incharge_username, 'incharge');
}
?>