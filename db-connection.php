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

            // Set charset to utf8
            $this->connection->set_charset("utf8");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
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

// Alternative simple connection (if you don't need OOP)
function getSimpleConnection()
{
    $servername = "localhost";
    $username = "root";
    $password = "123";
    $dbname = "your_database_name";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Create a global connection instance (optional)
$database = new Database();
$conn = $database->getConnection();
