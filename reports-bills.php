<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', 'Example@2022#', 'bill_management_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle filter and search input
$customer_filter = isset($_POST['customer']) ? $_POST['customer'] : '';
$date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
$date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';
$search_term = isset($_POST['search_term']) ? $_POST['search_term'] : '';

// SQL query with filters
$sql = "SELECT b.id, b.bill_name, b.amount, b.description, b.date_time, c.name AS customer_name 
        FROM bills b 
        JOIN customers c ON b.customer_id = c.id
        WHERE (c.name LIKE '%$customer_filter%' AND b.bill_name LIKE '%$search_term%')";

if ($date_from && $date_to) {
    $sql .= " AND b.date_time BETWEEN '$date_from' AND '$date_to'";
}

$result = $conn->query($sql);
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
        <input type="text" id="customer" name="customer" value="<?php echo $customer_filter; ?>">

        <label for="date_from">Date From:</label>
        <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">

        <label for="date_to">Date To:</label>
        <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">

        <label for="search_term">Search by Bill Name:</label>
        <input type="text" id="search_term" name="search_term" value="<?php echo $search_term; ?>">

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
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['customer_name']}</td>
<td><a href='view-bill.php?id=<?php echo $row[id]; ?>'><?php echo $row[bill_name]; ?></a></td>
                    <td>{$row['amount']}</td>
                    <td>{$row['description']}</td>
                    <td>{$row['date_time']}</td>
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
$conn->close();
?>