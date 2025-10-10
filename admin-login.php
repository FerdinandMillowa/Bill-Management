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
        $stmt = $conn->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password against hash
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
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
    <link rel="stylesheet" href="css/style.css">
    <title>Admin Login</title>
</head>

<body>
    <h1>Admin Login</h1>

    <?php if (isset($error)): ?>
        <div style="color: red; margin: 10px 0;"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="admin-login.php" method="POST">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>

        <input type="submit" value="Login">
    </form>
</body>

</html>

<?php
$conn->close();
?>