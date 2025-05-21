<?php
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', 'Example@2022#', 'bill_management_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    // Insert customer with status 'pending'
    $sql = "INSERT INTO customers (name, email, phone, address, status) VALUES ('$name', '$email', '$phone', '$address', 'pending')";

    if ($conn->query($sql) === TRUE) {
        echo "Customer added successfully. Waiting for admin approval.";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Add Customer</title>
</head>

<body>
    <h2>Add Customer</h2>
    <form action="add-customer.php" method="post">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br>

        <label for="phone">Phone:</label>
        <input type="text" id="phone" name="phone" required><br>

        <label for="address">Address:</label>
        <textarea id="address" name="address" required></textarea><br>

        <input type="submit" value="Add Customer">
    </form>
</body>

</html>