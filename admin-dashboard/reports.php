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

// Get filter parameters
$report_type = $_GET['type'] ?? 'bills'; // bills or payments
$filter_period = $_GET['period'] ?? 'all'; // all, today, weekly, biweekly, monthly, custom
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build date filter SQL
$date_filter = "";
$date_params = [];

switch ($filter_period) {
    case 'today':
        $date_filter = "DATE(b.created_at) = CURDATE()";
        break;
    case 'weekly':
        $date_filter = "DATE(b.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'biweekly':
        $date_filter = "DATE(b.created_at) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)";
        break;
    case 'monthly':
        $date_filter = "DATE(b.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'custom':
        if ($start_date && $end_date) {
            $date_filter = "DATE(b.created_at) BETWEEN ? AND ?";
            $date_params = [$start_date, $end_date];
        }
        break;
    default:
        $date_filter = "1=1"; // All records
}

// Fetch Bills Report
if (!empty($date_params)) {
    // Use prepared statement for custom date range
    $bills_query = "
        SELECT b.id, b.amount, b.description, b.created_at,
               c.first_name, c.last_name
        FROM bills b
        JOIN customers c ON b.customer_id = c.id
        WHERE DATE(b.created_at) BETWEEN ? AND ?
        ORDER BY b.created_at DESC
    ";
    $bills_stmt = $conn->prepare($bills_query);
    if ($bills_stmt) {
        $bills_stmt->bind_param("ss", $date_params[0], $date_params[1]);
        $bills_stmt->execute();
        $bills_result = $bills_stmt->get_result();
    } else {
        $bills_result = false;
    }
} else {
    // Direct query for other filters
    $bills_query = "
        SELECT b.id, b.amount, b.description, b.created_at,
               c.first_name, c.last_name
        FROM bills b
        JOIN customers c ON b.customer_id = c.id
        WHERE $date_filter
        ORDER BY b.created_at DESC
    ";
    $bills_result = $conn->query($bills_query);
}

// Calculate bills statistics
if (!empty($date_params)) {
    $bills_stats_query = "
        SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(AVG(amount), 0) as avg_amount,
            COALESCE(MIN(amount), 0) as min_amount,
            COALESCE(MAX(amount), 0) as max_amount
        FROM bills b
        WHERE DATE(b.created_at) BETWEEN ? AND ?
    ";
    $bills_stats_stmt = $conn->prepare($bills_stats_query);
    if ($bills_stats_stmt) {
        $bills_stats_stmt->bind_param("ss", $date_params[0], $date_params[1]);
        $bills_stats_stmt->execute();
        $bills_stats = $bills_stats_stmt->get_result()->fetch_assoc();
    } else {
        $bills_stats = ['total_count' => 0, 'total_amount' => 0, 'avg_amount' => 0, 'min_amount' => 0, 'max_amount' => 0];
    }
} else {
    $bills_stats_query = "
        SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(AVG(amount), 0) as avg_amount,
            COALESCE(MIN(amount), 0) as min_amount,
            COALESCE(MAX(amount), 0) as max_amount
        FROM bills b
        WHERE $date_filter
    ";
    $result = $conn->query($bills_stats_query);
    $bills_stats = $result ? $result->fetch_assoc() : ['total_count' => 0, 'total_amount' => 0, 'avg_amount' => 0, 'min_amount' => 0, 'max_amount' => 0];
}

// Fetch Payments Report
if (!empty($date_params)) {
    $payments_query = "
        SELECT p.id, p.amount, p.payment_method, p.created_at,
               c.first_name, c.last_name
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at DESC
    ";
    $payments_stmt = $conn->prepare($payments_query);
    if ($payments_stmt) {
        $payments_stmt->bind_param("ss", $date_params[0], $date_params[1]);
        $payments_stmt->execute();
        $payments_result = $payments_stmt->get_result();
    } else {
        $payments_result = false;
    }
} else {
    $payments_query = "
        SELECT p.id, p.amount, p.payment_method, p.created_at,
               c.first_name, c.last_name
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        WHERE DATE(p.created_at) >= DATE_SUB(CURDATE(), INTERVAL 
            CASE 
                WHEN '$filter_period' = 'today' THEN 0
                WHEN '$filter_period' = 'weekly' THEN 7
                WHEN '$filter_period' = 'biweekly' THEN 14
                WHEN '$filter_period' = 'monthly' THEN 30
                ELSE 36500
            END DAY)
        " . ($filter_period === 'today' ? "AND DATE(p.created_at) = CURDATE()" : "") . "
        ORDER BY p.created_at DESC
    ";
    $payments_result = $conn->query($payments_query);
}

// Calculate payments statistics
if (!empty($date_params)) {
    $payments_stats_query = "
        SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(AVG(amount), 0) as avg_amount,
            COALESCE(MIN(amount), 0) as min_amount,
            COALESCE(MAX(amount), 0) as max_amount
        FROM payments p
        WHERE DATE(p.created_at) BETWEEN ? AND ?
    ";
    $payments_stats_stmt = $conn->prepare($payments_stats_query);
    if ($payments_stats_stmt) {
        $payments_stats_stmt->bind_param("ss", $date_params[0], $date_params[1]);
        $payments_stats_stmt->execute();
        $payments_stats = $payments_stats_stmt->get_result()->fetch_assoc();
    } else {
        $payments_stats = ['total_count' => 0, 'total_amount' => 0, 'avg_amount' => 0, 'min_amount' => 0, 'max_amount' => 0];
    }
} else {
    $payments_stats_query = "
        SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(AVG(amount), 0) as avg_amount,
            COALESCE(MIN(amount), 0) as min_amount,
            COALESCE(MAX(amount), 0) as max_amount
        FROM payments p
        WHERE DATE(p.created_at) >= DATE_SUB(CURDATE(), INTERVAL 
            CASE 
                WHEN '$filter_period' = 'today' THEN 0
                WHEN '$filter_period' = 'weekly' THEN 7
                WHEN '$filter_period' = 'biweekly' THEN 14
                WHEN '$filter_period' = 'monthly' THEN 30
                ELSE 36500
            END DAY)
        " . ($filter_period === 'today' ? "AND DATE(p.created_at) = CURDATE()" : "") . "
    ";
    $result = $conn->query($payments_stats_query);
    $payments_stats = $result ? $result->fetch_assoc() : ['total_count' => 0, 'total_amount' => 0, 'avg_amount' => 0, 'min_amount' => 0, 'max_amount' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/reports-dashboard.css">
    <title>Reports - Dashboard</title>
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
                <a href="./add-bills.php">
                    <span class="material-icons-sharp">receipt_long</span>
                    <h3>Bills</h3>
                </a>
                <a href="./add-payments.php">
                    <span class="material-icons-sharp">payments</span>
                    <h3>Payments</h3>
                </a>
                <a href="./reports.php" class="active">
                    <span class="material-icons-sharp">insights</span>
                    <h3>Reports</h3>
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
        <main class="reports-main">
            <div class="reports-header">
                <h1>Financial Reports</h1>

                <!-- Filter Controls -->
                <div class="filter-controls">
                    <div class="filter-group">
                        <label>Period:</label>
                        <select id="periodFilter" onchange="applyFilter()">
                            <option value="all" <?php echo $filter_period === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $filter_period === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="weekly" <?php echo $filter_period === 'weekly' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="biweekly" <?php echo $filter_period === 'biweekly' ? 'selected' : ''; ?>>Last 14 Days</option>
                            <option value="monthly" <?php echo $filter_period === 'monthly' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="custom" <?php echo $filter_period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>

                    <div class="date-range" id="dateRange" style="display: <?php echo $filter_period === 'custom' ? 'flex' : 'none'; ?>;">
                        <input type="date" id="startDate" value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        <span>to</span>
                        <input type="date" id="endDate" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        <button onclick="applyFilter()" class="apply-btn">Apply</button>
                    </div>

                    <button onclick="exportReport()" class="export-btn">
                        <span class="material-icons-sharp">download</span>
                        Export
                    </button>
                </div>
            </div>

            <!-- Report Tabs -->
            <div class="report-tabs">
                <button class="report-tab-btn <?php echo $report_type === 'bills' ? 'active' : ''; ?>" onclick="switchReport('bills')">
                    <span class="material-icons-sharp">receipt_long</span>
                    Bills Report
                </button>
                <button class="report-tab-btn <?php echo $report_type === 'payments' ? 'active' : ''; ?>" onclick="switchReport('payments')">
                    <span class="material-icons-sharp">payments</span>
                    Payments Report
                </button>
            </div>

            <!-- Bills Report -->
            <div class="report-content <?php echo $report_type === 'bills' ? 'active' : ''; ?>" id="billsReport">
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="material-icons-sharp">receipt</span>
                        <div>
                            <h3><?php echo $bills_stats['total_count']; ?></h3>
                            <p>Total Bills</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons-sharp">attach_money</span>
                        <div>
                            <h3>MWK <?php echo number_format($bills_stats['total_amount'], 2); ?></h3>
                            <p>Total Amount</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons-sharp">trending_up</span>
                        <div>
                            <h3>MWK <?php echo number_format($bills_stats['avg_amount'], 2); ?></h3>
                            <p>Average Bill</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons-sharp">show_chart</span>
                        <div>
                            <h3>MWK <?php echo number_format($bills_stats['min_amount'], 2); ?> - <?php echo number_format($bills_stats['max_amount'], 2); ?></h3>
                            <p>Range</p>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Bill ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Check if $bills_result is valid and has rows
                            if ($bills_result && is_object($bills_result) && $bills_result->num_rows > 0):
                            ?>
                                <?php while ($bill = $bills_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="id-badge">#<?php echo str_pad($bill['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                        <td><?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?></td>
                                        <td><span class="amount">MWK <?php echo number_format($bill['amount'], 2); ?></span></td>
                                        <td><?php echo htmlspecialchars($bill['description']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($bill['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem;">
                                        <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">receipt_long</span>
                                        <p>No bills found for this period</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments Report -->
            <div class="report-content <?php echo $report_type === 'payments' ? 'active' : ''; ?>" id="paymentsReport">
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="material-icons-sharp">payment</span>
                        <div>
                            <h3><?php echo $payments_stats['total_count']; ?></h3>
                            <p>Total Payments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons-sharp">attach_money</span>
                        <div>
                            <h3>MWK <?php echo number_format($payments_stats['total_amount'], 2); ?></h3>
                            <p>Total Amount</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons-sharp">trending_up</span>
                        <div>
                            <h3>MWK <?php echo number_format($payments_stats['avg_amount'], 2); ?></h3>
                            <p>Average Payment</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons-sharp">show_chart</span>
                        <div>
                            <h3>MWK <?php echo number_format($payments_stats['min_amount'], 2); ?> - <?php echo number_format($payments_stats['max_amount'], 2); ?></h3>
                            <p>Range</p>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Check if $payments_result is valid and has rows
                            if ($payments_result && is_object($payments_result) && $payments_result->num_rows > 0):
                            ?>
                                <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="id-badge success">#<?php echo str_pad($payment['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                        <td><span class="amount success">MWK <?php echo number_format($payment['amount'], 2); ?></span></td>
                                        <td>
                                            <span class="method-badge">
                                                <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem;">
                                        <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">payments</span>
                                        <p>No payments found for this period</p>
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
        <div class="right-section reports-sidebar">
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

            <div class="summary-card">
                <h3>Report Summary</h3>
                <div class="summary-item">
                    <span class="material-icons-sharp">date_range</span>
                    <div>
                        <strong>Period</strong>
                        <p><?php
                            echo match ($filter_period) {
                                'today' => 'Today',
                                'weekly' => 'Last 7 Days',
                                'biweekly' => 'Last 14 Days',
                                'monthly' => 'Last 30 Days',
                                'custom' => "$start_date to $end_date",
                                default => 'All Time'
                            };
                            ?></p>
                    </div>
                </div>
                <div class="summary-item">
                    <span class="material-icons-sharp">receipt</span>
                    <div>
                        <strong>Bills</strong>
                        <p><?php echo $bills_stats['total_count']; ?> records</p>
                        <small>MWK <?php echo number_format($bills_stats['total_amount'], 0); ?></small>
                    </div>
                </div>
                <div class="summary-item">
                    <span class="material-icons-sharp">payments</span>
                    <div>
                        <strong>Payments</strong>
                        <p><?php echo $payments_stats['total_count']; ?> records</p>
                        <small>MWK <?php echo number_format($payments_stats['total_amount'], 0); ?></small>
                    </div>
                </div>
                <div class="summary-item">
                    <span class="material-icons-sharp">account_balance</span>
                    <div>
                        <strong>Balance</strong>
                        <p>MWK <?php echo number_format($bills_stats['total_amount'] - $payments_stats['total_amount'], 0); ?></p>
                        <small><?php echo ($bills_stats['total_amount'] - $payments_stats['total_amount']) >= 0 ? 'Outstanding' : 'Overpaid'; ?></small>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <button onclick="window.print()" class="action-btn">
                    <span class="material-icons-sharp">print</span>
                    Print Report
                </button>
                <button onclick="exportReport()" class="action-btn">
                    <span class="material-icons-sharp">download</span>
                    Export CSV
                </button>
                <a href="admin-dashboard.php" class="action-btn">
                    <span class="material-icons-sharp">dashboard</span>
                    Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
    <script>
        // Show/hide custom date range
        document.getElementById('periodFilter').addEventListener('change', function() {
            const dateRange = document.getElementById('dateRange');
            dateRange.style.display = this.value === 'custom' ? 'flex' : 'none';
        });

        // Apply filter
        function applyFilter() {
            const period = document.getElementById('periodFilter').value;
            const currentType = '<?php echo $report_type; ?>';
            let url = `reports.php?type=${currentType}&period=${period}`;

            if (period === 'custom') {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                if (startDate && endDate) {
                    url += `&start_date=${startDate}&end_date=${endDate}`;
                }
            }

            window.location.href = url;
        }

        // Switch report type
        function switchReport(type) {
            const period = document.getElementById('periodFilter').value;
            let url = `reports.php?type=${type}&period=${period}`;

            if (period === 'custom') {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                if (startDate && endDate) {
                    url += `&start_date=${startDate}&end_date=${endDate}`;
                }
            }

            window.location.href = url;
        }

        // Export to CSV
        function exportReport() {
            const type = '<?php echo $report_type; ?>';
            const table = document.querySelector('.report-content.active table');
            const rows = table.querySelectorAll('tr');
            let csv = [];

            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                cols.forEach(col => {
                    rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
                });
                csv.push(rowData.join(','));
            });

            const csvString = csv.join('\n');
            const blob = new Blob([csvString], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${type}_report_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
        }
    </script>
</body>

</html>