<?php
session_start();
$conn = new mysqli("localhost", "root", "Example@2022#", "bill_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$report_type = $_GET['report_type'] ?? 'daily';

switch ($report_type) {
    case 'weekly':
        $bill_filter = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $label = "This Week";
        break;
    case 'monthly':
        $bill_filter = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        $label = "This Month";
        break;
    case 'daily':
    default:
        $bill_filter = "DATE(created_at) = CURDATE()";
        $label = "Today";
        break;
}

$bills = $conn->query("SELECT SUM(amount) AS total_billed, COUNT(*) AS count FROM bills WHERE $bill_filter");
$payments = $conn->query("SELECT SUM(amount) AS total_paid, COUNT(*) AS count FROM payments WHERE $bill_filter");

$bill_data = $bills->fetch_assoc();
$payment_data = $payments->fetch_assoc();

$total_billed = $bill_data['total_billed'] ?? 0;
$total_paid = $payment_data['total_paid'] ?? 0;
$outstanding = $total_billed - $total_paid;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <link rel="stylesheet" href="css/reports.css">
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="header-report">
        <h1> <?php echo $label; ?></h1>
        <form method="get" action="reports.php">
            <select name="report_type" onchange="this.form.submit()">
                <option value="daily" <?php if ($report_type == 'daily') echo 'selected'; ?>>Daily</option>
                <option value="weekly" <?php if ($report_type == 'weekly') echo 'selected'; ?>>Weekly</option>
                <option value="monthly" <?php if ($report_type == 'monthly') echo 'selected'; ?>>Monthly</option>
            </select>
        </form>
    </div>
    <main>
        <section class="summary-cards">
            <div class="card">
                <h3>Total Billed</h3>
                <p>MWK <?php echo number_format($total_billed, 2); ?></p>
            </div>
            <div class="card">
                <h3>Total Paid</h3>
                <p>MWK <?php echo number_format($total_paid, 2); ?></p>
            </div>
            <div class="card">
                <h3>Outstanding</h3>
                <p>MWK <?php echo number_format($outstanding, 2); ?></p>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <!-- <footer>
    <p>&copy; <?php echo date("Y"); ?> Pitch and Roll</p>
</footer> -->
</body>

</html>