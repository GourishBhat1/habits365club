<?php
// config/connection.php

/**
 * Database Connection Class
 *
 * This class establishes a connection to the MySQL database using MySQLi.
 * It provides a method to retrieve the connection object for use in other parts of the application.
 */

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
?>
