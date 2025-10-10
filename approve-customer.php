<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

$success = "";
$error = "";

// Approve customer using prepared statement
if (isset($_POST['approve'])) {
    $customer_id = intval($_POST['customer_id']);
    $stmt = $conn->prepare("UPDATE customers SET status='approved' WHERE id=?");
    $stmt->bind_param("i", $customer_id);
    if ($stmt->execute()) {
        $success = "Customer approved successfully.";
    } else {
        $error = "Error approving customer: " . $conn->error;
    }
    $stmt->close();
}

// Delete customer using prepared statement
if (isset($_POST['delete'])) {
    $customer_id = intval($_POST['customer_id']);
    $stmt = $conn->prepare("DELETE FROM customers WHERE id=?");
    $stmt->bind_param("i", $customer_id);
    if ($stmt->execute()) {
        $success = "Customer deleted successfully.";
    } else {
        $error = "Error deleting customer: " . $conn->error;
    }
    $stmt->close();
}

// Fetch pending customers using prepared statement
$stmt = $conn->prepare("SELECT * FROM customers WHERE status='pending'");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Approval</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Approve or Delete Customers</h2>

    <?php if ($success): ?>
        <div style="color: green; margin: 10px 0;"><?php echo $success; ?></div>
    <?php elseif ($error): ?>
        <div style="color: red; margin: 10px 0;"><?php echo $error; ?></div>
    <?php endif; ?>

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
                    <td>" . htmlspecialchars($row['first_name'] . " " . $row['last_name']) . "</td>
                    <td>" . htmlspecialchars($row['email']) . "</td>
                    <td>" . htmlspecialchars($row['phone']) . "</td>
                    <td>" . htmlspecialchars($row['address'] ?? 'N/A') . "</td>
                    <td>
                        <form action='approve-customer.php' method='POST' style='display:inline;'>
                            <input type='hidden' name='customer_id' value='{$row['id']}'>
                            <input type='submit' name='approve' value='Approve'>
                        </form>
                        <form action='approve-customer.php' method='POST' style='display:inline;'>
                            <input type='hidden' name='customer_id' value='{$row['id']}'>
                            <input type='submit' name='delete' value='Delete' onclick='return confirm(\"Are you sure you want to delete this customer?\")'>
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