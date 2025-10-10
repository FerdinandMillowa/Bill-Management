<?php
require_once 'auth-helper.php';
requireAdminAuth();

// Database connection
require_once 'db-connection.php';

// Handle search and filter input with sanitization
$search_term = isset($_POST['search_term']) ? sanitizeInput($_POST['search_term']) : '';
$balance_status = isset($_POST['balance_status']) ? $_POST['balance_status'] : '';

// SQL query with prepared statement
$sql = "SELECT c.id, c.first_name, c.last_name, 
               COALESCE(SUM(b.amount), 0) AS total_bills,
               COALESCE(SUM(p.amount), 0) AS total_payments
        FROM customers c 
        LEFT JOIN bills b ON c.id = b.customer_id
        LEFT JOIN payments p ON c.id = p.customer_id
        WHERE (c.first_name LIKE CONCAT('%', ?, '%') OR c.last_name LIKE CONCAT('%', ?, '%'))
        GROUP BY c.id, c.first_name, c.last_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports on Customers</title>
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/utilities.css">
</head>

<body>
    <h2>Reports on Customers</h2>

    <!-- Filter and Search Form -->
    <form action="reports-customers.php" method="POST">
        <label for="search_term">Search by Customer Name:</label>
        <input type="text" id="search_term" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>">

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
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $outstanding_balance = $row['total_bills'] - $row['total_payments'];
                $status = ($outstanding_balance > 0) ? "outstanding" : "settled";

                // Apply balance status filter
                if ($balance_status && $balance_status != $status) {
                    continue;
                }

                $customer_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td><a href='view-customer.php?id={$row['id']}'>{$customer_name}</a></td>
                    <td>MWK " . number_format($row['total_bills'], 2) . "</td>
                    <td>MWK " . number_format($row['total_payments'], 2) . "</td>
                    <td>MWK " . number_format(max($outstanding_balance, 0), 2) . "</td>
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
$stmt->close();
$conn->close();
?>