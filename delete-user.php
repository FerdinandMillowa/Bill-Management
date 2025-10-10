<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'db-connection.php';

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from URL
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    // Delete user from the database
    $sql = "DELETE FROM users WHERE id = '$user_id'";

    if ($conn->query($sql) === TRUE) {
        echo "User deleted successfully.";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
} else {
    echo "Invalid request.";
}

$conn->close();
?>

<a href="view-users.php">Back to Users</a>