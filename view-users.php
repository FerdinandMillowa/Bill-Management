<?php
require_once 'auth-helper.php';
requireAdminAuth();

// Database connection
require_once 'db-connection.php';

// Fetch users from the database using prepared statement
$stmt = $conn->prepare("SELECT id, username, role FROM users ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();
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
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td>
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
$stmt->close();
$conn->close();
?>