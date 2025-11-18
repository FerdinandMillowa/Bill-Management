<?php
require_once 'auth-helper.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/header-new.css">
    <title>Document</title>
</head>

<body></body>

</html>
<header class="main-header">
    <div class="header-top">
        <div class="container">
            <h1 class="page-title">Bill Management System</h1>
        </div>
    </div>

    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <a href="index.php">Pitch<span>&</span>Roll</a>
            </div>

            <ul class="nav-links">
                <li><a href="add-bills.php">Bills</a></li>
                <li><a href="add-customer.php">Customers</a></li>
                <li><a href="add-payments.php">Payments</a></li>
                <li class="has-dropdown">
                    <a href="#">Reports <i class="fas fa-chevron-down"></i></a>
                    <ul class="dropdown">
                        <li><a href="reports.php">View Reports</a></li>
                        <li><a href="reports-bills.php">Bills Report</a></li>
                        <li><a href="reports-customers.php">Customers Report</a></li>
                    </ul>
                </li>
            </ul>

            <div class="nav-actions">
                <?php if (isUserLoggedIn()): ?>
                    <div class="user-menu has-dropdown">
                        <a href="#" class="user-profile">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars(getUsername()); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown">
                            <?php if (isAdmin()): ?>
                                <li>
                                    <a href="admin-dashboard/admin-dashboard.php">
                                        <i class="fas fa-chart-line"></i> Dashboard
                                    </a>
                                </li>
                                <li class="divider"></li>
                            <?php endif; ?>
                            <li>
                                <a href="<?php echo isAdmin() ? './admin-dashboard/profile.php' : 'profile.php'; ?>">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn-login">Login</a>
                <?php endif; ?>
            </div>

            <button class="mobile-toggle" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu">
        <ul>
            <li><a href="add-bills.php">Bills</a></li>
            <li><a href="add-customer.php">Customers</a></li>
            <li><a href="add-payments.php">Payments</a></li>
            <li><a href="reports.php">View Reports</a></li>
            <li><a href="reports-bills.php">Bills Report</a></li>
            <li><a href="reports-customers.php">Customers Report</a></li>

            <?php if (isUserLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                    <li class="mobile-divider"></li>
                    <li>
                        <a href="admin-dashboard/admin-dashboard.php">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="manage-users.php">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                    </li>
                <?php endif; ?>
                <li class="mobile-divider"></li>
                <li>
                    <a href="profile.php"> <i class="fas fa-user"></i> My Profile </a>
                </li>
                <li><a href="logout.php" class="btn-mobile-logout">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="btn-mobile-login">Login</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const header = document.querySelector('.main-header');
        const mobileToggle = document.querySelector('.mobile-toggle');
        const mobileMenu = document.querySelector('.mobile-menu');

        // Enhanced sticky header with scroll effect
        let lastScroll = 0;
        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            lastScroll = currentScroll;
        });

        // Mobile menu toggle
        mobileToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            this.querySelector('i').classList.toggle('fa-bars');
            this.querySelector('i').classList.toggle('fa-times');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.navbar') && !e.target.closest('.mobile-menu')) {
                mobileMenu.classList.remove('active');
                mobileToggle.querySelector('i').classList.remove('fa-times');
                mobileToggle.querySelector('i').classList.add('fa-bars');
            }
        });
    });
</script>