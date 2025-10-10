<?php
// Database connection
require_once 'db-connection.php';

// Hash password for security
$plain_password = "Admin123!"; // Change this to a strong password
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
$username = "admin";
$role = "admin";

// Check if admin already exists
$check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check_stmt->bind_param("s", $username);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo "Admin user already exists.";
} else {
    // Insert admin user with prepared statement
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashed_password, $role);

    if ($stmt->execute()) {
        echo "Admin user created successfully.<br>";
        echo "Username: admin<br>";
        echo "Password: Admin123!<br>";
        echo "<strong>Change this password immediately after first login!</strong>";
    } else {
        echo "Error creating admin user: " . $conn->error;
    }
    $stmt->close();
}

$check_stmt->close();
$conn->close();
