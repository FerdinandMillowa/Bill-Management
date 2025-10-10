<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

// Handle search and filter input
$search_term = isset($_POST['search_term']) ? $_POST['search_term'] : '';
$balance_status = isset($_POST['balance_status']) ? $_POST['balance_status'] : '';

// SQL query with filters
$sql = "SELECT c.id, c.name, 
            (SELECT SUM(amount) FROM bills WHERE customer_id = c.id) AS total_bills,
            (SELECT SUM(amount) FROM payments WHERE customer_id = c.id) AS total_payments
        FROM customers c 
        WHERE c.name LIKE '%$search_term%'";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports on Customers</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Reports on Customers</h2>

    <!-- Filter and Search Form -->
    <form action="reports-customers.php" method="POST">
        <label for="search_term">Search by Customer Name:</label>
        <input type="text" id="search_term" name="search_term" value="<?php echo $search_term; ?>">

        <label for="balance_status">Filter by Balance Status:</label>
        <select id="balance_status" name="balance_status">
            <option value="">All</option>
            <option value="outstanding" <?php if ($balance_status == "outstanding") echo "selected"; ?>>Outstanding</option>
            <option value="settled" <?php if ($balance_status == "settled") echo "selected"; ?>>Settled</option>
        </select>

        <input type="submit" value="Apply Filters">
    </form>

    <!-- Customer Table -->
    <table>
        <tr>
            <th>ID</th>
            <th>Customer Name</th>
            <th>Total Bills</th>
            <th>Total Payments</th>
            <th>Outstanding Balance</th>
        </tr>
        <?php
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $outstanding_balance = $row['total_bills'] - $row['total_payments'];
                $status = ($outstanding_balance > 0) ? "outstanding" : "settled";

                // Apply balance status filter
                if ($balance_status && $balance_status != $status) {
                    continue;
                }

                echo "<tr>
                    <td>{$row['id']}</td>
                    <td><a href='view-customer.php?id=<?php echo $row[id]; ?>'><?php echo $row[name]; ?></a></td>
                    <td>" . ($row['total_bills'] ? $row['total_bills'] : 0) . "</td>
                    <td>" . ($row['total_payments'] ? $row['total_payments'] : 0) . "</td>
                    <td>" . ($outstanding_balance > 0 ? $outstanding_balance : 0) . "</td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No customers found.</td></tr>";
        }
        ?>
    </table>
</body>

</html>

<?php
$conn->close();
?>