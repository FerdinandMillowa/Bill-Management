<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// database connection 
require_once 'db-connection.php';

$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $conn->real_escape_string($_POST['customer_id']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $description = $conn->real_escape_string($_POST['description']);

    // Validate inputs
    if (empty($customer_id)) {
        $error = "Customer selection is required.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Amount must be a positive number.";
    } else {
        // Check customer exists
        $check = $conn->query("SELECT id FROM customers WHERE id='$customer_id' AND status='approved'");
        if ($check->num_rows == 0) {
            $error = "Selected customer is not approved or doesn't exist.";
        } else {
            $stmt = $conn->prepare("INSERT INTO bills (customer_id, amount, description) VALUES (?, ?, ?)");
            $stmt->bind_param("ids", $customer_id, $amount, $description);
            if ($stmt->execute()) {
                $success = "Bill added successfully!";
            } else {
                $error = "Error saving bill: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch recent bills
$recent_bills = $conn->query("
    SELECT b.amount, b.description, b.created_at, 
           c.first_name, c.last_name 
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    ORDER BY b.created_at DESC LIMIT 5
");

// Fetch customers for dropdown
$customers = $conn->query("SELECT id, first_name, last_name FROM customers WHERE status='approved'");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Bill</title>
    <link rel="stylesheet" href="css/add-customer.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#customer_id').select2({
                placeholder: "Select customer",
                width: '100%'
            });
        });
    </script>
</head>

<body>
    <header>
        <?php include 'header.php'; ?>
        <?php if (!empty($success)): ?>
            <p class="success-message"><?php echo $success; ?></p>
        <?php elseif (!empty($error)): ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>
    </header>

    <!-- Left: Add Bill Form -->
    <section class="form-section">
        <form class="form" action="add-bill.php" method="POST">
            <p class="title">Add Bill</p>
            <p class="message">Fill in the details below</p>

            <label>
                <select id="customer_id" name="customer_id" required style="width:100%">
                    <option value="">Select Customer</option>
                    <?php while ($cust = $customers->fetch_assoc()): ?>
                        <?php $name = htmlspecialchars($cust['first_name'] . " " . $cust['last_name']); ?>
                        <option value="<?php echo $cust['id']; ?>"><?php echo $name; ?></option>
                    <?php endwhile; ?>
                </select>
            </label>

            <label>
                <input type="number" name="amount" required placeholder=" " step="100" min="500">
                <span>Amount (MWK)</span>
            </label>

            <label>
                <input type="text" name="description" required placeholder=" " maxlength="50">
                <span>Description</span>
            </label>

            <button type="submit" class="submit">Add Bill</button>
            <p class="signin">Return to <a href="index.php">Home</a></p>
        </form>
    </section>

    <!-- Right: Recent Bills -->
    <section class="list-section">
        <div class="customer-list">
            <h3>Recent Bills</h3>
            <ul>
                <?php if ($recent_bills->num_rows > 0): ?>
                    <?php while ($bill = $recent_bills->fetch_assoc()): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($bill['first_name'] . " " . $bill['last_name']); ?></strong>
                            <span>MWK <?php echo number_format($bill['amount'], 2); ?></span>
                            <span><?php echo htmlspecialchars($bill['description']); ?></span>
                            <small><?php echo date("M j, Y", strtotime($bill['created_at'])); ?></small>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li>No bills found</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <footer>
        <?php include 'footer.php'; ?>
    </footer>
</body>

</html>