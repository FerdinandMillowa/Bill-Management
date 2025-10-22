<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: reports.php");
    exit();
}

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

$error = "";
$success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Validate CSRF token
        validateFormToken();

        // Sanitize inputs
        $username = secureFormInput($_POST['username']);
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember_me']);

        // Validate inputs
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }

        // Check user in database using prepared statement
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Set admin session if admin
                if ($user['role'] === 'admin') {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $user['username'];
                }

                // Handle "Remember Me"
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", true, true); // 30 days

                    // Store token in database (you'd need to add a remember_tokens table)
                    // For now, we'll skip this and just set the cookie
                }

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: reports.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                throw new Exception("Invalid username or password.");
            }
        } else {
            throw new Exception("Invalid username or password.");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bill Management System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/add-customer.css">
    <link rel="stylesheet" href="css/utilities.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: var(--spacing-md, 20px);
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            background: var(--bg-dark, #24323d);
            border-radius: var(--radius-xl, 20px);
            padding: var(--spacing-2xl, 40px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl, 30px);
        }

        .login-header h1 {
            color: var(--color-accent, #1ab188);
            font-size: clamp(1.75rem, 4vw, 2rem);
            margin-bottom: var(--spacing-sm, 8px);
        }

        .login-header p {
            color: var(--text-light, #e0e0e0);
            font-size: var(--font-size-sm, 14px);
        }

        .login-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--spacing-lg, 20px);
            background: var(--color-accent, #1ab188);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            box-shadow: 0 4px 20px rgba(26, 177, 136, 0.3);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm, 8px);
            margin: var(--spacing-md, 15px) 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
            transform: scale(1.2);
        }

        .checkbox-group label {
            color: var(--text-light, #e0e0e0);
            font-size: var(--font-size-sm, 14px);
            cursor: pointer;
            user-select: none;
        }

        .form-footer {
            text-align: center;
            margin-top: var(--spacing-xl, 20px);
            padding-top: var(--spacing-md, 15px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-footer p {
            color: var(--text-light, #e0e0e0);
            font-size: var(--font-size-sm, 14px);
            margin: 0;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary, #777);
            font-size: 1.1rem;
            pointer-events: none;
        }

        .input-icon input {
            padding-left: 45px !important;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary, #777);
            font-size: 1.1rem;
            transition: color var(--transition-base, 0.2s ease);
        }

        .password-toggle:hover {
            color: var(--color-accent, #1ab188);
        }

        @media (max-width: 576px) {
            .login-container {
                padding: var(--spacing-xl, 30px) var(--spacing-lg, 20px);
            }

            .login-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-user-lock"></i>
            </div>
            <h1>Welcome Back</h1>
            <p>Sign in to continue to Bill Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form class="form" action="login.php" method="POST" autocomplete="off">
            <?php echo getFormTokenField(); ?>

            <label class="input-icon">
                <i class="fas fa-user"></i>
                <input
                    type="text"
                    name="username"
                    required
                    placeholder=" "
                    maxlength="50"
                    autofocus
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                <span>Username</span>
            </label>

            <label class="input-icon">
                <i class="fas fa-lock"></i>
                <input
                    type="password"
                    name="password"
                    id="password"
                    required
                    placeholder=" "
                    maxlength="100">
                <span>Password</span>
                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
            </label>

            <div class="checkbox-group">
                <input
                    type="checkbox"
                    name="remember_me"
                    id="remember_me"
                    <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                <label for="remember_me">Remember me for 30 days</label>
            </div>

            <button type="submit" class="submit">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>

            <div class="form-footer">
                <p>Bill Management System &copy; <?php echo date('Y'); ?></p>
            </div>
        </form>
    </div>

    <script>
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                msg.style.animation = 'slideDown 0.3s ease reverse';
                setTimeout(() => msg.remove(), 300);
            });
        }, 5000);
    </script>
</body>

</html><?php $conn->close(); ?>