<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "Example@2022#";
$dbname = "bill_management_system";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
