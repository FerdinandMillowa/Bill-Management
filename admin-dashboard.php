<?php
require_once 'auth-helper.php';
requireAdminAuth();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="css/utilities.css">
    <style>
        /* Loading Screen Styles */
        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        #loading img {
            width: 100px;
            height: 100px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div id="loading">
        <img src="images/logo.png" alt="Loading Logo"> <!-- Update this path -->
    </div>

    <div>
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</p>

        <nav>
            <ul>
                <li><a href="register.php">Register New User</a></li>
                <li><a href="view-customers.php">View Customers</a></li>
                <li><a href="reports-bills.php">View Bills Report</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <h2>Manage Users</h2>
    <a href="view-users.php">View All Users</a>

    <script>
        // Hide the loading screen after a brief delay
        window.onload = function() {
            document.getElementById("loading").style.display = "none";
            document.querySelector("div").style.display = "block";
        };
    </script>

</body>

</html>