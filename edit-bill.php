<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

$bill_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : null;
$bill_data = null;

if ($bill_id) {
    // Fetch bill data
    $sql = "SELECT * FROM bills WHERE id = '$bill_id'";
    $result = $conn->query($sql);
    $bill_data = $result->fetch_assoc();
}

// Update bill logic
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $bill_name = $conn->real_escape_string($_POST['bill_name']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $description = $conn->real_escape_string($_POST['description']);

    // Update the bill
    $update_sql = "UPDATE bills SET bill_name='$bill_name', amount='$amount', description='$description' WHERE id='$bill_id'";

    if ($conn->query($update_sql) === TRUE) {
        echo "Bill updated successfully.";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bill</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h2>Edit Bill</h2>
    <form action="edit-bill.php?id=<?php echo $bill_id; ?>" method="POST">
        <label for="bill-name">Bill Name:</label>
        <input type="text" id="bill-name" name="bill_name" value="<?php echo $bill_data['bill_name']; ?>" required>

        <label for="amount">Amount:</label>
        <input type="number" id="amount" name="amount" value="<?php echo $bill_data['amount']; ?>" required>

        <label for="description">Description:</label>
        <textarea id="description" name="description"><?php echo $bill_data['description']; ?></textarea>

        <input type="submit" value="Update Bill">
    </form>
</body>

</html>

<?php
$conn->close();
?>