<?php
// Database connection
require_once 'db-connection.php';

$conn = new mysqli($servername, $username, $password, $dbname);

// Hash password for security
$password = password_hash("admin123", PASSWORD_BCRYPT);

// Insert admin user
$sql = "INSERT INTO users (username, password, role) VALUES ('admin', '$password', 'admin')";

if ($conn->query($sql) === TRUE) {
    echo "Admin user created successfully.";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
