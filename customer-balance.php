<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

// Fetch all approved customers
$sql = "SELECT id, name FROM customers WHERE approved = 1";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Balances</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Check Customer Balance</h2>
    <form action="customer-balance.php" method="POST">
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
        <input type="submit" value="Check Balance">
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['customer_id'])) {
        $customer_id = $conn->real_escape_string($_POST['customer_id']);

        // Get total amount from bills
        $total_bills_sql = "SELECT SUM(amount) AS total_bills FROM bills WHERE customer_id = '$customer_id'";
        $total_bills_result = $conn->query($total_bills_sql);
        $total_bills = $total_bills_result->fetch_assoc()['total_bills'];

        // Get total payments made
        $total_payments_sql = "SELECT SUM(amount) AS total_payments FROM payments WHERE customer_id = '$customer_id'";
        $total_payments_result = $conn->query($total_payments_sql);
        $total_payments = $total_payments_result->fetch_assoc()['total_payments'];

        $outstanding_balance = $total_bills - $total_payments;

        echo "<h3>Balance Summary</h3>";
        echo "<p>Total Bills: " . ($total_bills ? $total_bills : 0) . "</p>";
        echo "<p>Total Payments: " . ($total_payments ? $total_payments : 0) . "</p>";
        echo "<p>Outstanding Balance: " . ($outstanding_balance > 0 ? $outstanding_balance : 0) . "</p>";
        echo "<p>Status: " . ($outstanding_balance > 0 ? "Outstanding" : "Settled") . "</p>";
    }
    ?>
</body>

</html>

<?php
$conn->close();
?>