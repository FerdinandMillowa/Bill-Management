<?php
session_start();

// Database connection
require_once 'db-connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);

    // Hard-coded credentials for demonstration
    $adminUsername = "admin";
    $adminPassword = "password"; // Use a hashed password in production

    if ($username === $adminUsername && $password === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: view-customers.php");
        exit();
    } else {
        echo "Invalid username or password.";
    }
}

$conn->close();
