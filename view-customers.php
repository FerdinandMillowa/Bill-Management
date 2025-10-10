<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

// Fetch unapproved customers
$sql = "SELECT * FROM customers WHERE approved = FALSE";
$result = $conn->query($sql);
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
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['name']}</td>
                    <td>{$row['email']}</td>
                    <td>{$row['phone']}</td>
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
$conn->close();
?>