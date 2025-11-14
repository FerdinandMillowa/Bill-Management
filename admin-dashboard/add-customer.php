<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once '../db-connection.php';
require_once '../auth-helper.php';

// Require any authentication
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
            logActivity('customer_created', "New customer created: $first_name $last_name");
        } else {
            throw new Exception("Error saving customer: " . $conn->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch customer statistics
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_customers,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
    FROM customers
");
$stats = $stats_query->fetch_assoc();

// Fetch recent customers - show ALL to admins, only approved to regular users
if (isAdmin()) {
    $recent_customers = $conn->query("
        SELECT id, first_name, last_name, email, phone, address, status, created_at 
        FROM customers 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
} else {
    $recent_customers = $conn->query("
        SELECT id, first_name, last_name, email, phone, address, status, created_at 
        FROM customers 
        WHERE status='approved' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
}

// Get pending approvals count
$pending_count = $stats['pending_count'];

// Fetch customers by status for the card clicks
$approved_customers = $conn->query("
    SELECT id, first_name, last_name, email, phone, address, status, created_at 
    FROM customers 
    WHERE status='approved' 
    ORDER BY created_at DESC
");

$pending_customers_list = $conn->query("
    SELECT id, first_name, last_name, email, phone, address, status, created_at 
    FROM customers 
    WHERE status='pending' 
    ORDER BY created_at DESC
");

$all_customers = $conn->query("
    SELECT id, first_name, last_name, email, phone, address, status, created_at 
    FROM customers 
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/customers-dashboard.css">
    <title>Customer Management - Dashboard</title>
    <style>
        .main-content-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px);
            min-height: 600px;
            overflow: hidden;
        }

        .content-section {
            flex: 1;
            overflow-y: auto;
            transition: all 0.3s ease;
        }

        .section-hidden {
            display: none;
        }

        .section-visible {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .back-to-form-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            margin: 1rem 0 2rem 0;
            transition: all 0.3s ease;
            width: fit-content;
        }

        .back-to-form-btn:hover {
            background: var(--color-success);
            transform: translateY(-2px);
        }

        .customer-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--color-light);
        }

        .user-count-badge {
            background: var(--color-primary);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stats-card-clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .stats-card-clickable:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
    </style>
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
                <a href="./add-customer.php" class="active">
                    <span class="material-icons-sharp">group</span>
                    <h3>Customers</h3>
                    <?php if ($pending_count > 0): ?>
                        <span class="message-count"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="./add-bills.php">
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
        <main>
            <h1>Customer Management</h1>

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

            <div class="main-content-container">
                <!-- Statistics Cards -->
                <div class="analyse">
                    <div class="sales stats-card-clickable" onclick="showCustomerList('all')">
                        <div class="status">
                            <div class="info">
                                <h3>Total Customers</h3>
                                <h1><?php echo $stats['total_customers']; ?></h1>
                            </div>
                            <div class="progresss">
                                <span class="material-icons-sharp">groups</span>
                            </div>
                        </div>
                    </div>
                    <div class="visits stats-card-clickable" onclick="showCustomerList('approved')">
                        <div class="status">
                            <div class="info">
                                <h3>Approved</h3>
                                <h1><?php echo $stats['approved_count']; ?></h1>
                            </div>
                            <div class="progresss">
                                <span class="material-icons-sharp">check_circle</span>
                            </div>
                        </div>
                    </div>
                    <div class="searches stats-card-clickable" onclick="showCustomerList('pending')">
                        <div class="status">
                            <div class="info">
                                <h3>Pending Approval</h3>
                                <h1><?php echo $stats['pending_count']; ?></h1>
                            </div>
                            <div class="progresss">
                                <span class="material-icons-sharp">pending</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Customer Form Section -->
                <div id="addCustomerForm" class="content-section section-visible">
                    <div class="customer-form-container">
                        <h2>Add New Customer</h2>
                        <form class="customer-form" method="POST" action="">
                            <?php echo getFormTokenField(); ?>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>
                                        <input type="text" name="first_name" required placeholder=" "
                                            value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                        <span>First Name *</span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <input type="text" name="last_name" required placeholder=" "
                                            value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                        <span>Last Name *</span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <input type="email" name="email" required placeholder=" "
                                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                        <span>Email Address *</span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <input type="tel" name="phone" required placeholder=" "
                                            pattern="^(?:\+265|0)\d{9}$"
                                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                        <span>Phone Number *</span>
                                    </label>
                                    <small class="helper-text">Format: +265XXXXXXXXX or 0XXXXXXXXX</small>
                                </div>

                                <div class="form-group full-width">
                                    <label>
                                        <input type="text" name="address" placeholder=" "
                                            value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                                        <span>Address (Optional)</span>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="submit-btn">
                                <span class="material-icons-sharp">person_add</span>
                                Add Customer
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Customer Lists Sections -->
                <div id="allCustomersList" class="content-section section-hidden">
                    <button class="back-to-form-btn" onclick="showAddCustomerForm()">
                        <span class="material-icons-sharp">arrow_back</span>
                        Back to Add Customer Form
                    </button>
                    <div class="customer-list-header">
                        <h2>All Customers</h2>
                        <span class="customer-count-badge"><?php echo $stats['total_customers']; ?> Customers</span>
                    </div>
                    <div class="recent-orders">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Date Added</th>
                                    <?php if (isAdmin()): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_customers && $all_customers->num_rows > 0): ?>
                                    <?php while ($customer = $all_customers->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="customer-info">
                                                    <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="contact-info">
                                                    <span><span class="material-icons-sharp small-icon">email</span> <?php echo htmlspecialchars($customer['email']); ?></span>
                                                    <span><span class="material-icons-sharp small-icon">phone</span> <?php echo htmlspecialchars($customer['phone']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($customer['address'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $customer['status']; ?>">
                                                    <?php echo ucfirst($customer['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                            <?php if (isAdmin()): ?>
                                                <td>
                                                    <div class="action-btns">
                                                        <?php if ($customer['status'] === 'pending'): ?>
                                                            <button class="btn-icon success"
                                                                onclick="approveCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>')"
                                                                title="Approve">
                                                                <span class="material-icons-sharp">check</span>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn-icon"
                                                            onclick="editCustomer(<?php echo $customer['id']; ?>)"
                                                            title="Edit">
                                                            <span class="material-icons-sharp">edit</span>
                                                        </button>
                                                        <button class="btn-icon danger"
                                                            onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>')"
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
                                            <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">group</span>
                                            <p>No customers found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="approvedCustomersList" class="content-section section-hidden">
                    <button class="back-to-form-btn" onclick="showAddCustomerForm()">
                        <span class="material-icons-sharp">arrow_back</span>
                        Back to Add Customer Form
                    </button>
                    <div class="customer-list-header">
                        <h2>Approved Customers</h2>
                        <span class="customer-count-badge"><?php echo $stats['approved_count']; ?> Customers</span>
                    </div>
                    <div class="recent-orders">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Date Added</th>
                                    <?php if (isAdmin()): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($approved_customers && $approved_customers->num_rows > 0): ?>
                                    <?php while ($customer = $approved_customers->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="customer-info">
                                                    <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="contact-info">
                                                    <span><span class="material-icons-sharp small-icon">email</span> <?php echo htmlspecialchars($customer['email']); ?></span>
                                                    <span><span class="material-icons-sharp small-icon">phone</span> <?php echo htmlspecialchars($customer['phone']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($customer['address'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $customer['status']; ?>">
                                                    <?php echo ucfirst($customer['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                            <?php if (isAdmin()): ?>
                                                <td>
                                                    <div class="action-btns">
                                                        <button class="btn-icon"
                                                            onclick="editCustomer(<?php echo $customer['id']; ?>)"
                                                            title="Edit">
                                                            <span class="material-icons-sharp">edit</span>
                                                        </button>
                                                        <button class="btn-icon danger"
                                                            onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>')"
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
                                            <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">group</span>
                                            <p>No approved customers found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="pendingCustomersList" class="content-section section-hidden">
                    <button class="back-to-form-btn" onclick="showAddCustomerForm()">
                        <span class="material-icons-sharp">arrow_back</span>
                        Back to Add Customer Form
                    </button>
                    <div class="customer-list-header">
                        <h2>Pending Approval Customers</h2>
                        <span class="customer-count-badge"><?php echo $stats['pending_count']; ?> Customers</span>
                    </div>
                    <div class="recent-orders">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Date Added</th>
                                    <?php if (isAdmin()): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pending_customers_list && $pending_customers_list->num_rows > 0): ?>
                                    <?php while ($customer = $pending_customers_list->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="customer-info">
                                                    <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="contact-info">
                                                    <span><span class="material-icons-sharp small-icon">email</span> <?php echo htmlspecialchars($customer['email']); ?></span>
                                                    <span><span class="material-icons-sharp small-icon">phone</span> <?php echo htmlspecialchars($customer['phone']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($customer['address'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $customer['status']; ?>">
                                                    <?php echo ucfirst($customer['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                            <?php if (isAdmin()): ?>
                                                <td>
                                                    <div class="action-btns">
                                                        <button class="btn-icon success"
                                                            onclick="approveCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>')"
                                                            title="Approve">
                                                            <span class="material-icons-sharp">check</span>
                                                        </button>
                                                        <button class="btn-icon"
                                                            onclick="editCustomer(<?php echo $customer['id']; ?>)"
                                                            title="Edit">
                                                            <span class="material-icons-sharp">edit</span>
                                                        </button>
                                                        <button class="btn-icon danger"
                                                            onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>')"
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
                                            <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">group</span>
                                            <p>No pending customers found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
                    <p>Customer Management</p>
                </div>
            </div>

            <div class="reminders">
                <div class="header">
                    <h2>Quick Stats</h2>
                    <span class="material-icons-sharp">insights</span>
                </div>

                <div class="notification">
                    <div class="icon">
                        <span class="material-icons-sharp">groups</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Total Customers</h3>
                            <small class="text_muted"><?php echo $stats['total_customers']; ?> registered</small>
                        </div>
                        <span class="material-icons-sharp">arrow_forward</span>
                    </div>
                </div>

                <?php if (isAdmin() && $pending_count > 0): ?>
                    <div class="notification">
                        <div class="icon" style="background-color: var(--color-warning);">
                            <span class="material-icons-sharp">pending</span>
                        </div>
                        <div class="content">
                            <div class="info">
                                <h3>Pending Approvals</h3>
                                <small class="text_muted"><?php echo $pending_count; ?> awaiting approval</small>
                            </div>
                            <button onclick="showCustomerList('pending')" style="background: none; border: none; cursor: pointer; color: var(--color-warning);">
                                <span class="material-icons-sharp">arrow_forward</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="notification">
                    <div class="icon" style="background-color: var(--color-success);">
                        <span class="material-icons-sharp">check_circle</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Approved</h3>
                            <small class="text_muted"><?php echo $stats['approved_count']; ?> active customers</small>
                        </div>
                        <span class="material-icons-sharp">trending_up</span>
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

    <!-- Include Admin Auth Modal -->
    <?php include '../admin-auth-modal.php'; ?>

    <script src="js/admin-dashboard.js"></script>
    <script>
        // Function to show customer list based on status
        function showCustomerList(status) {
            // Hide all sections first
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('section-visible');
                section.classList.add('section-hidden');
            });

            // Show the selected section
            const targetSection = document.getElementById(status + 'CustomersList');
            if (targetSection) {
                targetSection.classList.remove('section-hidden');
                targetSection.classList.add('section-visible');

                // Scroll to the top of the section
                targetSection.scrollTop = 0;
            }
        }

        // Function to show add customer form
        function showAddCustomerForm() {
            // Hide all sections first
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('section-visible');
                section.classList.add('section-hidden');
            });

            // Show the add customer form
            document.getElementById('addCustomerForm').classList.remove('section-hidden');
            document.getElementById('addCustomerForm').classList.add('section-visible');

            // Scroll to the top of the form
            document.getElementById('addCustomerForm').scrollTop = 0;
        }

        // Filter customers by status in the table
        function filterCustomers(status) {
            const rows = document.querySelectorAll('#customersTable tbody tr');
            const tabs = document.querySelectorAll('.tab-btn');

            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.closest('.tab-btn').classList.add('active');

            // Filter rows
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Approve customer
        function approveCustomer(customerId, customerName) {
            if (!confirm(`Approve ${customerName}?`)) return;

            fetch('../verify-admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'approve_customer',
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
                        alert(`${customerName} approved successfully!`);
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

        // Edit customer
        function editCustomer(customerId) {
            window.location.href = `edit-customer.php?id=${customerId}`;
        }

        // Delete customer
        function deleteCustomer(customerId, customerName) {
            if (!confirm(`Delete ${customerName}? This action cannot be undone.`)) return;

            fetch('../verify-admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
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
                        alert(`${customerName} deleted successfully.`);
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