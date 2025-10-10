<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "Access denied. Admins only.";
    exit;
}

// Database connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

// Retrieve and sanitize user input
$username = secureFormInput($_POST['username']);
$password = $_POST['password'];
$role = secureFormInput($_POST['role']);

try {
    // Validate CSRF token
    validateFormToken();

    // Validate inputs
    if (empty($username) || empty($password) || empty($role)) {
        throw new Exception("All fields are required.");
    }

    if (strlen($password) < 8) {
        throw new Exception("Password must be at least 8 characters long.");
    }

    // Check if username already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception("Username already taken.");
    }
    $check_stmt->close();

    // Hash the password for security
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into the database
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $passwordHash, $role);

    if ($stmt->execute()) {
        echo "User registered successfully. <a href='view-users.php'>View Users</a>";
    } else {
        throw new Exception("Error registering user: " . $conn->error);
    }

    $stmt->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . " <a href='register.php'>Go Back</a>";
}

$conn->close();
