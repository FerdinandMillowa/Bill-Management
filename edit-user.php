<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

// Only admins can edit users
requireAdminAuth();

$success = "";
$error = "";
$user_id = intval($_GET['id'] ?? 0);

// Prevent admin from editing themselves (use profile page instead)
if ($user_id === $_SESSION['user_id']) {
    $_SESSION['error_message'] = "Please use your profile page to edit your own account.";
    header("Location: manage-users.php");
    exit();
}

// Fetch user data
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT id, username, email, full_name, role, status, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "User not found.";
        header("Location: manage-users.php");
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: manage-users.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate CSRF token
        validateFormToken();

        // Sanitize inputs
        $username = secureFormInput($_POST["username"]);
        $email = secureFormInput($_POST["email"] ?? '');
        $full_name = secureFormInput($_POST["full_name"] ?? '');
        $role = secureFormInput($_POST["role"]);
        $status = secureFormInput($_POST["status"] ?? 'active');

        // Validate inputs
        if (empty($username)) {
            throw new Exception("Username is required.");
        }

        if (!in_array($role, ['user', 'admin'])) {
            throw new Exception("Invalid role selected.");
        }

        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception("Invalid status selected.");
        }

        if (!empty($email) && !validateEmail($email)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Check if username already exists (excluding current user)
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $user_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            throw new Exception("Username already exists. Please choose another.");
        }
        $check->close();

        // Check if email already exists (if provided, excluding current user)
        if (!empty($email)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                throw new Exception("Email already exists.");
            }
            $check->close();
        }

        // Update user
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $username, $email, $full_name, $role, $status, $user_id);

        if ($stmt->execute()) {
            $success = "User information updated successfully.";
            logActivity('user_updated', "User updated: $username (ID: $user_id)");

            // Refresh user data
            $user['username'] = $username;
            $user['email'] = $email;
            $user['full_name'] = $full_name;
            $user['role'] = $role;
            $user['status'] = $status;
        } else {
            throw new Exception("Error updating user: " . $conn->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit User - <?php echo htmlspecialchars($user['username']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="css/utilities.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body {
            display: grid;
            grid-template-areas:
                "header"
                "form"
                "footer";
            grid-template-columns: 1fr;
        }

        .form-section {
            max-width: 600px;
            margin: 0 auto;
        }

        .user-info-card {
            background: var(--bg-darker, #2c3e48);
            padding: var(--spacing-lg, 20px);
            border-radius: var(--radius-md, 12px);
            margin-bottom: var(--spacing-lg, 20px);
            border-left: 4px solid var(--color-accent, #1ab188);
        }

        .user-info-card h3 {
            color: var(--color-accent, #1ab188);
            margin-bottom: var(--spacing-md, 12px);
            font-size: 1.2rem;
        }

        .user-info-card p {
            margin: var(--spacing-xs, 4px) 0;
            color: var(--text-light, #e0e0e0);
            font-size: 0.9rem;
        }

        .info-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .actions-container {
            display: flex;
            gap: var(--spacing-sm, 10px);
            margin-top: var(--spacing-lg, 20px);
        }

        .btn-secondary {
            background: var(--bg-darker, #2c3e48);
            color: var(--text-light, #e0e0e0);
            padding: var(--spacing-md, 12px) var(--spacing-lg, 20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg, 10px);
            font-size: var(--font-size-base, 16px);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base, 0.2s ease);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: var(--bg-darkest, #344955);
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <header>
        <?php include 'header.php'; ?>
        <?php if (!empty($success)) : ?>
            <p class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </p>
        <?php elseif (!empty($error)) : ?>
            <p class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </p>
        <?php endif; ?>
    </header>

    <section class="form-section">
        <!-- User Information Card -->
        <div class="user-info-card">
            <h3><i class="fas fa-user-edit"></i> Editing User</h3>
            <p><span class="info-label">User ID:</span> #<?php echo $user['id']; ?></p>
            <p><span class="info-label">Created:</span> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
            <p><span class="info-label">Current Status:</span>
                <span style="color: <?php echo $user['status'] === 'active' ? '#4CAF50' : '#f44336'; ?>;">
                    <?php echo ucfirst($user['status'] ?? 'active'); ?>
                </span>
            </p>
        </div>

        <!-- Edit Form -->
        <form class="form" action="edit-user.php?id=<?php echo $user_id; ?>" method="POST" autocomplete="off">
            <p class="title"><i class="fas fa-edit"></i> Edit User Information</p>
            <p class="message">Update user details below</p>

            <?php echo getFormTokenField(); ?>

            <label>
                <input type="text" name="full_name" placeholder=" " maxlength="100"
                    value="<?php echo htmlspecialchars($user['full_name']); ?>">
                <span>Full Name</span>
            </label>

            <label>
                <input type="text" name="username" required placeholder=" " maxlength="50"
                    pattern="^[a-zA-Z0-9_]{3,50}$"
                    title="Username must be 3-50 characters, letters, numbers, and underscores only"
                    value="<?php echo htmlspecialchars($user['username']); ?>">
                <span>Username</span>
            </label>

            <label>
                <input type="email" name="email" placeholder=" " maxlength="100"
                    value="<?php echo htmlspecialchars($user['email']); ?>">
                <span>Email</span>
            </label>

            <label>
                <select name="role" required>
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>
                        Regular User
                    </option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>
                        Administrator
                    </option>
                </select>
                <span>User Role</span>
            </label>

            <label>
                <select name="status" required>
                    <option value="active" <?php echo ($user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>
                        Active
                    </option>
                    <option value="inactive" <?php echo ($user['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>
                        Inactive
                    </option>
                </select>
                <span>Account Status</span>
            </label>

            <div class="actions-container">
                <button type="submit" class="submit" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="manage-users.php" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

            <p class="signin" style="margin-top: var(--spacing-lg, 20px);">
                <a href="user-actions.php?action=reset_password&id=<?php echo $user_id; ?>"
                    onclick="return confirm('Reset password for this user?');">
                    <i class="fas fa-key"></i> Reset Password
                </a>
            </p>
        </form>
    </section>

    <footer>
        <?php include 'footer.php' ?>
    </footer>
</body>

</html>