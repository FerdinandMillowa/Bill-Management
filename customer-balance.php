<?php
require_once 'auth-helper.php';
requireAdminAuth();

// Database connection
require_once 'db-connection.php';

// Fetch all approved customers
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM customers WHERE status = 'approved' ORDER BY first_name, last_name");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Balances</title>
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="css/utilities.css">
    <style>
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .balance-table th,
        .balance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .balance-table th {
            background-color: #24323d;
            color: white;
        }

        .balance-owed {
            color: #f44336;
            font-weight: bold;
        }

        .balance-credit {
            color: #ff9800;
            font-weight: bold;
        }

        .balance-settled {
            color: #4CAF50;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <header>
        <?php include 'header.php'; ?>
    </header>

    <main style="padding: 20px;">
        <h2>Customer Balances</h2>

        <table class="balance-table">
            <tr>
                <th>Customer Name</th>
                <th>Total Bills</th>
                <th>Total Payments</th>
                <th>Balance</th>
                <th>Status</th>
            </tr>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Calculate balance for each customer
                    $bills_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_bills FROM bills WHERE customer_id = ?");
                    $bills_stmt->bind_param("i", $row['id']);
                    $bills_stmt->execute();
                    $bills_result = $bills_stmt->get_result();
                    $total_bills = $bills_result->fetch_assoc()['total_bills'];

                    $payments_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_payments FROM payments WHERE customer_id = ?");
                    $payments_stmt->bind_param("i", $row['id']);
                    $payments_stmt->execute();
                    $payments_result = $payments_stmt->get_result();
                    $total_payments = $payments_result->fetch_assoc()['total_payments'];

                    $balance = $total_bills - $total_payments;

                    // Determine status and class
                    if ($balance > 0) {
                        $status_class = "balance-owed";
                        $status = "Owes MWK " . number_format($balance, 2);
                    } elseif ($balance < 0) {
                        $status_class = "balance-credit";
                        $status = "Credit MWK " . number_format(abs($balance), 2);
                    } else {
                        $status_class = "balance-settled";
                        $status = "Settled";
                    }

                    echo "
                    <tr>
                        <td>{$row['first_name']} {$row['last_name']}</td>
                        <td>MWK " . number_format($total_bills, 2) . "</td>
                        <td>MWK " . number_format($total_payments, 2) . "</td>
                        <td>MWK " . number_format($balance, 2) . "</td>
                        <td class='$status_class'>$status</td>
                    </tr>";

                    $bills_stmt->close();
                    $payments_stmt->close();
                }
            } else {
                echo "<tr><td colspan='5'>No approved customers found.</td></tr>";
            }
            ?>
        </table>
    </main>

    <footer>
        <?php include 'footer.php'; ?>
    </footer>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>