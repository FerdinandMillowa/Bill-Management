<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

// Require authentication (any user can access their profile)
requireAnyAuth();

$success = "";
$error = "";
$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT id, username, email, full_name, role, status, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: logout.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Get user statistics
$bills_count = $conn->query("SELECT COUNT(*) as count FROM bills")->fetch_assoc()['count'];
$customers_count = $conn->query("SELECT COUNT(*) as count FROM customers WHERE status='approved'")->fetch_assoc()['count'];
$payments_count = $conn->query("SELECT COUNT(*) as count FROM payments")->fetch_assoc()['count'];

// Get recent activity (if activity_logs table exists)
$recent_activity = [];
$activity_check = $conn->query("SHOW TABLES LIKE 'activity_logs'");
if ($activity_check->num_rows > 0) {
    $activity_stmt = $conn->prepare("SELECT action, details, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
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

        // Validate email if provided
        if (!empty($email) && !validateEmail($email)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Check if email already exists (excluding current user)
        if (!empty($email)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                throw new Exception("Email already in use by another user.");
            }
            $check->close();
        }

        // Update profile
        $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ? WHERE id = ?");
        $stmt->bind_param("ssi", $email, $full_name, $user_id);

        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            logActivity('profile_updated', "User updated their profile");

            // Refresh user data
            $user['email'] = $email;
            $user['full_name'] = $full_name;
        } else {
            throw new Exception("Error updating profile: " . $conn->error);
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

        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required.");
        }

        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }

        if (strlen($new_password) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
            throw new Exception("Password must contain uppercase, lowercase, and number.");
        }

        // Verify current password
        $verify_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $verify_stmt->bind_param("i", $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $user_data = $verify_result->fetch_assoc();
        $verify_stmt->close();

        if (!password_verify($current_password, $user_data['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, password_reset_required = 0 WHERE id = ?");
        $update_stmt->bind_param("si", $password_hash, $user_id);

        if ($update_stmt->execute()) {
            $success = "Password changed successfully!";
            logActivity('password_changed', "User changed their password");
        } else {
            throw new Exception("Error changing password: " . $conn->error);
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
    <title>My Profile - <?php echo htmlspecialchars($user['username']); ?></title>
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
                "header header"
                "sidebar main"
                "footer footer";
            grid-template-columns: 300px 1fr;
            grid-template-rows: auto 1fr auto;
            gap: var(--spacing-xl, 20px);
            padding: var(--spacing-xl, 20px);
            height: 100vh;
            margin: 0;
            box-sizing: border-box;
        }

        header {
            grid-area: header;
        }

        .profile-sidebar {
            grid-area: sidebar;
            background: var(--bg-dark, #24323d);
            border-radius: var(--radius-xl, 20px);
            padding: var(--spacing-xl, 25px);
            box-shadow: var(--shadow-md, 0 4px 6px rgba(0, 0, 0, 0.1));
            height: fit-content;
        }

        .profile-main {
            grid-area: main;
            display: flex;
            flex-direction: column;
        }

        footer {
            grid-area: footer;
        }

        .profile-card {
            background: var(--bg-dark, #24323d);
            border-radius: var(--radius-xl, 20px);
            padding: var(--spacing-xl, 25px);
            box-shadow: var(--shadow-md, 0 4px 6px rgba(0, 0, 0, 0.1));
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .tabs {
            display: flex;
            gap: var(--spacing-sm, 10px);
            margin-bottom: var(--spacing-lg, 20px);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .tab-content {
            display: none;
            flex: 1;
            overflow-y: auto;
            padding-right: 10px;
        }

        .tab-content.active {
            display: flex;
            flex-direction: column;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            margin: 0 auto var(--spacing-lg, 20px);
            background: linear-gradient(135deg, var(--color-accent, #1ab188), var(--color-primary, #0088A9));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            box-shadow: 0 8px 20px rgba(26, 177, 136, 0.3);
            position: relative;
            overflow: hidden;
        }

        .profile-avatar::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.2) 100%);
        }

        .profile-info {
            text-align: center;
            margin-bottom: var(--spacing-lg, 20px);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-white, #fff);
            margin-bottom: var(--spacing-xs, 4px);
        }

        .profile-username {
            color: var(--color-accent, #1ab188);
            font-size: 1rem;
            margin-bottom: var(--spacing-xs, 4px);
        }

        .profile-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: var(--spacing-sm, 8px);
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

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--spacing-sm, 10px);
            margin-top: var(--spacing-lg, 20px);
            padding-top: var(--spacing-lg, 20px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-sm, 10px);
            background: var(--bg-darker, #2c3e48);
            border-radius: var(--radius-md, 8px);
            transition: transform var(--transition-base, 0.2s ease);
        }

        .stat-item:hover {
            transform: translateX(5px);
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
        }

        .stat-value {
            color: var(--color-accent, #1ab188);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md, 12px);
            margin-bottom: var(--spacing-lg, 20px);
            padding-bottom: var(--spacing-md, 12px);
            border-bottom: 2px solid var(--color-accent, #1ab188);
        }

        .card-header i {
            font-size: 1.5rem;
            color: var(--color-accent, #1ab188);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-white, #fff);
            margin: 0;
        }

        .tab {
            padding: var(--spacing-md, 12px) var(--spacing-lg, 20px);
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base, 0.2s ease);
            border-bottom: 3px solid transparent;
        }

        .tab:hover {
            color: var(--text-white, #fff);
        }

        .tab.active {
            color: var(--color-accent, #1ab188);
            border-bottom-color: var(--color-accent, #1ab188);
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md, 12px);
            padding: var(--spacing-md, 12px);
            border-left: 3px solid var(--color-accent, #1ab188);
            margin-bottom: var(--spacing-md, 12px);
            background: var(--bg-darker, #2c3e48);
            border-radius: var(--radius-md, 8px);
            transition: all var(--transition-base, 0.2s ease);
        }

        .activity-item:hover {
            transform: translateX(5px);
            background: var(--bg-darkest, #344955);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--color-accent, #1ab188);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon i {
            color: white;
            font-size: 1rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-action {
            font-weight: 600;
            color: var(--text-white, #fff);
            margin-bottom: var(--spacing-xs, 4px);
        }

        .activity-details {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: var(--spacing-xs, 4px);
        }

        .activity-time {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md, 15px);
            margin-bottom: var(--spacing-lg, 20px);
        }

        .info-item {
            background: var(--bg-darker, #2c3e48);
            padding: var(--spacing-md, 15px);
            border-radius: var(--radius-md, 8px);
            border-left: 3px solid var(--color-accent, #1ab188);
        }

        .info-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--spacing-xs, 4px);
        }

        .info-value {
            font-size: 1rem;
            color: var(--text-white, #fff);
            font-weight: 600;
        }

        @media (max-width: 992px) {
            body {
                grid-template-areas:
                    "header"
                    "sidebar"
                    "main"
                    "footer";
                grid-template-columns: 1fr;
                grid-template-rows: auto auto 1fr auto;
                height: auto;
                overflow: auto;
            }

            .profile-sidebar {
                position: relative;
                top: 0;
            }

            .profile-avatar {
                width: 120px;
                height: 120px;
                font-size: 3rem;
            }

            .tab-content {
                height: auto;
                overflow: visible;
            }
        }

        @media (max-width: 576px) {
            .tabs {
                flex-direction: column;
            }

            .tab {
                text-align: left;
                border-bottom: none;
                border-left: 3px solid transparent;
            }

            .tab.active {
                border-left-color: var(--color-accent, #1ab188);
                border-bottom-color: transparent;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header>
        <?php include 'header-new.php'; ?>
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

    <!-- Profile Sidebar -->
    <aside class="profile-sidebar">
        <div class="profile-avatar">
            <i class="fas fa-user"></i>
        </div>

        <div class="profile-info">
            <div class="profile-name">
                <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
            </div>
            <div class="profile-username">
                @<?php echo htmlspecialchars($user['username']); ?>
            </div>
            <span class="profile-role role-<?php echo $user['role']; ?>">
                <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'user-shield' : 'user'; ?>"></i>
                <?php echo ucfirst($user['role']); ?>
            </span>
        </div>

        <div class="profile-stats">
            <div class="stat-item">
                <span class="stat-label">
                    <i class="fas fa-file-invoice"></i> Total Bills
                </span>
                <span class="stat-value"><?php echo $bills_count; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">
                    <i class="fas fa-users"></i> Customers
                </span>
                <span class="stat-value"><?php echo $customers_count; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">
                    <i class="fas fa-money-bill-wave"></i> Payments
                </span>
                <span class="stat-value"><?php echo $payments_count; ?></span>
            </div>
        </div>
    </aside>

    <!-- Profile Main Content -->
    <main class="profile-main">
        <div class="profile-card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('overview')">
                    <i class="fas fa-th-large"></i> Overview
                </button>
                <button class="tab" onclick="switchTab('edit')">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
                <button class="tab" onclick="switchTab('security')">
                    <i class="fas fa-lock"></i> Security
                </button>
                <button class="tab" onclick="switchTab('activity')">
                    <i class="fas fa-history"></i> Activity
                </button>
            </div>

            <!-- Overview Tab -->
            <div id="overview" class="tab-content active">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h2 class="card-title">Account Information</h2>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : '<span style="color: rgba(255,255,255,0.3);">Not set</span>'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">
                            <?php echo !empty($user['full_name']) ? htmlspecialchars($user['full_name']) : '<span style="color: rgba(255,255,255,0.3);">Not set</span>'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Type</div>
                        <div class="info-value"><?php echo ucfirst($user['role']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value" style="color: <?php echo ($user['status'] ?? 'active') === 'active' ? '#4CAF50' : '#f44336'; ?>;">
                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value">
                            <?php echo !empty($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : '<span style="color: rgba(255,255,255,0.3);">Never</span>'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account ID</div>
                        <div class="info-value">#<?php echo $user['id']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Tab -->
            <div id="edit" class="tab-content">
                <div class="card-header">
                    <i class="fas fa-user-edit"></i>
                    <h2 class="card-title">Edit Profile</h2>
                </div>

                <form class="form" method="POST" action="profile.php">
                    <?php echo getFormTokenField(); ?>
                    <input type="hidden" name="action" value="update_profile">

                    <label>
                        <input type="text" name="username" placeholder=" " disabled
                            value="<?php echo htmlspecialchars($user['username']); ?>">
                        <span>Username (Cannot be changed)</span>
                    </label>

                    <label>
                        <input type="text" name="full_name" placeholder=" " maxlength="100"
                            value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        <span>Full Name</span>
                    </label>

                    <label>
                        <input type="email" name="email" placeholder=" " maxlength="100"
                            value="<?php echo htmlspecialchars($user['email']); ?>">
                        <span>Email Address</span>
                    </label>

                    <button type="submit" class="submit">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Security Tab -->
            <div id="security" class="tab-content">
                <div class="card-header">
                    <i class="fas fa-shield-alt"></i>
                    <h2 class="card-title">Change Password</h2>
                </div>

                <form class="form" method="POST" action="profile.php">
                    <?php echo getFormTokenField(); ?>
                    <input type="hidden" name="action" value="change_password">

                    <label>
                        <input type="password" id="current_password" name="current_password" required placeholder=" ">
                        <span>Current Password</span>
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordField('current_password')"></i>
                    </label>

                    <label>
                        <input type="password" id="new_password" name="new_password" required placeholder=" " minlength="8">
                        <span>New Password</span>
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordField('new_password')"></i>
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
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder=" ">
                        <span>Confirm New Password</span>
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordField('confirm_password')"></i>
                    </label>

                    <button type="submit" class="submit">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

            <!-- Activity Tab -->
            <div id="activity" class="tab-content">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h2 class="card-title">Recent Activity</h2>
                </div>

                <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                    <ul class="activity-list">
                        <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?php
                                                        echo match (true) {
                                                            str_contains($activity['action'], 'login') => 'sign-in-alt',
                                                            str_contains($activity['action'], 'customer') => 'user-plus',
                                                            str_contains($activity['action'], 'bill') => 'file-invoice',
                                                            str_contains($activity['action'], 'payment') => 'money-bill-wave',
                                                            str_contains($activity['action'], 'password') => 'key',
                                                            str_contains($activity['action'], 'profile') => 'user-edit',
                                                            default => 'check-circle'
                                                        };
                                                        ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-action">
                                        <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($activity['action']))); ?>
                                    </div>
                                    <?php if (!empty($activity['details'])): ?>
                                        <div class="activity-details">
                                            <?php echo htmlspecialchars($activity['details']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="activity-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div style="text-align: center; padding: var(--spacing-2xl, 40px); color: rgba(255,255,255,0.4);">
                        <i class="fas fa-history" style="font-size: 3rem; margin-bottom: var(--spacing-md, 15px);"></i>
                        <p>No activity recorded yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <?php include 'footer.php' ?>
    </footer>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Password toggle
        function togglePasswordField(fieldId) {
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

        // Password strength checker
        const newPasswordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('strengthBar');

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
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
        }

        // Password match validation
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (confirmPasswordInput && newPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value && this.value !== newPasswordInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });

            newPasswordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value) {
                    confirmPasswordInput.dispatchEvent(new Event('input'));
                }
            });
        }

        // Add these styles for password requirements
        const style = document.createElement('style');
        style.textContent = `
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

            .requirement.met i {
                color: var(--color-success, #4CAF50);
            }

            .requirement.unmet {
                color: rgba(255, 255, 255, 0.4);
            }

            .requirement.unmet i {
                color: rgba(255, 255, 255, 0.2);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>