<?php
class Database
{
    private $host = "localhost";
    private $username = "root";
    private $password = "123";
    private $database = "bill_management_system";
    private $connection;

    public function __construct()
    {
        try {
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);

            // Check connection
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }

            // Set charset to utf8mb4 for better Unicode support
            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }

    // Get the connection object
    public function getConnection()
    {
        return $this->connection;
    }

    // Close connection
    public function closeConnection()
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Create a global connection instance
try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    error_log("Failed to create database connection: " . $e->getMessage());
    die("System initialization failed. Please contact administrator.");
}
