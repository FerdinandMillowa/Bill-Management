<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

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
