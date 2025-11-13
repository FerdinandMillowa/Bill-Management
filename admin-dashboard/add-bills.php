<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// database connection and security
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

        // Check customer exists, is approved, and get their name
        $check = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM customers WHERE id=? AND status='approved'");
        $check->bind_param("i", $customer_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows == 0) {
            throw new Exception("Selected customer is not approved or doesn't exist.");
        }

        $customer_data = $check_result->fetch_assoc();
        $bill_name = $customer_data['full_name']; // Use customer's full name as bill_name
        $check->close();

        // Insert bill with bill_name (customer's name)
        $stmt = $conn->prepare("INSERT INTO bills (customer_id, bill_name, amount, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $customer_id, $bill_name, $amount, $description);

        if ($stmt->execute()) {
            $success = "Bill added successfully!";
            logActivity('bill_created', "New bill created for $bill_name - MWK " . number_format($amount, 2));
        } else {
            throw new Exception("Error saving bill: " . $conn->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get bill statistics
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_bills,
        COALESCE(SUM(amount), 0) as total_amount,
        COALESCE(AVG(amount), 0) as avg_amount
    FROM bills
");
$stats = $stats_query->fetch_assoc();

// Get total payments for comparison
$payments_total = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments")->fetch_assoc()['total'];
$outstanding = $stats['total_amount'] - $payments_total;

// Calculate collection rate
$collection_rate = $stats['total_amount'] > 0 ? min(round(($payments_total / $stats['total_amount']) * 100, 1), 100) : 0;

// Fetch recent bills
$recent_bills = $conn->query("
    SELECT b.id, b.bill_name, b.amount, b.description, b.created_at, 
           c.first_name, c.last_name, c.id as customer_id
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    ORDER BY b.created_at DESC 
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
    <link rel="stylesheet" href="css/bills-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <title>Bill Management - Dashboard</title>
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
                <a href="./manage-users-dashboard.php">
                    <span class="material-icons-sharp">person_outline</span>
                    <h3>Users</h3>
                </a>
                <a href="./add-customer.php">
                    <span class="material-icons-sharp">group</span>
                    <h3>Customers</h3>
                </a>
                <a href="./add-bills.php" class="active">
                    <span class="material-icons-sharp">receipt_long</span>
                    <h3>Bills</h3>
                </a>
                <a href="./add-payments.php">
                    <span class="material-icons-sharp">payments</span>
                    <h3>Payments</h3>
                </a>
                <a href="./reports.php">
                    <span class="material-icons-sharp">insights</span>
                    <h3>Reports</h3>
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
        <main class="bills-main">
            <div class="bills-header">
                <h1>Bill Management</h1>
                <a href="create-bill.php" class="add-bill-btn">
                    <span class="material-icons-sharp">add_circle</span>
                    Create Bill
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
                            <h3>Total Bills</h3>
                            <h1><?php echo $stats['total_bills']; ?></h1>
                            <small>MWK <?php echo number_format($stats['total_amount'], 0); ?></small>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">receipt_long</span>
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
                            <h3>Outstanding</h3>
                            <h1>MWK <?php echo number_format(abs($outstanding), 0); ?></h1>
                            <small><?php echo $outstanding >= 0 ? 'To Collect' : 'Overpaid'; ?></small>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">account_balance_wallet</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bills Table -->
            <div class="bills-table-container">
                <div class="table-header">
                    <h2>All Bills</h2>
                    <div class="search-box">
                        <span class="material-icons-sharp">search</span>
                        <input type="text" id="searchInput" placeholder="Search bills..." onkeyup="searchBills()">
                    </div>
                </div>

                <div class="table-wrapper">
                    <table id="billsTable">
                        <thead>
                            <tr>
                                <th>Bill ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Date Created</th>
                                <?php if (isAdmin()): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_bills && $recent_bills->num_rows > 0): ?>
                                <?php while ($bill = $recent_bills->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="bill-id">#<?php echo str_pad($bill['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                        <td>
                                            <div class="customer-info">
                                                <strong><?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?></strong>
                                                <small><?php echo htmlspecialchars($bill['bill_name']); ?></small>
                                            </div>
                                        </td>
                                        <td><span class="amount">MWK <?php echo number_format($bill['amount'], 2); ?></span></td>
                                        <td><?php echo htmlspecialchars($bill['description']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($bill['created_at'])); ?></td>
                                        <?php if (isAdmin()): ?>
                                            <td>
                                                <div class="action-btns">
                                                    <button class="btn-icon"
                                                        onclick="viewBill(<?php echo $bill['id']; ?>)"
                                                        title="View Details">
                                                        <span class="material-icons-sharp">visibility</span>
                                                    </button>
                                                    <button class="btn-icon warning"
                                                        onclick="editBill(<?php echo $bill['id']; ?>)"
                                                        title="Edit">
                                                        <span class="material-icons-sharp">edit</span>
                                                    </button>
                                                    <button class="btn-icon danger"
                                                        onclick="deleteBill(<?php echo $bill['id']; ?>, '<?php echo htmlspecialchars($bill['bill_name']); ?>')"
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
                                        <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">receipt_long</span>
                                        <p>No bills found</p>
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
                    <p>Bill Management</p>
                </div>
            </div>

            <div class="reminders">
                <div class="header">
                    <h2>Quick Stats</h2>
                    <span class="material-icons-sharp">insights</span>
                </div>

                <div class="notification">
                    <div class="icon">
                        <span class="material-icons-sharp">receipt</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Total Revenue</h3>
                            <small class="text_muted">MWK <?php echo number_format($stats['total_amount'], 0); ?></small>
                        </div>
                        <span class="material-icons-sharp">trending_up</span>
                    </div>
                </div>

                <div class="notification">
                    <div class="icon" style="background-color: var(--color-warning);">
                        <span class="material-icons-sharp">account_balance_wallet</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Outstanding</h3>
                            <small class="text_muted">MWK <?php echo number_format(abs($outstanding), 0); ?></small>
                        </div>
                        <span class="material-icons-sharp">arrow_forward</span>
                    </div>
                </div>

                <div class="notification">
                    <div class="icon" style="background-color: var(--color-primary);">
                        <span class="material-icons-sharp">calculate</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Average Bill</h3>
                            <small class="text_muted">MWK <?php echo number_format($stats['avg_amount'], 0); ?></small>
                        </div>
                        <span class="material-icons-sharp">info</span>
                    </div>
                </div>

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
        // Initialize Select2 with better configuration
        $(document).ready(function() {
            $('#customer_id').select2({
                placeholder: "Select customer",
                width: '100%',
                dropdownParent: $('.bill-form-container'),
                dropdownPosition: 'below'
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.select2-container').length) {
                    $('#customer_id').select2('close');
                }
            });
        });

        // Search bills
        function searchBills() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('billsTable');
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

        // View bill details
        function viewBill(billId) {
            window.location.href = `view-bill.php?id=${billId}`;
        }

        // Edit bill
        function editBill(billId) {
            window.location.href = `edit-bill.php?id=${billId}`;
        }

        // Delete bill
        function deleteBill(billId, billName) {
            if (!confirm(`Delete bill for ${billName}? This action cannot be undone.`)) return;

            fetch('../bill-actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'delete',
                        bill_id: billId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Bill deleted successfully.');
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