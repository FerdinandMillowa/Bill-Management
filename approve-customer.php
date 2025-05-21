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

// Approve customer
if (isset($_POST['approve'])) {
    $customer_id = $_POST['customer_id'];
    $sql = "UPDATE customers SET status='approved' WHERE id=$customer_id";
    $conn->query($sql);
}

// Delete customer
if (isset($_POST['delete'])) {
    $customer_id = $_POST['customer_id'];
    $sql = "DELETE FROM customers WHERE id=$customer_id";
    $conn->query($sql);
}

// Fetch pending customers
$sql = "SELECT * FROM customers WHERE status='pending'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="stylesheet" href="css/style.css"> -->
    <title>Customer Approval</title>
</head>

<body>
    <h2>Approve or Delete Customers</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['name']}</td>
                    <td>{$row['email']}</td>
                    <td>{$row['phone']}</td>
                    <td>{$row['address']}</td>
                    <td>
                        <form action='approve-customers.php' method='POST' style='display:inline;'>
                            <input type='hidden' name='customer_id' value='{$row['id']}'>
                            <input type='submit' name='approve' value='Approve'>
                        </form>
                        <form action='approve-customers.php' method='POST' style='display:inline;'>
                            <input type='hidden' name='customer_id' value='{$row['id']}'>
                            <input type='submit' name='delete' value='Delete'>
                        </form>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='6'>No pending customers found.</td></tr>";
        }
        ?>
    </table>
</body>

</html>

<?php
$conn->close();
?>