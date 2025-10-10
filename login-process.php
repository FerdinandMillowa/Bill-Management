<?php
session_start();

// Database connection
require_once 'db-connection.php';

// Retrieve user input
$username = $_POST['username'];
$password = $_POST['password'];

// Fetch user from database
$sql = "SELECT * FROM users WHERE username = '$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Start session and set user role
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Redirect based on role
        if ($user['role'] == 'admin') {
            header("Location: admin-dashboard.php");
        } else {
            header("Location: user-dashboard.php");
        }
        exit;
    } else {
        echo "Invalid password.";
    }
} else {
    echo "User not found.";
}

$conn->close();
