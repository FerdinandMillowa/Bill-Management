<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'db-connection.php';

// Get user ID from URL
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    // Fetch user data from the database
    $sql = "SELECT * FROM users WHERE id = '$user_id'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    } else {
        echo "User not found.";
        exit;
    }
} else {
    echo "Invalid request.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="css/utilities.css">
</head>

<body>
    <h2>Edit User: <?php echo $user['username']; ?></h2>
    <form action="edit-user-process.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

        <label for="username">Username:</label>
        <input type="text" id="username" name="username" value="<?php echo $user['username']; ?>" required>

        <label for="role">Role:</label>
        <select id="role" name="role" required>
            <option value="user" <?php if ($user['role'] == 'user') echo 'selected'; ?>>User</option>
            <option value="admin" <?php if ($user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
        </select>

        <button type="submit">Update User</button>
    </form>

    <a href="view-users.php">Cancel</a>
</body>

</html>

<?php
$conn->close();
?>