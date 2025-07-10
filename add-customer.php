<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection
$conn = new mysqli("localhost", "root", "Example@2022#", "bill_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $status = "pending";

    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^(?:\+265|0)\d{9}$/', $phone)) {
        $error = "Invalid phone number. It must start with +265 or 0 and contain exactly 10 digits after.";
    } else {
        $check = $conn->query("SELECT id FROM customers WHERE email='$email' OR phone='$phone'");
        if ($check->num_rows > 0) {
            $error = "A customer with this email or phone already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $first_name, $last_name, $email, $phone, $address, $status);
            if ($stmt->execute()) {
                $success = "Customer submitted successfully. Awaiting admin approval.";
            } else {
                $error = "Error saving customer.";
            }
        }
    }
}

// Fetch recent customers (latest 5)
$recent_customers = $conn->query("SELECT first_name, last_name, email, phone FROM customers ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Customer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/add-customer.css">
</head>

<body>
    <header>
        <?php include 'header.php'; ?>
        <?php if (!empty($success)) : ?>
            <p class="success-message"><?php echo $success; ?></p>
        <?php elseif (!empty($error)) : ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>
    </header>

    <!-- Left: Add Customer Form -->
    <section class="form-section">
        <form class="form" action="add-customer.php" method="POST" autocomplete="off">
            <p class="title">Add Customer</p>
            <p class="message">Fill in the details below</p>

            <div class="form-group">
                <label>
                    <input type="text" name="first_name" required placeholder=" " maxlength="50">
                    <span>First Name</span>
                </label>
                <label>
                    <input type="text" name="last_name" required placeholder=" " maxlength="50">
                    <span>Last Name</span>
                </label>
            </div>

            <label>
                <input type="email" name="email" required placeholder=" " maxlength="100">
                <span>Email</span>
            </label>

            <label>
                <input type="tel" name="phone" required placeholder=" " maxlength="13" pattern="^(?:\+265|0)\d{9}$"
                    title="Phone number must start with +265 or 0 and contain exactly 9 digits after.">
                <span>Phone</span>
            </label>

            <label>
                <input type="text" name="address" placeholder=" " maxlength="255">
                <span>Address</span>
            </label>

            <button type="submit" class="submit">Add Customer</button>

            <p class="signin">Return to <a href="index.php">Home</a></p>
        </form>
    </section>

    <!-- Right: Recent Customers -->
    <section class="list-section">
        <div class="customer-list">
            <h3>Recently Added Customers</h3>
            <ul>
                <?php if ($recent_customers->num_rows > 0): ?>
                    <?php while ($cust = $recent_customers->fetch_assoc()): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($cust['first_name'] . " " . $cust['last_name']); ?></strong><br>
                            <?php echo htmlspecialchars($cust['email']); ?><br>
                            <?php echo htmlspecialchars($cust['phone']); ?>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li>No customers added yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>
    <footer>
        <?php include 'footer.php' ?>
    </footer>


</body>

</html>