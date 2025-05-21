<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

if (!isset($_GET['id'])) {
    echo "No bill ID provided!";
    exit();
}

$bill_id = $_GET['id'];

// Database connection
$conn = new mysqli('localhost', 'root', 'Example@2022#', 'bill_management_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch bill details
$sql = "SELECT b.id, b.bill_name, b.amount, b.description, b.date_time, c.name AS customer_name, c.id AS customer_id 
        FROM bills b
        JOIN customers c ON b.customer_id = c.id
        WHERE b.id = $bill_id";
$bill_result = $conn->query($sql);

if ($bill_result->num_rows == 0) {
    echo "No bill found with this ID!";
    exit();
}

$bill = $bill_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Details</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Bill Details</h2>
    <p><strong>Customer Name:</strong> <?php echo $bill['customer_name']; ?></p>
    <p><strong>Bill Name:</strong> <?php echo $bill['bill_name']; ?></p>
    <p><strong>Amount:</strong> <?php echo $bill['amount']; ?></p>
    <p><strong>Description:</strong> <?php echo $bill['description']; ?></p>
    <p><strong>Date/Time:</strong> <?php echo $bill['date_time']; ?></p>

    <h3>Payments for this Bill</h3>
    <table>
        <tr>
            <th>Payment ID</th>
            <th>Payment Date/Time</th>
            <th>Amount</th>
            <th>Payment Method</th>
        </tr>
        <?php
        // Fetch payments for this customer
        $payments_sql = "SELECT id, date_time, amount, payment_method FROM payments WHERE customer_id = {$bill['customer_id']}";
        $payments_result = $conn->query($payments_sql);

        if ($payments_result->num_rows > 0) {
            while ($payment = $payments_result->fetch_assoc()) {
                echo "<tr>
                    <td>{$payment['id']}</td>
                    <td>{$payment['date_time']}</td>
                    <td>{$payment['amount']}</td>
                    <td>{$payment['payment_method']}</td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No payments found for this bill.</td></tr>";
        }
        ?>
    </table>
</body>

</html>

<?php
$conn->close();
?>