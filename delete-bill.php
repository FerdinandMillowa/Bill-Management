<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', 'Example@2022#', 'bill_management_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $bill_id = $conn->real_escape_string($_GET['id']);

    // Delete the bill
    $sql = "DELETE FROM bills WHERE id = '$bill_id'";

    if ($conn->query($sql) === TRUE) {
        echo "Bill deleted successfully.";
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    echo "Invalid bill ID.";
}

$conn->close();
