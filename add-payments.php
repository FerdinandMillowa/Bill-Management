<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection
require_once 'db-connection.php';

$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $customer_id = $conn->real_escape_string($_POST['customer_id']);
  $amount = $conn->real_escape_string($_POST['amount']);
  $payment_method = $conn->real_escape_string($_POST['payment_method']);

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
      $stmt = $conn->prepare("INSERT INTO payments (customer_id, amount, payment_method) VALUES (?, ?, ?)");
      $stmt->bind_param("ids", $customer_id, $amount, $payment_method);
      if ($stmt->execute()) {
        $success = "Payment recorded successfully!";

        // Update bill status if full amount paid
        $conn->query("UPDATE bills SET status='paid' WHERE customer_id='$customer_id' AND status='unpaid'");
      } else {
        $error = "Error saving payment: " . $conn->error;
      }
    }
  }
}

// Fetch recent payments
$recent_payments = $conn->query("
    SELECT p.amount, p.payment_method, p.created_at, 
           c.first_name, c.last_name 
    FROM payments p
    JOIN customers c ON p.customer_id = c.id
    ORDER BY p.created_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Payment</title>
  <link rel="stylesheet" href="css/add-customer.css"> <!-- Using same CSS as reference -->
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

  <!-- Left: Add Payment Form -->
  <section class="form-section">
    <form class="form" action="add-payment.php" method="POST">
      <p class="title">Record Payment</p>
      <p class="message">Fill in the payment details</p>

      <label>
        <select id="customer_id" name="customer_id" required style="width:100%">
          <?php
          $customers = $conn->query("SELECT id, first_name, last_name FROM customers WHERE status='approved'");
          while ($cust = $customers->fetch_assoc()):
            $name = htmlspecialchars($cust['first_name'] . " " . $cust['last_name']);
            echo "<option value='{$cust['id']}'>{$name}</option>";
          endwhile;
          ?>
        </select>
        <span>Customer</span>
      </label>

      <label>
        <input type="number" name="amount" required placeholder=" " step="100" min="500">
        <span>Amount (MWK)</span>
      </label>

      <label>
        <select name="payment_method" required>
          <option value="" disabled selected>Select method</option>
          <option value="cash">Cash</option>
          <option value="mobile_money">Mobile Money</option>
          <option value="bank">Bank Transfer</option>
          <option value="card">Credit/Debit Card</option>
        </select>
        <span>Payment Method</span>
      </label>

      <button type="submit" class="submit">Record Payment</button>
      <p class="signin">Return to <a href="index.php">Home</a></p>
    </form>
  </section>

  <!-- Right: Recent Payments -->
  <section class="list-section">
    <div class="customer-list">
      <h3>Recent Payments</h3>
      <ul>
        <?php if ($recent_payments->num_rows > 0): ?>
          <?php while ($payment = $recent_payments->fetch_assoc()): ?>
            <li>
              <strong><?php echo htmlspecialchars($payment['first_name'] . " " . $payment['last_name']); ?></strong>
              <span>MWK <?php echo number_format($payment['amount'], 2); ?></span>
              <span><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></span>
              <small><?php echo date("M j, Y", strtotime($payment['created_at'])); ?></small>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li>No payments found</li>
        <?php endif; ?>
      </ul>
    </div>
  </section>

  <footer>
    <?php include 'footer.php'; ?>
  </footer>
</body>

</html>