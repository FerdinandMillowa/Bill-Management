<?php
require_once 'auth-helper.php';
?>
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
                <li><a href="reports.php">View Reports</a></li>
                <li><a href="reports-bills.php">Bills Report</a></li>
                <li><a href="reports-customers.php">Customers Report</a></li>
            </ul>
        </li>
        <li><a href="reports.php">Dashboard</a></li>

        <?php if (isAdmin()): ?>
            <li><a href="manage-users.php">
                    <i class="fas fa-users-cog"></i> Manage Users
                </a></li>
        <?php endif; ?>

        <?php if (isUserLoggedIn()): ?>
            <li>
                <a href="#">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars(getUsername()); ?>
                </a>
                <ul class="dropdown">
                    <li><a href="profile.php">
                            <i class="fas fa-user"></i> My Profile
                        </a></li>
                    <li><a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                </ul>
            </li>
        <?php endif; ?>
    </ul>

    <?php if (!isUserLoggedIn()): ?>
        <a href="login.php" class="action_btn">Login</a>
    <?php endif; ?>

    <div class="toggle_btn">
        <i class="fa-solid fa-bars"></i>
    </div>
</div>

<div class="dropdown_menu">
    <li><a href="add-bills.php">Add Bill</a></li>
    <li><a href="add-customer.php">Add Customer</a></li>
    <li><a href="add-payments.php">Add Payment</a></li>
    <li><a href="reports.php">Reports</a></li>

    <?php if (isAdmin()): ?>
        <li><a href="manage-users.php">
                <i class="fas fa-users-cog"></i> Manage Users
            </a></li>
    <?php endif; ?>

    <?php if (isUserLoggedIn()): ?>
        <li><a href="profile.php">
                <i class="fas fa-user"></i> My Profile
            </a></li>
        <li><a href="logout.php" class="action_btn">Logout</a></li>
    <?php else: ?>
        <li><a href="login.php" class="action_btn">Login</a></li>
    <?php endif; ?>
</div>