<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

// Fetch all bills
$sql = "SELECT b.id, b.bill_name, b.amount, b.description, b.created_at, c.name AS customer_name 
        FROM bills b 
        JOIN customers c ON b.customer_id = c.id";

$result = $conn->query($sql);

// Check for SQL errors
if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bills</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>All Bills</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Customer Name</th>
            <th>Bill Name</th>
            <th>Amount</th>
            <th>Description</th>
            <th>Date/Time</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['customer_name']}</td>
                    <td>{$row['bill_name']}</td>
                    <td>{$row['amount']}</td>
                    <td>{$row['description']}</td>
                    <td>{$row['created_at']}</td>
                    <td>
                        <a href='edit-bill.php?id={$row['id']}'>Edit</a> | 
                        <a href='delete-bill.php?id={$row['id']}'>Delete</a>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='7'>No bills found.</td></tr>";
        }
        ?>
    </table>
</body>

</html>

<?php
$conn->close();
?>