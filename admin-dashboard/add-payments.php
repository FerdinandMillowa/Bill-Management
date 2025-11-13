<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once '../db-connection.php';
require_once '../auth-helper.php';

// Require authentication
requireAnyAuth();

// Get current user info
$admin_username = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'User';
$admin_id = $_SESSION['user_id'];

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
        $check = $conn->prepare("SELECT id, first_name, last_name FROM customers WHERE id=? AND status='approved'");
        $check->bind_param("i", $customer_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows == 0) {
            throw new Exception("Selected customer is not approved or doesn't exist.");
        }

        $customer = $check_result->fetch_assoc();
        $check->close();

        // Insert payment
        $stmt = $conn->prepare("INSERT INTO payments (customer_id, amount, payment_method) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $customer_id, $amount, $payment_method);

        if ($stmt->execute()) {
            $customer_name = $customer['first_name'] . " " . $customer['last_name'];
            $success = "Payment of MWK " . number_format($amount, 2) . " recorded successfully for $customer_name!";
            logActivity('payment_created', "New payment recorded: $customer_name - MWK " . number_format($amount, 2));
        } else {
            throw new Exception("Error saving payment: " . $conn->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get payment statistics
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(amount), 0) as total_amount,
        COALESCE(AVG(amount), 0) as avg_amount
    FROM payments
");
$stats = $stats_query->fetch_assoc();

// Get payment method breakdown
$method_stats = $conn->query("
    SELECT 
        payment_method,
        COUNT(*) as count,
        COALESCE(SUM(amount), 0) as total
    FROM payments
    GROUP BY payment_method
    ORDER BY total DESC
");

// Get total bills for comparison
$bills_total = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM bills")->fetch_assoc()['total'];
$collection_rate = $bills_total > 0 ? round(($stats['total_amount'] / $bills_total) * 100, 1) : 0;

// Fetch recent payments
$recent_payments = $conn->query("
    SELECT p.id, p.amount, p.payment_method, p.created_at, 
           c.first_name, c.last_name, c.id as customer_id
    FROM payments p
    JOIN customers c ON p.customer_id = c.id
    ORDER BY p.created_at DESC 
    LIMIT 20
");

// Fetch customers for dropdown
$customers = $conn->query("SELECT id, first_name, last_name FROM customers WHERE status='approved' ORDER BY first_name, last_name");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/payments-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <title>Payment Management - Dashboard</title>
</head>

<body>
    <div class="container">
        <!-- Sidebar Section -->
        <aside>
            <div class="toggle">
                <div class="logo">
                    <img src="images/logo.png" alt="Logo">
                    <h2>Pitch<span class="danger">&Roll</span></h2>
                </div>
                <div class="close" id="close-btn">
                    <span class="material-icons-sharp">close</span>
                </div>
            </div>

            <div class="sidebar">
                <a href="admin-dashboard.php">
                    <span class="material-icons-sharp">dashboard</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="../manage-users.php">
                    <span class="material-icons-sharp">person_outline</span>
                    <h3>Users</h3>
                </a>
                <a href="./add-customer.php">
                    <span class="material-icons-sharp">group</span>
                    <h3>Customers</h3>
                </a>
                <a href="./add-bills.php">
                    <span class="material-icons-sharp">receipt_long</span>
                    <h3>Bills</h3>
                </a>
                <a href="./add-payments.php" class="active">
                    <span class="material-icons-sharp">payments</span>
                    <h3>Payments</h3>
                </a>
                <a href="./reports.php">
                    <span class="material-icons-sharp">insights</span>
                    <h3>Reports</h3>
                </a>
                <a href="./reports-bills.php">
                    <span class="material-icons-sharp">description</span>
                    <h3>Bill Reports</h3>
                </a>
                <a href="./profile.php">
                    <span class="material-icons-sharp">settings</span>
                    <h3>Profile</h3>
                </a>
                <a href="../index.php">
                    <span class="material-icons-sharp">home</span>
                    <h3>Main System</h3>
                </a>
                <a href="../logout.php">
                    <span class="material-icons-sharp">logout</span>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>
        <!-- End of Sidebar Section -->

        <!-- Main Content -->
        <main class="payments-main">
            <div class="payments-header">
                <h1>Payment Management</h1>
                <a href="record-payment.php" class="add-payment-btn">
                    <span class="material-icons-sharp">add_circle</span>
                    Record Payment
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <span class="material-icons-sharp">check_circle</span>
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span class="material-icons-sharp">error</span>
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="analyse">
                <div class="sales">
                    <div class="status">
                        <div class="info">
                            <h3>Total Payments</h3>
                            <h1><?php echo $stats['total_payments']; ?></h1>
                            <small>MWK <?php echo number_format($stats['total_amount'], 0); ?></small>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">payments</span>
                        </div>
                    </div>
                </div>
                <div class="visits">
                    <div class="status">
                        <div class="info">
                            <h3>Collection Rate</h3>
                            <h1><?php echo $collection_rate; ?>%</h1>
                            <small>Of Total Bills</small>
                        </div>
                        <div class="progresss">
                            <svg>
                                <circle cx="38" cy="38" r="36"></circle>
                            </svg>
                            <div class="percentage">
                                <p><?php echo $collection_rate; ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="searches">
                    <div class="status">
                        <div class="info">
                            <h3>Average Payment</h3>
                            <h1>MWK <?php echo number_format($stats['avg_amount'], 0); ?></h1>
                            <small>Per Transaction</small>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">analytics</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="payments-table-container">
                <div class="table-header">
                    <h2>All Payments</h2>
                    <div class="search-box">
                        <span class="material-icons-sharp">search</span>
                        <input type="text" id="searchInput" placeholder="Search payments..." onkeyup="searchPayments()">
                    </div>
                </div>

                <div class="table-wrapper">
                    <table id="paymentsTable">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <?php if (isAdmin()): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                                <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="payment-id">#<?php echo str_pad($payment['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                        <td>
                                            <div class="customer-info">
                                                <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><span class="amount success">MWK <?php echo number_format($payment['amount'], 2); ?></span></td>
                                        <td>
                                            <span class="method-badge method-<?php echo $payment['payment_method']; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></td>
                                        <?php if (isAdmin()): ?>
                                            <td>
                                                <div class="action-btns">
                                                    <button class="btn-icon"
                                                        onclick="viewPayment(<?php echo $payment['id']; ?>)"
                                                        title="View Details">
                                                        <span class="material-icons-sharp">visibility</span>
                                                    </button>
                                                    <button class="btn-icon danger"
                                                        onclick="deletePayment(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>')"
                                                        title="Delete">
                                                        <span class="material-icons-sharp">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo isAdmin() ? '6' : '5'; ?>" style="text-align: center; padding: 2rem;">
                                        <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">payments</span>
                                        <p>No payments found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
        <!-- End of Main Content -->

        <!-- Right Section -->
        <div class="right-section">
            <div class="nav">
                <button id="menu-btn">
                    <span class="material-icons-sharp">menu</span>
                </button>
                <div class="dark-mode">
                    <span class="material-icons-sharp active">light_mode</span>
                    <span class="material-icons-sharp">dark_mode</span>
                </div>

                <div class="profile">
                    <div class="info">
                        <p>Hey, <b><?php echo htmlspecialchars($admin_username); ?></b></p>
                        <small class="text-muted"><?php echo isAdmin() ? 'Administrator' : 'User'; ?></small>
                    </div>
                    <div class="profile-photo">
                        <span class="material-icons-sharp" style="font-size: 2.5rem;">account_circle</span>
                    </div>
                </div>
            </div>

            <div class="user-profile">
                <div class="logo">
                    <img src="images/logo.png" alt="Logo">
                    <h2>Pitch&Roll</h2>
                    <p>Payment Management</p>
                </div>
            </div>

            <div class="reminders">
                <div class="header">
                    <h2>Payment Methods</h2>
                    <span class="material-icons-sharp">payment</span>
                </div>

                <?php
                $method_stats->data_seek(0);
                while ($method = $method_stats->fetch_assoc()):
                    $method_name = ucwords(str_replace('_', ' ', $method['payment_method']));
                    $icon_map = [
                        'cash' => 'money',
                        'mobile_money' => 'phone_android',
                        'bank' => 'account_balance',
                        'card' => 'credit_card'
                    ];
                    $icon = $icon_map[$method['payment_method']] ?? 'payment';
                ?>
                    <div class="notification">
                        <div class="icon">
                            <span class="material-icons-sharp"><?php echo $icon; ?></span>
                        </div>
                        <div class="content">
                            <div class="info">
                                <h3><?php echo $method_name; ?></h3>
                                <small class="text_muted">
                                    <?php echo $method['count']; ?> payments -
                                    MWK <?php echo number_format($method['total'], 0); ?>
                                </small>
                            </div>
                            <span class="material-icons-sharp">trending_up</span>
                        </div>
                    </div>
                <?php endwhile; ?>

                <div class="add-reminder">
                    <a href="admin-dashboard.php" style="text-decoration: none; color: inherit;">
                        <div>
                            <span class="material-icons-sharp">dashboard</span>
                            <h3>Back to Dashboard</h3>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('#customer_id').select2({
                placeholder: "Select customer",
                width: '100%'
            });

            // Load customer balance when selected
            $('#customer_id').on('change', function() {
                const customerId = $(this).val();
                if (customerId) {
                    $('#balanceInfo').show();
                    $.ajax({
                        url: '../get-customer-balance.php',
                        type: 'POST',
                        data: {
                            customer_id: customerId
                        },
                        success: function(response) {
                            $('#balanceAmount').html(response);
                        },
                        error: function() {
                            $('#balanceAmount').html('Unable to load balance');
                        }
                    });
                } else {
                    $('#balanceInfo').hide();
                }
            });
        });

        // Search payments
        function searchPayments() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('paymentsTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName('td');

                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }

                tr[i].style.display = found ? '' : 'none';
            }
        }

        // View payment details
        function viewPayment(paymentId) {
            window.location.href = `view-payment.php?id=${paymentId}`;
        }

        // Delete payment
        function deletePayment(paymentId, customerName) {
            if (!confirm(`Delete payment for ${customerName}? This action cannot be undone.`)) return;

            fetch('../payment-actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'delete',
                        payment_id: paymentId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment deleted successfully.');
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
    </script>
</body>

</html>