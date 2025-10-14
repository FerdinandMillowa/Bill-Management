<?php
require_once 'db-connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['customer_id'])) {
    $customer_id = intval($_POST['customer_id']);

    // Get customer details
    $customer_stmt = $conn->prepare("SELECT first_name, last_name FROM customers WHERE id = ?");
    $customer_stmt->bind_param("i", $customer_id);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();

    if ($customer_result->num_rows > 0) {
        $customer = $customer_result->fetch_assoc();

        // Calculate balance
        $bills_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_bills FROM bills WHERE customer_id = ?");
        $bills_stmt->bind_param("i", $customer_id);
        $bills_stmt->execute();
        $bills_result = $bills_stmt->get_result();
        $total_bills = $bills_result->fetch_assoc()['total_bills'];

        $payments_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_payments FROM payments WHERE customer_id = ?");
        $payments_stmt->bind_param("i", $customer_id);
        $payments_stmt->execute();
        $payments_result = $payments_stmt->get_result();
        $total_payments = $payments_result->fetch_assoc()['total_payments'];

        $balance = $total_bills - $total_payments;

        // Determine balance class
        if ($balance > 0) {
            $balance_class = "balance-positive";
            $status = "Owes: MWK " . number_format($balance, 2);
        } elseif ($balance < 0) {
            $balance_class = "balance-negative";
            $status = "Credit: MWK " . number_format(abs($balance), 2);
        } else {
            $balance_class = "balance-zero";
            $status = "Balance Settled";
        }

        echo "
            <h4>Customer: {$customer['first_name']} {$customer['last_name']}</h4>
            <p>Total Bills: <strong>MWK " . number_format($total_bills, 2) . "</strong></p>
            <p>Total Payments: <strong>MWK " . number_format($total_payments, 2) . "</strong></p>
            <p class='$balance_class'>$status</p>
        ";
    } else {
        echo "<p>Customer not found.</p>";
    }

    $customer_stmt->close();
    $bills_stmt->close();
    $payments_stmt->close();
}

$conn->close();
