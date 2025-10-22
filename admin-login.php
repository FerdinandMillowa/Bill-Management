<?php
session_start();

// Database connection
require_once 'db-connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Input validation
    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password against hash
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_id'] = $user['id'];
                header("Location: admin-dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Bill Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/login.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="system-brand">
                <h2>Pitch&Roll</h2>
                <p>Bill Management System</p>
            </div>

            <div class="login-header">
                <h1><i class="fas fa-lock"></i> Admin Login</h1>
                <p>Access the administration dashboard</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> You have been successfully logged out.
                </div>
            <?php endif; ?>

            <form class="login-form" action="admin-login.php" method="POST">
                <div class="form-group">
                    <input type="text" id="username" name="username" placeholder="Enter your username" required
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <i class="fas fa-user"></i>
                </div>

                <div class="form-group">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="login-footer">
                <p>Need help? <a href="mailto:support@pitchandroll.com">Contact Support</a></p>
            </div>
        </div>
    </div>

    <script src="js/script.js"> </script>
</body>

</html>

<?php
$conn->close();
?>