<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.html");
    exit();
}

// Database connection
require_once 'db-connection.php';

$bill_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$bill_data = null;

if ($bill_id) {
    // Fetch bill data using prepared statement
    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bill_data = $result->fetch_assoc();
    $stmt->close();
}

// Update bill logic
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $bill_name = trim($_POST['bill_name']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);

    // Validate inputs
    if (empty($bill_name) || $amount <= 0) {
        $error = "Invalid bill data provided.";
    } else {
        // Update the bill using prepared statement
        $stmt = $conn->prepare("UPDATE bills SET bill_name=?, amount=?, description=? WHERE id=?");
        $stmt->bind_param("sdsi", $bill_name, $amount, $description, $bill_id);

        if ($stmt->execute()) {
            $success = "Bill updated successfully.";
            // Refresh bill data
            $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
            $stmt->bind_param("i", $bill_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $bill_data = $result->fetch_assoc();
        } else {
            $error = "Error updating bill: " . $conn->error;
        }
        $stmt->close();
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

    <?php if (isset($success)): ?>
        <div style="color: green; margin: 10px 0;"><?php echo $success; ?></div>
    <?php elseif (isset($error)): ?>
        <div style="color: red; margin: 10px 0;"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($bill_data): ?>
        <form action="edit-bill.php?id=<?php echo $bill_id; ?>" method="POST">
            <label for="bill-name">Bill Name:</label>
            <input type="text" id="bill-name" name="bill_name" value="<?php echo htmlspecialchars($bill_data['bill_name']); ?>" required>

            <label for="amount">Amount:</label>
            <input type="number" id="amount" name="amount" value="<?php echo htmlspecialchars($bill_data['amount']); ?>" step="0.01" min="0" required>

            <label for="description">Description:</label>
            <textarea id="description" name="description"><?php echo htmlspecialchars($bill_data['description']); ?></textarea>

            <input type="submit" value="Update Bill">
        </form>
    <?php else: ?>
        <p>Bill not found.</p>
    <?php endif; ?>
</body>

</html>

<?php
$conn->close();
?>