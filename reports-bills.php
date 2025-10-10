<?php
require_once 'auth-helper.php';
requireAdminAuth();

// Database connection
require_once 'db-connection.php';

// Handle filter and search input with sanitization
$customer_filter = isset($_POST['customer']) ? sanitizeInput($_POST['customer']) : '';
$date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
$date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';
$search_term = isset($_POST['search_term']) ? sanitizeInput($_POST['search_term']) : '';

// Build SQL query with prepared statement
$sql = "SELECT b.id, b.bill_name, b.amount, b.description, b.created_at, 
               c.first_name, c.last_name 
        FROM bills b 
        JOIN customers c ON b.customer_id = c.id
        WHERE (c.first_name LIKE CONCAT('%', ?, '%') OR c.last_name LIKE CONCAT('%', ?, '%')) 
        AND b.bill_name LIKE CONCAT('%', ?, '%')";

$params = [$customer_filter, $customer_filter, $search_term];
$types = "sss";

if ($date_from && $date_to) {
    $sql .= " AND DATE(b.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= "ss";
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports on Bills</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Reports on Bills</h2>

    <!-- Filter and Search Form -->
    <form action="reports-bills.php" method="POST">
        <label for="customer">Filter by Customer:</label>
        <input type="text" id="customer" name="customer" value="<?php echo htmlspecialchars($customer_filter); ?>">

        <label for="date_from">Date From:</label>
        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">

        <label for="date_to">Date To:</label>
        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">

        <label for="search_term">Search by Bill Name:</label>
        <input type="text" id="search_term" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>">

        <input type="submit" value="Apply Filters">
    </form>

    <!-- Bills Table -->
    <table>
        <tr>
            <th>ID</th>
            <th>Customer Name</th>
            <th>Bill Name</th>
            <th>Amount</th>
            <th>Description</th>
            <th>Date/Time</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customer_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$customer_name}</td>
                    <td><a href='view-bill.php?id={$row['id']}'>" . htmlspecialchars($row['bill_name']) . "</a></td>
                    <td>MWK " . number_format($row['amount'], 2) . "</td>
                    <td>" . htmlspecialchars($row['description']) . "</td>
                    <td>" . htmlspecialchars($row['created_at']) . "</td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='6'>No bills found.</td></tr>";
        }
        ?>
    </table>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>