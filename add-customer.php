<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

// Require any authentication
requireAnyAuth();

$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate CSRF token
        validateFormToken();

        // Sanitize inputs
        $first_name = secureFormInput($_POST["first_name"]);
        $last_name = secureFormInput($_POST["last_name"]);
        $email = secureFormInput($_POST["email"]);
        $phone = secureFormInput($_POST["phone"]);
        $address = secureFormInput($_POST["address"]);
        $status = "pending";

        // Validate inputs
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
            throw new Exception("All fields are required.");
        }

        if (!validateEmail($email)) {
            throw new Exception("Please enter a valid email address.");
        }

        if (!validatePhone($phone)) {
            throw new Exception("Invalid phone number. It must start with +265 or 0 and contain exactly 9 digits after.");
        }

        // Check for existing customer
        $check = $conn->prepare("SELECT id FROM customers WHERE email=? OR phone=?");
        $check->bind_param("ss", $email, $phone);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            throw new Exception("A customer with this email or phone already exists.");
        }

        $check->close();

        // Insert customer
        $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $first_name, $last_name, $email, $phone, $address, $status);

        if ($stmt->execute()) {
            $success = "Customer submitted successfully. " . (isAdmin() ? "You can approve it from the list." : "Awaiting admin approval.");
        } else {
            throw new Exception("Error saving customer: " . $conn->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch recent customers (latest 5) - show ALL to admins, only approved to regular users
if (isAdmin()) {
    $recent_customers = $conn->query("SELECT id, first_name, last_name, email, phone, status FROM customers ORDER BY created_at DESC LIMIT 10");
} else {
    $recent_customers = $conn->query("SELECT id, first_name, last_name, email, phone, status FROM customers WHERE status='approved' ORDER BY created_at DESC LIMIT 5");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Customer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="css/utilities.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .customer-actions {
            margin-top: var(--spacing-sm, 8px);
            display: flex;
            gap: var(--spacing-sm, 8px);
            flex-wrap: wrap;
        }

        .btn-approve,
        .btn-delete {
            padding: var(--spacing-xs, 4px) var(--spacing-sm, 8px);
            border: none;
            border-radius: var(--radius-sm, 4px);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base, 0.2s ease);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-approve {
            background: var(--color-success, #4CAF50);
            color: white;
        }

        .btn-approve:hover {
            background: #45a049;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: var(--color-error, #f44336);
            color: white;
        }

        .btn-delete:hover {
            background: #da190b;
            transform: translateY(-1px);
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: var(--radius-sm, 4px);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: var(--spacing-xs, 4px);
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.2);
            color: var(--color-warning, #ff9800);
            border: 1px solid var(--color-warning, #ff9800);
        }

        .status-approved {
            background: rgba(76, 175, 80, 0.2);
            color: var(--color-success, #4CAF50);
            border: 1px solid var(--color-success, #4CAF50);
        }
    </style>
</head>

<body>
    <header>
        <?php include 'header.php'; ?>
        <?php if (!empty($success)) : ?>
            <p class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </p>
        <?php elseif (!empty($error)) : ?>
            <p class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </p>
        <?php endif; ?>
    </header>

    <!-- Left: Add Customer Form -->
    <section class="form-section">
        <form class="form" action="add-customer.php" method="POST" autocomplete="off">
            <p class="title">Add Customer</p>
            <p class="message">Fill in the details below</p>

            <?php echo getFormTokenField(); ?>

            <div class="form-group">
                <label>
                    <input type="text" name="first_name" required placeholder=" " maxlength="50"
                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    <span>First Name</span>
                </label>
                <label>
                    <input type="text" name="last_name" required placeholder=" " maxlength="50"
                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    <span>Last Name</span>
                </label>
            </div>

            <label>
                <input type="email" name="email" required placeholder=" " maxlength="100"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <span>Email</span>
            </label>

            <label>
                <input type="tel" name="phone" required placeholder=" " maxlength="13"
                    pattern="^(?:\+265|0)\d{9}$"
                    title="Phone number must start with +265 or 0 and contain exactly 9 digits after."
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                <span>Phone</span>
            </label>

            <label>
                <input type="text" name="address" placeholder=" " maxlength="255"
                    value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                <span>Address (Optional)</span>
            </label>

            <button type="submit" class="submit">
                <i class="fas fa-user-plus"></i> Add Customer
            </button>

            <p class="signin">Return to <a href="index.php">Home</a></p>
        </form>
    </section>

    <!-- Right: Recent Customers -->
    <section class="list-section">
        <div class="customer-list">
            <h3><?php echo isAdmin() ? 'All Recent Customers' : 'Recently Added Customers'; ?></h3>
            <ul>
                <?php if ($recent_customers->num_rows > 0): ?>
                    <?php while ($cust = $recent_customers->fetch_assoc()): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($cust['first_name'] . " " . $cust['last_name']); ?></strong>

                            <span class="status-badge status-<?php echo $cust['status']; ?>">
                                <?php echo ucfirst($cust['status']); ?>
                            </span>

                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($cust['email']); ?></span>
                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($cust['phone']); ?></span>

                            <?php if (isAdmin() && $cust['status'] === 'pending'): ?>
                                <div class="customer-actions">
                                    <button class="btn-approve" onclick="approveCustomer(<?php echo $cust['id']; ?>, '<?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?>')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn-delete" onclick="deleteCustomer(<?php echo $cust['id']; ?>, '<?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            <?php elseif (!isAdmin() && $cust['status'] === 'pending'): ?>
                                <div class="customer-actions">
                                    <button class="btn-approve" onclick="requestApproval(<?php echo $cust['id']; ?>, '<?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?>')">
                                        <i class="fas fa-user-check"></i> Request Admin Approval
                                    </button>
                                </div>
                            <?php endif; ?>
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

    <!-- Include Admin Auth Modal -->
    <?php include 'admin-auth-modal.php'; ?>

    <script>
        /**
         * Approve customer (Admin only - direct action)
         */
        function approveCustomer(customerId, customerName) {
            if (!confirm(`Are you sure you want to approve ${customerName}?`)) {
                return;
            }

            // Admin can approve directly
            fetch('verify-admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'approve_customer',
                        data: JSON.stringify({
                            customer_id: customerId
                        }),
                        username: '<?php echo $_SESSION['username'] ?? ''; ?>',
                        password: 'admin_bypass' // Special flag for already authenticated admins
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`${customerName} has been approved successfully!`);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        }

        /**
         * Delete customer (Admin only)
         */
        function deleteCustomer(customerId, customerName) {
            if (!confirm(`Are you sure you want to delete ${customerName}? This action cannot be undone.`)) {
                return;
            }

            fetch('verify-admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'delete_customer',
                        data: JSON.stringify({
                            customer_id: customerId
                        }),
                        username: '<?php echo $_SESSION['username'] ?? ''; ?>',
                        password: 'admin_bypass'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`${customerName} has been deleted successfully.`);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        }

        /**
         * Request admin approval (Regular users - shows modal)
         */
        function requestApproval(customerId, customerName) {
            showAdminAuthModal(
                'approve_customer', {
                    customer_id: customerId
                },
                `Admin credentials required to approve ${customerName}`,
                async function(result) {
                    alert(`${customerName} has been approved successfully!`);
                }
            );
        }
    </script>
</body>

</html>