<?php
// Database connection
$conn = new mysqli('localhost', 'root', 'Example@2022#', 'bill_management_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = $conn->real_escape_string($_POST['customer_id']);
    $bill_id = $conn->real_escape_string($_POST['bill_id']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);

    // Insert into payments table
    $sql = "INSERT INTO payments (customer_id, bill_id, amount, payment_method) VALUES ('$customer_id', '$bill_id', '$amount', '$payment_method')";
    if ($conn->query($sql) === TRUE) {
        echo "Payment recorded successfully.";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
