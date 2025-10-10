<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

// Fetch all approved customers using prepared statement
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM customers WHERE status = 'approved'");
$stmt->execute();
$result = $stmt->get_result();
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
                    $full_name = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
                    echo "<option value='{$row['id']}'>{$full_name}</option>";
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
        $customer_id = intval($_POST['customer_id']);

        // Get total amount from bills using prepared statement
        $bills_stmt = $conn->prepare("SELECT SUM(amount) AS total_bills FROM bills WHERE customer_id = ?");
        $bills_stmt->bind_param("i", $customer_id);
        $bills_stmt->execute();
        $total_bills_result = $bills_stmt->get_result();
        $total_bills = $total_bills_result->fetch_assoc()['total_bills'] ?? 0;
        $bills_stmt->close();

        // Get total payments made using prepared statement
        $payments_stmt = $conn->prepare("SELECT SUM(amount) AS total_payments FROM payments WHERE customer_id = ?");
        $payments_stmt->bind_param("i", $customer_id);
        $payments_stmt->execute();
        $total_payments_result = $payments_stmt->get_result();
        $total_payments = $total_payments_result->fetch_assoc()['total_payments'] ?? 0;
        $payments_stmt->close();

        $outstanding_balance = $total_bills - $total_payments;

        echo "<h3>Balance Summary</h3>";
        echo "<p>Total Bills: MWK " . number_format($total_bills, 2) . "</p>";
        echo "<p>Total Payments: MWK " . number_format($total_payments, 2) . "</p>";
        echo "<p>Outstanding Balance: MWK " . number_format(max($outstanding_balance, 0), 2) . "</p>";
        echo "<p>Status: " . ($outstanding_balance > 0 ? "Outstanding" : "Settled") . "</p>";
    }
    ?>
</body>

</html>

<?php
$conn->close();
?>