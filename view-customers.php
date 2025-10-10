<?php
require_once 'auth-helper.php';
requireAdminAuth();

// Database connection
require_once 'db-connection.php';

// Fetch unapproved customers using prepared statement
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone FROM customers WHERE status = 'pending'");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customers</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Unapproved Customers</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$full_name}</td>
                    <td>" . htmlspecialchars($row['email']) . "</td>
                    <td>" . htmlspecialchars($row['phone']) . "</td>
                    <td><a href='approve-customer.php?id={$row['id']}'>Approve</a></td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No unapproved customers found.</td></tr>";
        }
        ?>
    </table>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>