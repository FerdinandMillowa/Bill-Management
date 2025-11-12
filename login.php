<?php
session_start();

// Redirect if already logged in - BOTH go to index.php
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

        // Check rate limiting (prevent brute force)
        $rate_limit_key = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!checkRateLimit($rate_limit_key, 5, 900)) { // 5 attempts in 15 minutes
            $remaining = getRemainingLockoutTime($rate_limit_key, 900);
            throw new Exception("Too many login attempts. Please try again in " . formatLockoutTime($remaining));
        }

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
                // Set session variables for ALL users
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Set admin flag if admin
                if ($user['role'] === 'admin') {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $user['username'];
                }

                // Handle "Remember Me"
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", true, true); // 30 days
                }

                // Log activity
                logActivity('login', "User logged in successfully");

                // Check if there's a redirect URL stored
                $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);

                // Update last login timestamp
                $update_login = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_login->bind_param("i", $user['id']);
                $update_login->execute();
                $update_login->close();

                // EVERYONE goes to index.php (or their intended destination)
                header("Location: $redirect");
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
        :root {
            --color-primary: #1676f3;
            --background-light: #f5f7f8;
            --background-dark: #101822;
            --text-dark: #0d131c;
            --text-light: #6b7280;
            --text-white: #ffffff;
            --text-muted: #94a3b8;
            --border-light: #d1d5db;
            --border-dark: #374151;
        }

        /* Loading Screen Styles */
        #loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        #loading-screen.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loading-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            animation: logoSpin 2s ease-in-out infinite;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1.5rem;
        }

        .loading-text {
            color: white;
            font-size: 1.125rem;
            font-weight: 500;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .loading-subtext {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            text-align: center;
        }

        @keyframes logoSpin {
            0% {
                transform: rotate(0deg) scale(1);
            }

            50% {
                transform: rotate(180deg) scale(1.1);
            }

            100% {
                transform: rotate(360deg) scale(1);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        /* Rest of your existing styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .design-root {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .layout-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 3rem;
            align-items: center;
        }

        @media (min-width: 1024px) {
            .grid-container {
                grid-template-columns: 1fr 1fr;
                gap: 5rem;
            }
        }

        .hero-section {
            text-align: center;
            padding: 0 1rem;
        }

        @media (min-width: 1024px) {
            .hero-section {
                text-align: left;
                padding: 0;
            }
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        @media (min-width: 1024px) {
            .logo-container {
                justify-content: flex-start;
            }
        }

        .logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 8px;
        }

        .logo-text {
            color: var(--text-white);
            font-weight: 900;
            font-size: 2.5rem;
            line-height: 1;
            letter-spacing: -0.025em;
        }

        .hero-title {
            color: var(--color-primary);
            font-weight: 900;
            font-size: 3.75rem;
            line-height: 1;
            letter-spacing: -0.025em;
            margin-bottom: 1.5rem;
        }

        .hero-subtitle {
            color: var(--text-white);
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .hero-description {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.125rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (min-width: 1024px) {
            .hero-description {
                margin-left: 0;
                margin-right: 0;
            }
        }

        .login-card {
            width: 100%;
            max-width: 28rem;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-content {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        @media (min-width: 768px) {
            .card-content {
                padding: 2.5rem;
            }
        }

        .card-title {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .card-title h3 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 1rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: var(--text-light);
            z-index: 10;
        }

        .form-input {
            width: 100%;
            height: 3.5rem;
            padding: 0 1rem 0 3rem;
            border: 1px solid var(--border-light);
            border-radius: 0.5rem;
            background-color: var(--background-light);
            font-size: 1rem;
            color: var(--text-dark);
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(22, 118, 243, 0.1);
            background-color: white;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            color: var(--text-light);
            background: none;
            border: none;
            cursor: pointer;
            z-index: 10;
            padding: 0.25rem;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--color-primary);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
            transform: scale(1.1);
        }

        .checkbox-group label {
            color: var(--text-dark);
            font-size: 0.875rem;
            cursor: pointer;
            user-select: none;
        }

        .submit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 3.5rem;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.125rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .submit-btn:hover {
            background: #1265d8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(22, 118, 243, 0.3);
        }

        .submit-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(22, 118, 243, 0.3);
        }

        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }

        .forgot-password a {
            color: var(--color-primary);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-password a:hover {
            text-decoration: underline;
            color: #1265d8;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #16a34a;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        footer {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem 1.5rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: white;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .logo-container {
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }

            .logo {
                width: 50px;
                height: 50px;
            }

            .logo-text {
                font-size: 2rem;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.5rem;
            }

            .hero-description {
                font-size: 1rem;
                color: rgba(255, 255, 255, 0.95);
            }

            .card-content {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1.25rem;
            }

            .logo-text {
                font-size: 1.75rem;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Screen -->
    <div id="loading-screen">
        <img src="images/logo.png" alt="BillFlow Logo" class="loading-logo">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading BillFlow</div>
        <div class="loading-subtext">Please wait...</div>
    </div>

    <!-- Main Content -->
    <div class="design-root" style="display: none;">
        <div class="layout-container">
            <main>
                <div class="container">
                    <div class="grid-container">
                        <!-- Hero Section -->
                        <div class="hero-section">
                            <div class="logo-container">
                                <img src="images/logo.png" alt="BillFlow Logo" class="logo">
                                <div class="logo-text">BillFlow</div>
                            </div>
                            <h1 class="hero-title">Streamline Your Billing</h1>
                            <h2 class="hero-subtitle">
                                Efficient bill management made simple
                            </h2>
                            <p class="hero-description">
                                BillFlow helps you manage customer bills, track payments, and generate comprehensive reports.
                                Designed for businesses that value efficiency and clarity in financial management.
                            </p>
                        </div>

                        <!-- Login Card -->
                        <div class="login-card">
                            <div class="card">
                                <div class="card-content">
                                    <div class="card-title">
                                        <h3>Welcome Back</h3>
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

                                        <div class="form-group">
                                            <div class="input-wrapper">
                                                <i class="fas fa-user input-icon"></i>
                                                <input
                                                    class="form-input"
                                                    type="text"
                                                    name="username"
                                                    required
                                                    placeholder="Username"
                                                    maxlength="50"
                                                    autofocus
                                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="input-wrapper">
                                                <i class="fas fa-lock input-icon"></i>
                                                <input
                                                    class="form-input"
                                                    type="password"
                                                    name="password"
                                                    id="password"
                                                    required
                                                    placeholder="Password"
                                                    maxlength="100">
                                                <button type="button" class="password-toggle" id="togglePassword">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="checkbox-group">
                                            <input
                                                type="checkbox"
                                                name="remember_me"
                                                id="remember_me"
                                                <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                                            <label for="remember_me">Remember me for 30 days</label>
                                        </div>

                                        <button type="submit" class="submit-btn">
                                            <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                                            Sign In
                                        </button>

                                        <div class="forgot-password">
                                            <a href="#">Forgot password?</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <footer>
                <div class="footer-content">
                    <div class="footer-links">
                        <a href="#">About Us</a>
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">English (US)</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Loading screen functionality
        window.addEventListener('load', function() {
            // Wait for 2 seconds to show the loading animation
            setTimeout(function() {
                const loadingScreen = document.getElementById('loading-screen');
                const mainContent = document.querySelector('.design-root');

                // Hide loading screen
                loadingScreen.classList.add('hidden');

                // Show main content
                mainContent.style.display = 'flex';

                // Remove loading screen from DOM after transition
                setTimeout(function() {
                    loadingScreen.remove();
                }, 500);
            }, 2000); // 2 seconds loading time
        });

        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            toggleIcon.classList.toggle('fa-eye');
            toggleIcon.classList.toggle('fa-eye-slash');
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.3s ease';
                setTimeout(() => msg.remove(), 300);
            });
        }, 5000);
    </script>
</body>

</html>
<?php $conn->close(); ?>