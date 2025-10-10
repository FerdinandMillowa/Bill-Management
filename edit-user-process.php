<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'db-connection.php';

// Retrieve user input
$user_id = $_POST['id'];
$username = $_POST['username'];
$role = $_POST['role'];

// Update user in the database
$sql = "UPDATE users SET username = '$username', role = '$role' WHERE id = '$user_id'";

if ($conn->query($sql) === TRUE) {
    echo "User updated successfully.";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>

<a href="view-users.php">Back to Users</a>