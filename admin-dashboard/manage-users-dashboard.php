<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once '../db-connection.php';
require_once '../auth-helper.php';

// Only admins can access this page
requireAdminAuth();

// Get current admin info
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$admin_id = $_SESSION['user_id'];

$success = "";
$error = "";

// Handle form submission for adding new user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    try {
        // Validate CSRF token
        validateFormToken();

        // Sanitize inputs
        $username = secureFormInput($_POST["username"]);
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];
        $role = secureFormInput($_POST["role"]);
        $email = secureFormInput($_POST["email"] ?? '');
        $full_name = secureFormInput($_POST["full_name"] ?? '');

        // Validate inputs
        if (empty($username) || empty($password) || empty($confirm_password)) {
            throw new Exception("Username and password are required.");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            throw new Exception("Password must contain at least one uppercase letter, one lowercase letter, and one number.");
        }

        if (!in_array($role, ['user', 'admin'])) {
            throw new Exception("Invalid role selected.");
        }

        if (!empty($email) && !validateEmail($email)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Check if username already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            throw new Exception("Username already exists. Please choose another.");
        }
        $check->close();

        // Check if email already exists (if provided)
        if (!empty($email)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                throw new Exception("Email already exists.");
            }
            $check->close();
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $password_hash, $role, $email, $full_name);

        if ($stmt->execute()) {
            $success = "User '$username' created successfully as " . ucfirst($role) . ".";
            logActivity('user_created', "New user created: $username (Role: $role)");
        } else {
            throw new Exception("Error creating user: " . $conn->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch all users (excluding current admin for safety)
$current_user_id = $_SESSION['user_id'];
$users_query = $conn->prepare("SELECT id, username, role, email, full_name, status, created_at, last_login FROM users WHERE id != ? ORDER BY created_at DESC");
$users_query->bind_param("i", $current_user_id);
$users_query->execute();
$users = $users_query->get_result();

// Get user statistics
$stats_query = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
    FROM users");
$stats = $stats_query->fetch_assoc();

// Get recent user activities
$recent_activities = $conn->query("
    SELECT a.action, a.details, a.created_at, u.username, u.full_name
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.action LIKE '%user%'
    ORDER BY a.created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/manage-users-dashboard.css">
    <title>Manage Users - Admin Dashboard</title>
</head>

<body>
    <div class="container">
        <!-- Sidebar Section -->
        <aside>
            <div class="toggle">
                <div class="logo">
                    <img src="images/logo.png" alt="Logo">
                    <h2>Pitch<span class="danger">&Roll</span></h2>
                </div>
                <div class="close" id="close-btn">
                    <span class="material-icons-sharp">close</span>
                </div>
            </div>

            <div class="sidebar">
                <a href="admin-dashboard.php">
                    <span class="material-icons-sharp">dashboard</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="./manage-users-dashboard.php" class="active">
                    <span class="material-icons-sharp">person_outline</span>
                    <h3>Users</h3>
                </a>
                <a href="./add-customer.php">
                    <span class="material-icons-sharp">group</span>
                    <h3>Customers</h3>
                </a>
                <a href="./add-bills.php">
                    <span class="material-icons-sharp">receipt_long</span>
                    <h3>Bills</h3>
                </a>
                <a href="./add-payments.php">
                    <span class="material-icons-sharp">payments</span>
                    <h3>Payments</h3>
                </a>
                <a href="../reports.php">
                    <span class="material-icons-sharp">insights</span>
                    <h3>Reports</h3>
                </a>
                <a href="../reports-bills.php">
                    <span class="material-icons-sharp">description</span>
                    <h3>Bill Reports</h3>
                </a>
                <a href="../profile.php">
                    <span class="material-icons-sharp">settings</span>
                    <h3>Profile</h3>
                </a>
                <a href="../index.php">
                    <span class="material-icons-sharp">home</span>
                    <h3>Main System</h3>
                </a>
                <a href="../logout.php">
                    <span class="material-icons-sharp">logout</span>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>
        <!-- End of Sidebar Section -->

        <!-- Main Content -->
        <main>
            <h1>User Management</h1>

            <!-- Alert Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <span class="material-icons-sharp">check_circle</span>
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span class="material-icons-sharp">error</span>
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="analyse">
                <div class="sales">
                    <div class="status">
                        <div class="info">
                            <h3>Total Users</h3>
                            <h1><?php echo $stats['total_users']; ?></h1>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">people</span>
                        </div>
                    </div>
                </div>
                <div class="visits">
                    <div class="status">
                        <div class="info">
                            <h3>Administrators</h3>
                            <h1><?php echo $stats['admin_count']; ?></h1>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">admin_panel_settings</span>
                        </div>
                    </div>
                </div>
                <div class="searches">
                    <div class="status">
                        <div class="info">
                            <h3>Regular Users</h3>
                            <h1><?php echo $stats['user_count']; ?></h1>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">person</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add User Form -->
            <div class="user-form-container">
                <h2>Create New User</h2>
                <form class="user-form" method="POST" action="">
                    <?php echo getFormTokenField(); ?>
                    <input type="hidden" name="action" value="add_user">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                <input type="text" name="full_name" placeholder=" "
                                    value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                <span>Full Name (Optional)</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="text" name="username" required placeholder=" "
                                    pattern="^[a-zA-Z0-9_]{3,50}$"
                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                <span>Username *</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="email" name="email" placeholder=" "
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <span>Email (Optional)</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <select name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="user">Regular User</option>
                                    <option value="admin">Administrator</option>
                                </select>
                                <span>User Role *</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="password" name="password" id="password" required placeholder=" " minlength="8">
                                <span>Password *</span>
                            </label>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <small class="helper-text">Min. 8 chars, 1 uppercase, 1 lowercase, 1 number</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="password" name="confirm_password" required placeholder=" ">
                                <span>Confirm Password *</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <span class="material-icons-sharp">person_add</span>
                        Create User
                    </button>
                </form>
            </div>

            <!-- Users Table -->
            <div class="recent-orders">
                <h2>All Users</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                                            <?php if (!empty($user['email'])): ?>
                                                <small><?php echo htmlspecialchars($user['email']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['status'] ?? 'active'; ?>">
                                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-icon" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                                <span class="material-icons-sharp">edit</span>
                                            </button>
                                            <button class="btn-icon warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Reset Password">
                                                <span class="material-icons-sharp">lock_reset</span>
                                            </button>
                                            <button class="btn-icon danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete">
                                                <span class="material-icons-sharp">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">
                                    <span class="material-icons-sharp" style="font-size: 3rem; color: var(--color-info-dark);">people</span>
                                    <p>No other users found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
        <!-- End of Main Content -->

        <!-- Right Section -->
        <div class="right-section">
            <div class="nav">
                <button id="menu-btn">
                    <span class="material-icons-sharp">menu</span>
                </button>
                <div class="dark-mode">
                    <span class="material-icons-sharp active">light_mode</span>
                    <span class="material-icons-sharp">dark_mode</span>
                </div>

                <div class="profile">
                    <div class="info">
                        <p>Hey, <b><?php echo htmlspecialchars($admin_username); ?></b></p>
                        <small class="text-muted">Administrator</small>
                    </div>
                    <div class="profile-photo">
                        <span class="material-icons-sharp" style="font-size: 2.5rem;">account_circle</span>
                    </div>
                </div>
            </div>

            <div class="user-profile">
                <div class="logo">
                    <img src="images/logo.png" alt="Logo">
                    <h2>Pitch&Roll</h2>
                    <p>User Management</p>
                </div>
            </div>

            <div class="reminders">
                <div class="header">
                    <h2>Recent Activity</h2>
                    <span class="material-icons-sharp">history</span>
                </div>

                <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                    <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                        <div class="notification">
                            <div class="icon">
                                <span class="material-icons-sharp">person</span>
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h3><?php echo htmlspecialchars($activity['action']); ?></h3>
                                    <small class="text_muted">
                                        <?php echo htmlspecialchars($activity['username'] ?? 'System'); ?> -
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="notification">
                        <div class="content">
                            <p style="text-align: center; color: var(--color-info-dark);">No recent activities</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="add-reminder">
                    <a href="admin-dashboard.php" style="text-decoration: none; color: inherit;">
                        <div>
                            <span class="material-icons-sharp">dashboard</span>
                            <h3>Back to Dashboard</h3>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;

                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;

                strengthBar.className = 'strength-bar';
                if (strength >= 4) {
                    strengthBar.classList.add('strength-strong');
                } else if (strength >= 2) {
                    strengthBar.classList.add('strength-medium');
                } else if (strength >= 1) {
                    strengthBar.classList.add('strength-weak');
                }
            });
        }

        // Edit user
        function editUser(userId) {
            window.location.href = `edit-user.php?id=${userId}`;
        }

        // Reset password
        function resetPassword(userId, username) {
            if (!confirm(`Reset password for user "${username}"?\n\nA new temporary password will be generated.`)) {
                return;
            }
            window.location.href = `../user-actions.php?action=reset_password&id=${userId}`;
        }

        // Delete user
        function deleteUser(userId, username) {
            if (!confirm(`Are you sure you want to delete user "${username}"?\n\nThis action cannot be undone.`)) {
                return;
            }
            window.location.href = `../user-actions.php?action=delete&id=${userId}`;
        }
    </script>
</body>

</html>