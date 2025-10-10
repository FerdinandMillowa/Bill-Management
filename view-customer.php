<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

if (!isset($_GET['id'])) {
    echo "No customer ID provided!";
    exit();
}

$customer_id = $_GET['id'];

// Database connection
require_once 'db-connection.php';

// Fetch customer details
$customer_sql = "SELECT * FROM customers WHERE id = $customer_id";
$customer_result = $conn->query($customer_sql);

if ($customer_result->num_rows == 0) {
    echo "No customer found with this ID!";
    exit();
}

$customer = $customer_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Customer Details</h2>
    <p><strong>Name:</strong> <?php echo $customer['name']; ?></p>
    <p><strong>Email:</strong> <?php echo $customer['email']; ?></p>
    <p><strong>Phone:</strong> <?php echo $customer['phone']; ?></p>

    <?php
    // Fetch total bills and total payments for the customer
    $total_bills_sql = "SELECT SUM(amount) AS total_bills FROM bills WHERE customer_id = $customer_id";
    $total_bills_result = $conn->query($total_bills_sql);
    $total_bills = ($total_bills_result->num_rows > 0) ? $total_bills_result->fetch_assoc()['total_bills'] : 0;

    $total_payments_sql = "SELECT SUM(amount) AS total_payments FROM payments WHERE customer_id = $customer_id";
    $total_payments_result = $conn->query($total_payments_sql);
    $total_payments = ($total_payments_result->num_rows > 0) ? $total_payments_result->fetch_assoc()['total_payments'] : 0;

    // Calculate outstanding balance
    $outstanding_balance = $total_bills - $total_payments;
    ?>

    <h3>Customer Summary</h3>
    <p><strong>Total Bills:</strong> <?php echo $total_bills; ?></p>
    <p><strong>Total Payments:</strong> <?php echo $total_payments; ?></p>
    <?php
    $balance_color = ($outstanding_balance == 0) ? "green" : "red";
    ?>
    <p><strong>Outstanding Balance:</strong> <span style="color: <?php echo $balance_color; ?>;"><?php echo $outstanding_balance; ?></span></p>

    <p><strong>Outstanding Balance:</strong> <?php echo $outstanding_balance; ?></p>


    <h3>Bills for this Customer</h3>
    <table>
        <tr>
            <th>Bill ID</th>
            <th>Bill Name</th>
            <th>Amount</th>
            <th>Description</th>
            <th>Date/Time</th>
        </tr>
        <?php
        // Fetch bills for this customer
        $bills_sql = "SELECT * FROM bills WHERE customer_id = $customer_id";
        $bills_result = $conn->query($bills_sql);

        if ($bills_result->num_rows > 0) {
            while ($bill = $bills_result->fetch_assoc()) {
                echo "<tr>
                    <td>{$bill['id']}</td>
                    <td>{$bill['bill_name']}</td>
                    <td>{$bill['amount']}</td>
                    <td>{$bill['description']}</td>
                    <td>{$bill['date_time']}</td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No bills found for this customer.</td></tr>";
        }
        ?>
    </table>

    <h3>Payments for this Customer</h3>
    <table>
        <tr>
            <th>Payment ID</th>
            <th>Date/Time</th>
            <th>Amount</th>
            <th>Payment Method</th>
        </tr>
        <?php
        // Fetch payments for this customer
        $payments_sql = "SELECT * FROM payments WHERE customer_id = $customer_id";
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
            echo "<tr><td colspan='4'>No payments found for this customer.</td></tr>";
        }
        ?>
    </table>
</body>

</html>

<?php
$conn->close();
?>