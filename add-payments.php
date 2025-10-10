<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
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
    $payment_method = secureFormInput($_POST['payment_method']);

    // Validate inputs
    if (empty($customer_id)) {
      throw new Exception("Customer selection is required.");
    }

    if (!validateAmount($amount)) {
      throw new Exception("Amount must be a positive number.");
    }

    if (empty($payment_method)) {
      throw new Exception("Payment method is required.");
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

    // Insert payment
    $stmt = $conn->prepare("INSERT INTO payments (customer_id, amount, payment_method) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $customer_id, $amount, $payment_method);

    if ($stmt->execute()) {
      $success = "Payment recorded successfully!";

      // Update bill status if full amount paid (optional enhancement)
      // $conn->query("UPDATE bills SET status='paid' WHERE customer_id='$customer_id' AND status='unpaid'");
    } else {
      throw new Exception("Error saving payment: " . $conn->error);
    }

    $stmt->close();
  } catch (Exception $e) {
    $error = $e->getMessage();
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
  <link rel="stylesheet" href="css/add-customer.css">
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/reports.css">
  <link rel="stylesheet" href="css/utilities.css">
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
    <form class="form" action="add-payments.php" method="POST">
      <p class="title">Record Payment</p>
      <p class="message">Fill in the payment details</p>

      <?php echo getFormTokenField(); ?>

      <label>
        <select id="customer_id" name="customer_id" required style="width:100%">
          <option value="">Select Customer</option>
          <?php
          $customers = $conn->query("SELECT id, first_name, last_name FROM customers WHERE status='approved'");
          while ($cust = $customers->fetch_assoc()):
            $name = htmlspecialchars($cust['first_name'] . " " . $cust['last_name']);
            $selected = (isset($_POST['customer_id']) && $_POST['customer_id'] == $cust['id']) ? 'selected' : '';
            echo "<option value='{$cust['id']}' $selected>{$name}</option>";
          endwhile;
          ?>
        </select>
        <span>Customer</span>
      </label>

      <label>
        <input type="number" name="amount" required placeholder=" " step="0.01" min="0.01"
          value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
        <span>Amount (MWK)</span>
      </label>

      <label>
        <select name="payment_method" required>
          <option value="" disabled selected>Select method</option>
          <option value="cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
          <option value="mobile_money" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option>
          <option value="bank" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank') ? 'selected' : ''; ?>>Bank Transfer</option>
          <option value="card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'card') ? 'selected' : ''; ?>>Credit/Debit Card</option>
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