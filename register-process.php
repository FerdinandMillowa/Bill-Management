<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "Access denied. Admins only.";
    exit;
}

// Database connection
require_once 'db-connection.php';

// Retrieve user input
$username = $_POST['username'];
$password = $_POST['password'];
$role = $_POST['role'];

// Check if username already exists
$sql = "SELECT * FROM users WHERE username = '$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Username already taken.";
} else {
    // Hash the password for security
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user into the database
    $sql = "INSERT INTO users (username, password, role) VALUES ('$username', '$passwordHash', '$role')";
    if ($conn->query($sql) === TRUE) {
        echo "User registered successfully.";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
