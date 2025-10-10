<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'db-connection.php';
// Fetch users from the database
$sql = "SELECT * FROM users";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Users</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h1>Registered Users</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
        <?php if ($result->num_rows > 0) {
            while ($user = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo $user['username']; ?></td>
                    <td><?php echo $user['role']; ?></td>
                    <td>
                        <!-- You can add edit/delete functionality here -->
                        <a href="edit-user.php?id=<?php echo $user['id']; ?>">Edit</a>
                        <a href="delete-user.php?id=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>

                    </td>
                </tr>
            <?php }
        } else { ?>
            <tr>
                <td colspan="4">No users found.</td>
            </tr>
        <?php } ?>
    </table>

    <a href="admin-dashboard.php">Back to Dashboard</a>
</body>

</html>

<?php
$conn->close();
?>