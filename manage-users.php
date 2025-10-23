<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

// Only admins can access this page
requireAdminAuth();

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

        // Optional: Check password strength
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
$users_query = $conn->prepare("SELECT id, username, role, email, full_name, created_at, last_login FROM users WHERE id != ? ORDER BY created_at DESC");
$users_query->bind_param("i", $current_user_id);
$users_query->execute();
$users = $users_query->get_result();

// Get user statistics
$stats_query = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count
    FROM users");
$stats = $stats_query->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="css/utilities.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md, 15px);
            margin-bottom: var(--spacing-xl, 25px);
        }

        .stat-card {
            background: var(--bg-darker, #2c3e48);
            padding: var(--spacing-lg, 20px);
            border-radius: var(--radius-md, 12px);
            text-align: center;
            border-left: 4px solid var(--color-accent, #1ab188);
            transition: transform var(--transition-base, 0.2s ease);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md, 0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .stat-card h3 {
            font-size: 2rem;
            color: var(--color-accent, #1ab188);
            margin: 0;
        }

        .stat-card p {
            margin: var(--spacing-xs, 4px) 0 0;
            color: var(--text-light, #e0e0e0);
            font-size: 0.9rem;
        }

        .user-table-container {
            overflow-x: auto;
            background: var(--bg-darker, #2c3e48);
            border-radius: var(--radius-md, 12px);
            padding: var(--spacing-lg, 20px);
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th,
        .user-table td {
            padding: var(--spacing-md, 12px);
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-table th {
            background: var(--bg-darkest, #344955);
            color: var(--color-accent, #1ab188);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .user-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .user-table td {
            color: var(--text-light, #e0e0e0);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: rgba(255, 152, 0, 0.2);
            color: var(--color-warning, #ff9800);
            border: 1px solid var(--color-warning, #ff9800);
        }

        .role-user {
            background: rgba(33, 150, 243, 0.2);
            color: #2196F3;
            border: 1px solid #2196F3;
        }

        .action-buttons {
            display: flex;
            gap: var(--spacing-sm, 8px);
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: var(--radius-sm, 4px);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base, 0.2s ease);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }

        .btn-edit {
            background: var(--color-info, #2196F3);
            color: white;
        }

        .btn-edit:hover {
            background: #1976D2;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: var(--color-error, #f44336);
            color: white;
        }

        .btn-delete:hover {
            background: #da190b;
            transform: translateY(-1px);
        }

        .btn-reset {
            background: var(--color-warning, #ff9800);
            color: white;
        }

        .btn-reset:hover {
            background: #f57c00;
            transform: translateY(-1px);
        }

        .password-strength {
            margin-top: var(--spacing-xs, 4px);
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all var(--transition-base, 0.2s ease);
        }

        .strength-weak {
            width: 33%;
            background: var(--color-error, #f44336);
        }

        .strength-medium {
            width: 66%;
            background: var(--color-warning, #ff9800);
        }

        .strength-strong {
            width: 100%;
            background: var(--color-success, #4CAF50);
        }

        .password-requirements {
            margin-top: var(--spacing-sm, 8px);
            font-size: 0.75rem;
            color: var(--text-light, #e0e0e0);
            line-height: 1.6;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .requirement.met {
            color: var(--color-success, #4CAF50);
        }

        .requirement.unmet {
            color: rgba(255, 255, 255, 0.4);
        }

        @media (max-width: 992px) {
            .user-table-container {
                overflow-x: scroll;
            }

            .user-table {
                min-width: 800px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header>
        <?php include 'header.php'; ?>

        <?php if (isset($_SESSION['temp_password'])): ?>
            <div class="success-message" style="background: rgba(255, 152, 0, 0.1); border-left-color: #ff9800;">
                <i class="fas fa-key"></i>
                <div>
                    <strong>Password Reset Successful!</strong><br>
                    User: <strong><?php echo htmlspecialchars($_SESSION['temp_password_user']); ?></strong><br>
                    New Password: <strong style="font-family: monospace; font-size: 1.1em;"><?php echo htmlspecialchars($_SESSION['temp_password']); ?></strong><br>
                    <small style="color: rgba(255,255,255,0.8);">⚠️ Save this password - it will not be shown again!</small>
                </div>
            </div>
            <?php
            unset($_SESSION['temp_password']);
            unset($_SESSION['temp_password_user']);
            ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </p>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </p>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

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

    <!-- Left: Add User Form -->
    <section class="form-section">
        <form class="form" action="manage-users.php" method="POST" autocomplete="off">
            <p class="title"><i class="fas fa-user-plus"></i> Create New User</p>
            <p class="message">Add a new user to the system</p>

            <?php echo getFormTokenField(); ?>
            <input type="hidden" name="action" value="add_user">

            <label>
                <input type="text" name="full_name" placeholder=" " maxlength="100"
                    value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                <span>Full Name (Optional)</span>
            </label>

            <label>
                <input type="text" name="username" required placeholder=" " maxlength="50"
                    pattern="^[a-zA-Z0-9_]{3,50}$"
                    title="Username must be 3-50 characters, letters, numbers, and underscores only"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                <span>Username</span>
            </label>

            <label>
                <input type="email" name="email" placeholder=" " maxlength="100"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <span>Email (Optional)</span>
            </label>

            <label>
                <input type="password" name="password" id="password" required placeholder=" "
                    maxlength="100" minlength="8">
                <span>Password</span>
                <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('password')"></i>
            </label>
            <div class="password-strength">
                <div class="password-strength-bar" id="strengthBar"></div>
            </div>
            <div class="password-requirements">
                <div class="requirement" id="req-length">
                    <i class="fas fa-circle"></i>
                    <span>At least 8 characters</span>
                </div>
                <div class="requirement" id="req-upper">
                    <i class="fas fa-circle"></i>
                    <span>One uppercase letter</span>
                </div>
                <div class="requirement" id="req-lower">
                    <i class="fas fa-circle"></i>
                    <span>One lowercase letter</span>
                </div>
                <div class="requirement" id="req-number">
                    <i class="fas fa-circle"></i>
                    <span>One number</span>
                </div>
            </div>

            <label>
                <input type="password" name="confirm_password" id="confirm_password" required placeholder=" " maxlength="100">
                <span>Confirm Password</span>
                <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('confirm_password')"></i>
            </label>

            <label>
                <select name="role" required>
                    <option value="" disabled selected>Select Role</option>
                    <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>
                        Regular User
                    </option>
                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>
                        Administrator
                    </option>
                </select>
                <span>User Role</span>
            </label>

            <button type="submit" class="submit">
                <i class="fas fa-user-plus"></i> Create User
            </button>

            <p class="signin">Return to <a href="index.php">Home</a></p>
        </form>
    </section>

    <!-- Right: User List -->
    <section class="list-section">
        <div class="customer-list">
            <h3><i class="fas fa-users"></i> User Management</h3>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p><i class="fas fa-users"></i> Total Users</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['admin_count']; ?></h3>
                    <p><i class="fas fa-user-shield"></i> Administrators</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['user_count']; ?></h3>
                    <p><i class="fas fa-user"></i> Regular Users</p>
                </div>
            </div>

            <!-- User Table -->
            <div class="user-table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                                        <?php if (!empty($user['email'])): ?>
                                            <br><small style="color: rgba(255,255,255,0.6);">
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'user-shield' : 'user'; ?>"></i>
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                        <?php if (!empty($user['last_login'])): ?>
                                            <br><small style="color: rgba(255,255,255,0.5);">
                                                Last: <?php echo date('M j', strtotime($user['last_login'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn-action btn-reset" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-key"></i> Reset
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: var(--spacing-xl, 30px);">
                                    <i class="fas fa-users" style="font-size: 3rem; color: rgba(255,255,255,0.2);"></i>
                                    <p>No other users found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <footer>
        <?php include 'footer.php' ?>
    </footer>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            // Check requirements
            const hasLength = password.length >= 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);

            // Update requirement indicators
            document.getElementById('req-length').className = hasLength ? 'requirement met' : 'requirement unmet';
            document.getElementById('req-upper').className = hasUpper ? 'requirement met' : 'requirement unmet';
            document.getElementById('req-lower').className = hasLower ? 'requirement met' : 'requirement unmet';
            document.getElementById('req-number').className = hasNumber ? 'requirement met' : 'requirement unmet';

            // Calculate strength
            if (hasLength) strength++;
            if (hasUpper) strength++;
            if (hasLower) strength++;
            if (hasNumber) strength++;

            // Update strength bar
            strengthBar.className = 'password-strength-bar';
            if (strength >= 4) {
                strengthBar.classList.add('strength-strong');
            } else if (strength >= 2) {
                strengthBar.classList.add('strength-medium');
            } else if (strength >= 1) {
                strengthBar.classList.add('strength-weak');
            }
        });

        // Toggle password visibility
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = event.target;

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Edit user (redirect to edit page)
        function editUser(userId) {
            window.location.href = `edit-user.php?id=${userId}`;
        }

        // Reset password
        function resetPassword(userId, username) {
            if (!confirm(`Reset password for user "${username}"?\n\nA new temporary password will be generated.`)) {
                return;
            }

            // This will be implemented in user-actions.php
            window.location.href = `user-actions.php?action=reset_password&id=${userId}`;
        }

        // Delete user
        function deleteUser(userId, username) {
            if (!confirm(`Are you sure you want to delete user "${username}"?\n\nThis action cannot be undone.`)) {
                return;
            }

            // This will be implemented in user-actions.php
            window.location.href = `user-actions.php?action=delete&id=${userId}`;
        }
    </script>
</body>

</html>