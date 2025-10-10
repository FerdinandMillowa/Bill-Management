<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// database connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate CSRF token
        validateFormToken();

        // Sanitize inputs
        $customer_id = intval($_POST['customer_id']);
        $amount = floatval($_POST['amount']);
        $description = secureFormInput($_POST['description']);

        // Validate inputs
        if (empty($customer_id)) {
            throw new Exception("Customer selection is required.");
        }

        if (!validateAmount($amount)) {
            throw new Exception("Amount must be a positive number.");
        }

        if (empty($description)) {
            throw new Exception("Description is required.");
        }

        // Check customer exists and is approved
        $check = $conn->prepare("SELECT id FROM customers WHERE id=? AND status='approved'");
        $check->bind_param("i", $customer_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows == 0) {
            throw new Exception("Selected customer is not approved or doesn't exist.");
        }
        $check->close();

        // Insert bill
        $stmt = $conn->prepare("INSERT INTO bills (customer_id, amount, description) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $customer_id, $amount, $description);

        if ($stmt->execute()) {
            $success = "Bill added successfully!";
        } else {
            throw new Exception("Error saving bill: " . $conn->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
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
        <form class="form" action="add-bills.php" method="POST">
            <p class="title">Add Bill</p>
            <p class="message">Fill in the details below</p>

            <?php echo getFormTokenField(); ?>

            <label>
                <select id="customer_id" name="customer_id" required style="width:100%">
                    <option value="">Select Customer</option>
                    <?php while ($cust = $customers->fetch_assoc()): ?>
                        <?php $name = htmlspecialchars($cust['first_name'] . " " . $cust['last_name']); ?>
                        <option value="<?php echo $cust['id']; ?>"
                            <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $cust['id']) ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </label>

            <label>
                <input type="number" name="amount" required placeholder=" " step="0.01" min="0.01"
                    value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                <span>Amount (MWK)</span>
            </label>

            <label>
                <input type="text" name="description" required placeholder=" " maxlength="255"
                    value="<?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?>">
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