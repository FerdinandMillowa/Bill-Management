<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once '../db-connection.php';
require_once '../auth-helper.php';

// Require authentication
requireAnyAuth();

// Get current user info
$admin_username = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'];

$success = "";
$error = "";

// Fetch user data
$stmt = $conn->prepare("SELECT id, username, email, full_name, role, status, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: ../logout.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Get user statistics
$bills_count = $conn->query("SELECT COUNT(*) as count FROM bills")->fetch_assoc()['count'];
$customers_count = $conn->query("SELECT COUNT(*) as count FROM customers WHERE status='approved'")->fetch_assoc()['count'];
$payments_count = $conn->query("SELECT COUNT(*) as count FROM payments")->fetch_assoc()['count'];

// Get total amounts
$total_bills = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM bills")->fetch_assoc()['total'];
$total_payments = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments")->fetch_assoc()['total'];

// Get recent activity
$recent_activity = [];
$activity_check = $conn->query("SHOW TABLES LIKE 'activity_logs'");
if ($activity_check->num_rows > 0) {
    $activity_stmt = $conn->prepare("SELECT action, details, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $activity_stmt->bind_param("i", $user_id);
    $activity_stmt->execute();
    $recent_activity = $activity_stmt->get_result();
    $activity_stmt->close();
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        validateFormToken();

        $email = secureFormInput($_POST["email"] ?? '');
        $full_name = secureFormInput($_POST["full_name"] ?? '');

        if (!empty($email) && !validateEmail($email)) {
            throw new Exception("Please enter a valid email address.");
        }

        if (!empty($email)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("Email already in use.");
            }
            $check->close();
        }

        $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ? WHERE id = ?");
        $stmt->bind_param("ssi", $email, $full_name, $user_id);

        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            logActivity('profile_updated', "User updated their profile");
            $user['email'] = $email;
            $user['full_name'] = $full_name;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        validateFormToken();

        $current_password = $_POST["current_password"];
        $new_password = $_POST["new_password"];
        $confirm_password = $_POST["confirm_password"];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required.");
        }

        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }

        if (strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters.");
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
            throw new Exception("Password must contain uppercase, lowercase, and number.");
        }

        $verify_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $verify_stmt->bind_param("i", $user_id);
        $verify_stmt->execute();
        $user_data = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();

        if (!password_verify($current_password, $user_data['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $password_hash, $user_id);

        if ($update_stmt->execute()) {
            $success = "Password changed successfully!";
            logActivity('password_changed', "User changed their password");
        }
        $update_stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/profile-dashboard.css">
    <title>My Profile - Dashboard</title>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
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
                <a href="./manage-users-dashboard.php">
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
                <a href="./reports.php">
                    <span class="material-icons-sharp">insights</span>
                    <h3>Reports</h3>
                </a>
                <a href="./profile.php" class="active">
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

        <!-- Main Content -->
        <main class="profile-main">
            <h1>My Profile</h1>

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

            <!-- Profile Stats -->
            <div class="analyse">
                <div class="sales">
                    <div class="status">
                        <div class="info">
                            <h3>Total Bills</h3>
                            <h1><?php echo $bills_count; ?></h1>
                            <small>MWK <?php echo number_format($total_bills, 0); ?></small>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">receipt_long</span>
                        </div>
                    </div>
                </div>
                <div class="visits">
                    <div class="status">
                        <div class="info">
                            <h3>Total Payments</h3>
                            <h1><?php echo $payments_count; ?></h1>
                            <small>MWK <?php echo number_format($total_payments, 0); ?></small>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">payments</span>
                        </div>
                    </div>
                </div>
                <div class="searches">
                    <div class="status">
                        <div class="info">
                            <h3>Customers</h3>
                            <h1><?php echo $customers_count; ?></h1>
                            <small>Approved</small>
                        </div>
                        <div class="progresss">
                            <span class="material-icons-sharp">groups</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="profile-tabs">
                <button class="profile-tab-btn active" onclick="switchProfileTab('info')">
                    <span class="material-icons-sharp">info</span>
                    Account Info
                </button>
                <button class="profile-tab-btn" onclick="switchProfileTab('edit')">
                    <span class="material-icons-sharp">edit</span>
                    Edit Profile
                </button>
                <button class="profile-tab-btn" onclick="switchProfileTab('security')">
                    <span class="material-icons-sharp">lock</span>
                    Security
                </button>
            </div>

            <!-- Account Info Tab -->
            <div class="profile-tab-content active" id="infoTab">
                <div class="info-card">
                    <h2>Account Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="material-icons-sharp">person</span>
                            <div>
                                <strong>Username</strong>
                                <p><?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="material-icons-sharp">badge</span>
                            <div>
                                <strong>Full Name</strong>
                                <p><?php echo htmlspecialchars($user['full_name'] ?: 'Not set'); ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="material-icons-sharp">email</span>
                            <div>
                                <strong>Email</strong>
                                <p><?php echo htmlspecialchars($user['email'] ?: 'Not set'); ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="material-icons-sharp">admin_panel_settings</span>
                            <div>
                                <strong>Role</strong>
                                <p>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="material-icons-sharp">verified</span>
                            <div>
                                <strong>Status</strong>
                                <p>
                                    <span class="status-badge status-<?php echo $user['status'] ?? 'active'; ?>">
                                        <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="material-icons-sharp">calendar_today</span>
                            <div>
                                <strong>Member Since</strong>
                                <p><?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="material-icons-sharp">schedule</span>
                            <div>
                                <strong>Last Login</strong>
                                <p><?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="material-icons-sharp">tag</span>
                            <div>
                                <strong>User ID</strong>
                                <p>#<?php echo str_pad($user['id'], 5, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Tab -->
            <div class="profile-tab-content" id="editTab">
                <div class="form-card">
                    <h2>Edit Profile</h2>
                    <form class="profile-form" method="POST" action="">
                        <?php echo getFormTokenField(); ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label>
                                <input type="text" disabled value="<?php echo htmlspecialchars($user['username']); ?>" placeholder=" ">
                                <span>Username (Cannot be changed)</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="text" name="full_name" placeholder=" " value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                <span>Full Name</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="email" name="email" placeholder=" " value="<?php echo htmlspecialchars($user['email']); ?>">
                                <span>Email Address</span>
                            </label>
                        </div>

                        <button type="submit" class="submit-btn">
                            <span class="material-icons-sharp">save</span>
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="profile-tab-content" id="securityTab">
                <div class="form-card">
                    <h2>Change Password</h2>
                    <form class="profile-form" method="POST" action="">
                        <?php echo getFormTokenField(); ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label>
                                <input type="password" name="current_password" id="current_password" required placeholder=" ">
                                <span>Current Password</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="password" name="new_password" id="new_password" required placeholder=" " minlength="8">
                                <span>New Password</span>
                            </label>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <small class="helper-text">Min. 8 chars, 1 uppercase, 1 lowercase, 1 number</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="password" name="confirm_password" id="confirm_password" required placeholder=" ">
                                <span>Confirm New Password</span>
                            </label>
                        </div>

                        <button type="submit" class="submit-btn">
                            <span class="material-icons-sharp">vpn_key</span>
                            Change Password
                        </button>
                    </form>
                </div>
            </div>

        </main>

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
                        <small class="text-muted"><?php echo ucfirst($user['role']); ?></small>
                    </div>
                    <div class="profile-photo">
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="user-profile">
                <div class="profile-avatar">
                    <div class="avatar-large">
                        <?php echo strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)); ?>
                    </div>
                </div>
                <h2><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h2>
                <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                <span class="role-badge role-<?php echo $user['role']; ?>">
                    <?php echo ucfirst($user['role']); ?>
                </span>
            </div>

            <div class="reminders">
                <div class="header">
                    <h2>Recent Activity</h2>
                    <span class="material-icons-sharp">history</span>
                </div>

                <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                    <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                        <div class="notification">
                            <div class="icon">
                                <span class="material-icons-sharp">
                                    <?php
                                    echo match (true) {
                                        str_contains($activity['action'], 'login') => 'login',
                                        str_contains($activity['action'], 'customer') => 'person_add',
                                        str_contains($activity['action'], 'bill') => 'receipt',
                                        str_contains($activity['action'], 'payment') => 'payment',
                                        str_contains($activity['action'], 'password') => 'lock',
                                        default => 'check_circle'
                                    };
                                    ?>
                                </span>
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h3><?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?></h3>
                                    <small class="text_muted"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="notification">
                        <p style="text-align: center; color: var(--color-info-dark);">No recent activity</p>
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
        function switchProfileTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.profile-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active from buttons
            document.querySelectorAll('.profile-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + 'Tab').classList.add('active');
            event.target.closest('.profile-tab-btn').classList.add('active');
        }

        // Password strength checker
        const newPasswordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('strengthBar');

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
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
    </script>
</body>

</html>