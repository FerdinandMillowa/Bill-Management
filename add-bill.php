<?php
// Database connection
$conn = new mysqli('localhost', 'root', 'Example@2022#', 'bill_management_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = isset($_POST['customer_id']) ? $conn->real_escape_string($_POST['customer_id']) : null;
    $bill_name = $conn->real_escape_string($_POST['bill_name']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $description = $conn->real_escape_string($_POST['description']);

    // Check if customer_id is provided
    if (is_null($customer_id) || empty($customer_id)) {
        die("Error: Customer ID is required.");
    }

    // Check if the customer ID exists in the customers table
    $checkCustomerSql = "SELECT id FROM customers WHERE id = '$customer_id'";
    $result = $conn->query($checkCustomerSql);

    if ($result->num_rows === 0) {
        die("Error: Customer ID does not exist.");
    }

    // Insert into bills table
    $sql = "INSERT INTO bills (customer_id, bill_name, amount, description) VALUES ('$customer_id', '$bill_name', '$amount', '$description')";
    if ($conn->query($sql) === TRUE) {
        echo "Bill added successfully.";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
