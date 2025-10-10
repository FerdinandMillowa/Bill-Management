<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

// Fetch all customers for the payment form
$sql = "SELECT id, name FROM customers WHERE approved = 1"; // Only approved customers
$result = $conn->query($sql);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = $conn->real_escape_string($_POST['customer_id']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);

    // Insert the payment record
    $insert_sql = "INSERT INTO payments (customer_id, amount, payment_method) VALUES ('$customer_id', '$amount', '$payment_method')";

    if ($conn->query($insert_sql) === TRUE) {
        echo "Payment recorded successfully.";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Record Payment</h2>
    <form action="record-payment.php" method="POST">
        <label for="customer_id">Select Customer:</label>
        <select id="customer_id" name="customer_id" required>
            <option value="">Select a customer</option>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<option value='{$row['id']}'>{$row['name']}</option>";
                }
            } else {
                echo "<option value=''>No approved customers available</option>";
            }
            ?>
        </select>

        <label for="amount">Payment Amount:</label>
        <input type="number" id="amount" name="amount" required>

        <label for="payment_method">Payment Method:</label>
        <select id="payment_method" name="payment_method" required>
            <option value="cash">Cash</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="bank">Bank</option>
        </select>

        <input type="submit" value="Record Payment">
    </form>
</body>

</html>

<?php
$conn->close();
?>