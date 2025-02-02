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
    private $host = "193.203.184.167";       // Hostname of the MySQL server
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
        // Create a new MySQLi connection
        $this->conn = new mysqli($this->host, $this->userName, $this->password, $this->dbName);

        // Check for connection errors
        if ($this->conn->connect_error){
            die("Connection failed: " . $this->conn->connect_error);
        }

        // Set the character set to UTF-8 for proper encoding
        $this->conn->set_charset("utf8");

        return $this->conn;
    }
}
?>
