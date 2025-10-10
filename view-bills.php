<?php
require_once 'auth-helper.php';
requireAdminAuth();

// Database connection
require_once 'db-connection.php';

// Fetch all bills using prepared statement
$stmt = $conn->prepare("SELECT b.id, b.bill_name, b.amount, b.description, b.created_at, 
                               c.first_name, c.last_name 
                        FROM bills b 
                        JOIN customers c ON b.customer_id = c.id 
                        ORDER BY b.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
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
                $customer_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$customer_name}</td>
                    <td>" . htmlspecialchars($row['bill_name']) . "</td>
                    <td>MWK " . number_format($row['amount'], 2) . "</td>
                    <td>" . htmlspecialchars($row['description']) . "</td>
                    <td>" . htmlspecialchars($row['created_at']) . "</td>
                    <td>
                        <a href='edit-bill.php?id={$row['id']}'>Edit</a> | 
                        <a href='delete-bill.php?id={$row['id']}' onclick='return confirm(\"Are you sure you want to delete this bill?\")'>Delete</a>
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
$stmt->close();
$conn->close();
?>