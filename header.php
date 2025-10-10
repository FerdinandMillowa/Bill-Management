<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BillMgtSytem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.1/css/fontawesome.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>

<body>
    <header>
        <div class="header_center">
            <h1>Bill Management System</h1>
        </div>
        <br><br>
        <hr>
        <div class="navbar">
            <div class="logo">
                <a href="index.php">Pitch&Roll</a>
            </div>
            <ul class="links">
                <li><a href="add-bills.php">Bills</a></li>
                <li><a href="add-customer.php">Customers</a></li>
                <li><a href="add-payments.php">Payments</a></li>
                <li>
                    <a href="#">Reports</a>
                    <ul class="dropdown">
                        <li><a href="#view-reports">View Reports</a></li>
                        <li><a href="#dashboard">Dashboard</a></li>
                    </ul>
                </li>
                <li><a href="reports.php">Dashboard</a></li>
            </ul>
            <a href="#" class="action_btn">Logout</a>
            <div class="toggle_btn">
                <i class="fa-solid fa-bars"></i>
            </div>
        </div>


        <div class="dropdown_menu ">
            <li><a href="add-bill.html">Add Bill</a></li>
            <li><a href="add-customer.php">Add Customer</a></li>
            <li><a href="add-payment.html">Add Payment</a></li>
            <li><a href="view-bills.php">View Bills</a></li>
            <li><a href="view-customers.php">View Customers</a></li>
            <li><a href="view-reports.html">Reports</a></li>
            <li><a href="#" class="action_btn">Get started</a></li>
        </div>
    </header>
</body>

</html>