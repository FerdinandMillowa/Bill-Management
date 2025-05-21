<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin-login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
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
            /* Makes sure it's on top of other elements */
        }

        #loading img {
            width: 100px;
            /* Adjust as needed */
            height: 100px;
            /* Adjust as needed */
            animation: spin 2s linear infinite;
            /* Spinning animation */
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
        <img src="path/to/your/logo.png" alt="Loading Logo"> <!-- Update this path -->
    </div>

    <div>
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?php echo $_SESSION['username']; ?>!</p>

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
    <a href="view-users.php">View All Users</a> <!-- Link to view users if you create that page -->

    <script>
        // Hide the loading screen after a brief delay
        window.onload = function() {
            document.getElementById("loading").style.display = "none";
            document.querySelector("div").style.display = "block"; // Show main content
        };
    </script>

</body>

</html>