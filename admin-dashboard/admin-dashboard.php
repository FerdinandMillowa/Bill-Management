<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once '../db-connection.php';
require_once '../auth-helper.php';

// Only admins can access dashboard
requireAdminAuth();

// Get current admin info
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$admin_id = $_SESSION['user_id'];

// Fetch dashboard statistics
// 1. Total Revenue (Total Bills)
$total_bills_query = $conn->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM bills");
$total_bills_data = $total_bills_query->fetch_assoc();
$total_revenue = $total_bills_data['total'];
$total_bills_count = $total_bills_data['count'];

// 2. Total Payments
$total_payments_query = $conn->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM payments");
$total_payments_data = $total_payments_query->fetch_assoc();
$total_payments = $total_payments_data['total'];
$total_payments_count = $total_payments_data['count'];

// 3. Outstanding Balance
$outstanding_balance = $total_revenue - $total_payments;

// 4. Customer Statistics
$customers_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM customers
");
$customers_data = $customers_query->fetch_assoc();
$total_customers = $customers_data['total'];
$approved_customers = $customers_data['approved'];
$pending_customers = $customers_data['pending'];

// 5. Calculate percentages for circular progress
$collection_rate = $total_revenue > 0 ? round(($total_payments / $total_revenue) * 100, 1) : 0;
$approval_rate = $total_customers > 0 ? round(($approved_customers / $total_customers) * 100, 1) : 0;

// Calculate growth percentages (compare with last month)
$current_month_bills = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM bills 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetch_assoc()['total'];

$last_month_bills = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM bills 
    WHERE MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
")->fetch_assoc()['total'];

$revenue_growth = $last_month_bills > 0 ? round((($current_month_bills - $last_month_bills) / $last_month_bills) * 100, 1) : 0;

// Fetch recent customers (last 4)
$recent_customers = $conn->query("
    SELECT id, first_name, last_name, created_at, status
    FROM customers 
    ORDER BY created_at DESC 
    LIMIT 4
");

// Fetch recent bills (last 5)
$recent_bills = $conn->query("
    SELECT b.id, b.amount, b.description, b.created_at,
           c.first_name, c.last_name
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    ORDER BY b.created_at DESC
    LIMIT 5
");

// Fetch recent payments (last 5)
$recent_payments = $conn->query("
    SELECT p.id, p.amount, p.payment_method, p.created_at,
           c.first_name, c.last_name
    FROM payments p
    JOIN customers c ON p.customer_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 5
");

// Fetch pending approvals
$pending_approvals = $conn->query("
    SELECT id, first_name, last_name, email, phone, created_at
    FROM customers
    WHERE status = 'pending'
    ORDER BY created_at DESC
    LIMIT 5
");

// Get monthly revenue data for chart (last 6 months)
$monthly_data = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month,
        COALESCE(SUM(amount), 0) as total
    FROM bills
    WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");

$chart_labels = [];
$chart_data = [];
// while ($row = $monthly_data->fetch_assoc()) {
//     $chart_labels[] = $row['month'];
//     $chart_data[] = $row['total'];
// }

// Get payment methods distribution
$payment_methods = $conn->query("
    SELECT payment_method, COUNT(*) as count
    FROM payments
    GROUP BY payment_method
");

$payment_methods_labels = [];
$payment_methods_data = [];
while ($row = $payment_methods->fetch_assoc()) {
    $payment_methods_labels[] = ucwords(str_replace('_', ' ', $row['payment_method']));
    $payment_methods_data[] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <title>Admin Dashboard - Bill Management System</title>
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
                <a href="admin-dashboard.php" class="active">
                    <span class="material-icons-sharp">dashboard</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="../manage-users.php">
                    <span class="material-icons-sharp">person_outline</span>
                    <h3>Users</h3>
                </a>
                <a href="../add-customer.php">
                    <span class="material-icons-sharp">group</span>
                    <h3>Customers</h3>
                    <?php if ($pending_customers > 0): ?>
                        <span class="message-count"><?php echo $pending_customers; ?></span>
                    <?php endif; ?>
                </a>
                <a href="../add-bills.php">
                    <span class="material-icons-sharp">receipt_long</span>
                    <h3>Bills</h3>
                </a>
                <a href="../add-payments.php">
                    <span class="material-icons-sharp">payments</span>
                    <h3>Payments</h3>
                </a>
                <a href="../reports.php">
                    <span class="material-icons-sharp">insights</span>
                    <h3>Reports</h3>
                </a>
                <a href="../reports-bills.php">
                    <span class="material-icons-sharp">description</span>
                    <h3>Bill Reports</h3>
                </a>
                <a href="../profile.php">
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
            <h1>Dashboard Overview</h1>

            <!-- Analyses -->
            <div class="analyse">
                <div class="sales">
                    <div class="status">
                        <div class="info">
                            <h3>Total Revenue</h3>
                            <h1>MWK <?php echo number_format($total_revenue, 2); ?></h1>
                            <small><?php echo $total_bills_count; ?> Bills</small>
                        </div>
                        <div class="progresss">
                            <svg>
                                <circle cx="38" cy="38" r="36"></circle>
                            </svg>
                            <div class="percentage">
                                <p><?php echo $revenue_growth >= 0 ? '+' : ''; ?><?php echo $revenue_growth; ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="visits">
                    <div class="status">
                        <div class="info">
                            <h3>Outstanding</h3>
                            <h1>MWK <?php echo number_format($outstanding_balance, 2); ?></h1>
                            <small><?php echo $collection_rate; ?>% Collected</small>
                        </div>
                        <div class="progresss">
                            <svg>
                                <circle cx="38" cy="38" r="36"></circle>
                            </svg>
                            <div class="percentage">
                                <p><?php echo 100 - $collection_rate; ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="searches">
                    <div class="status">
                        <div class="info">
                            <h3>Total Customers</h3>
                            <h1><?php echo $total_customers; ?></h1>
                            <small><?php echo $approved_customers; ?> Approved</small>
                        </div>
                        <div class="progresss">
                            <svg>
                                <circle cx="38" cy="38" r="36"></circle>
                            </svg>
                            <div class="percentage">
                                <p><?php echo $approval_rate; ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End of Analyses -->

            <!-- Charts Section -->
            <div class="charts">
                <div class="chart-container">
                    <h2>Revenue Trend (Last 6 Months)</h2>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-container">
                    <h2>Payment Methods Distribution</h2>
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
            <!-- End of Charts -->

            <!-- New Customers Section -->
            <div class="new-users">
                <h2>Recent Customers</h2>
                <div class="user-list">
                    <?php
                    $customer_count = 0;
                    while ($customer = $recent_customers->fetch_assoc()):
                        if ($customer_count >= 3) break;
                        $customer_count++;
                        $time_ago = time_elapsed_string($customer['created_at']);
                    ?>
                        <div class="user">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($customer['first_name'], 0, 1)); ?>
                            </div>
                            <h2><?php echo htmlspecialchars($customer['first_name']); ?></h2>
                            <p><?php echo $time_ago; ?></p>
                        </div>
                    <?php endwhile; ?>
                    <div class="user">
                        <a href="../add-customer.php" style="text-decoration: none; color: inherit;">
                            <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-primary);">add_circle</span>
                            <h2>Add New</h2>
                            <p>Customer</p>
                        </a>
                    </div>
                </div>
            </div>
            <!-- End of New Customers Section -->

            <!-- Recent Activity Tables with Tabs -->
            <div class="recent-orders">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('bills')">
                        <span class="material-icons-sharp">receipt</span> Recent Bills
                    </button>
                    <button class="tab-btn" onclick="switchTab('payments')">
                        <span class="material-icons-sharp">payments</span> Recent Payments
                    </button>
                    <button class="tab-btn" onclick="switchTab('approvals')">
                        <span class="material-icons-sharp">pending</span> Pending Approvals
                        <?php if ($pending_customers > 0): ?>
                            <span class="tab-badge"><?php echo $pending_customers; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Bills Tab -->
                <div id="bills-tab" class="tab-content active">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_bills->data_seek(0);
                            while ($bill = $recent_bills->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($bill['description'], 0, 30)) . '...'; ?></td>
                                    <td>MWK <?php echo number_format($bill['amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($bill['created_at'])); ?></td>
                                    <td class="primary">
                                        <a href="../add-bills.php" style="color: var(--color-primary);">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <a href="../add-bills.php">Show All Bills</a>
                </div>

                <!-- Payments Tab -->
                <div id="payments-tab" class="tab-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_payments->data_seek(0);
                            while ($payment = $recent_payments->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                    <td class="success">MWK <?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                    <td class="primary">
                                        <a href="../add-payments.php" style="color: var(--color-primary);">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <a href="../add-payments.php">Show All Payments</a>
                </div>

                <!-- Approvals Tab -->
                <div id="approvals-tab" class="tab-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $pending_approvals->data_seek(0);
                            if ($pending_approvals->num_rows > 0):
                                while ($approval = $pending_approvals->fetch_assoc()):
                            ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($approval['email']); ?></td>
                                        <td><?php echo htmlspecialchars($approval['phone']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($approval['created_at'])); ?></td>
                                        <td class="warning">
                                            <a href="../add-customer.php" style="color: var(--color-warning);">Approve</a>
                                        </td>
                                    </tr>
                                <?php
                                endwhile;
                            else:
                                ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem;">
                                        <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-success);">check_circle</span>
                                        <p>No pending approvals</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <a href="../add-customer.php">Show All Customers</a>
                </div>
            </div>
            <!-- End of Recent Activity -->

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
                        <small class="text-muted">Administrator</small>
                    </div>
                    <div class="profile-photo">
                        <span class="material-icons-sharp" style="font-size: 2.5rem;">account_circle</span>
                    </div>
                </div>
            </div>
            <!-- End of Nav -->

            <div class="user-profile">
                <div class="logo">
                    <img src="images/logo.png" alt="Logo">
                    <h2>Pitch&Roll</h2>
                    <p>Bill Management System</p>
                </div>
            </div>

            <div class="reminders">
                <div class="header">
                    <h2>Quick Stats</h2>
                    <span class="material-icons-sharp">insert_chart</span>
                </div>

                <div class="notification">
                    <div class="icon">
                        <span class="material-icons-sharp">attach_money</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Total Payments</h3>
                            <small class="text_muted">MWK <?php echo number_format($total_payments, 0); ?></small>
                        </div>
                        <span class="material-icons-sharp">trending_up</span>
                    </div>
                </div>

                <div class="notification">
                    <div class="icon" style="background-color: var(--color-warning);">
                        <span class="material-icons-sharp">pending</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Pending Approvals</h3>
                            <small class="text_muted"><?php echo $pending_customers; ?> Customers</small>
                        </div>
                        <a href="../add-customer.php" style="color: var(--color-warning); text-decoration: none;">
                            <span class="material-icons-sharp">arrow_forward</span>
                        </a>
                    </div>
                </div>

                <div class="notification">
                    <div class="icon" style="background-color: var(--color-primary);">
                        <span class="material-icons-sharp">groups</span>
                    </div>
                    <div class="content">
                        <div class="info">
                            <h3>Active Customers</h3>
                            <small class="text_muted"><?php echo $approved_customers; ?> Approved</small>
                        </div>
                        <span class="material-icons-sharp">check_circle</span>
                    </div>
                </div>

                <div class="add-reminder">
                    <a href="../add-customer.php" style="text-decoration: none; color: inherit;">
                        <div>
                            <span class="material-icons-sharp">add</span>
                            <h3>Add Customer</h3>
                        </div>
                    </a>
                </div>

            </div>

        </div>

    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Chart.js Configuration
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartData = <?php echo json_encode($chart_data); ?>;
        const paymentMethodsLabels = <?php echo json_encode($payment_methods_labels); ?>;
        const paymentMethodsData = <?php echo json_encode($payment_methods_data); ?>;

        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Revenue (MWK)',
                    data: chartData,
                    borderColor: '#1B9C85',
                    backgroundColor: 'rgba(27, 156, 133, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentMethodsChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentMethodsLabels,
                datasets: [{
                    data: paymentMethodsData,
                    backgroundColor: [
                        '#1B9C85',
                        '#6C9BCF',
                        '#F7D060',
                        '#FF0060'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>

</html>

<?php
// Helper function for time ago
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'min',
        's' => 'sec',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>