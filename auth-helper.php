<?php
// auth-helper.php
// Authentication helper functions

/**
 * Require admin authentication
 */
function requireAdminAuth($redirect = 'login.php')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: $redirect");
        exit();
    }
}

/**
 * Require user authentication (non-admin)
 */
function requireUserAuth($redirect = 'login.php')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: $redirect");
        exit();
    }

    // Redirect admins to their dashboard
    if ($_SESSION['role'] === 'admin') {
        header("Location: reports.php");
        exit();
    }
}

/**
 * Require any authentication (admin or regular user)
 */
function requireAnyAuth($redirect = 'login.php')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $isUser = isset($_SESSION['user_id']);

    if (!$isAdmin && !$isUser) {
        header("Location: $redirect");
        exit();
    }
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if any user is logged in
 */
function isUserLoggedIn()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function isAdmin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get current user role
 */
function getUserRole()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['role'] ?? 'guest';
}

/**
 * Get current username
 */
function getUsername()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['username'] ?? $_SESSION['admin_username'] ?? 'Guest';
}

/**
 * Generate CSRF token
 */
function generateCSRFToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input (basic HTML entity encoding)
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email)
{
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Malawi phone number
 * Accepts: +265xxxxxxxxx or 0xxxxxxxxx (9 digits after prefix)
 */
function validatePhone($phone)
{
    return preg_match('/^(?:\+265|0)\d{9}$/', $phone);
}

/**
 * Validate amount (must be positive number)
 */
function validateAmount($amount)
{
    return is_numeric($amount) && $amount > 0;
}

/**
 * Validate password strength
 */
function validatePassword($password, $min_length = 8)
{
    if (strlen($password) < $min_length) {
        return false;
    }

    // Optional: Add more complex requirements
    // Must contain at least one uppercase, one lowercase, one number
    // return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password);

    return true;
}

/**
 * Logout user and destroy session
 */
function logoutUser()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Unset all session variables
    $_SESSION = array();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Delete remember me cookie if exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    // Destroy session
    session_destroy();
}

/**
 * Secure form input (strip tags and trim)
 */
function secureFormInput($input)
{
    if (is_array($input)) {
        return array_map('secureFormInput', $input);
    }
    return trim(strip_tags($input));
}

/**
 * Validate form token (CSRF protection)
 */
function validateFormToken()
{
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception("Invalid security token. Please refresh the page and try again.");
    }
}

/**
 * Get form token field (HTML input)
 */
function getFormTokenField()
{
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Log activity (optional - for audit trail)
 */
function logActivity($action, $details = '')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? 0;
    $username = $_SESSION['username'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $log_entry = sprintf(
        "[%s] User: %s (ID: %d) | Action: %s | Details: %s | IP: %s\n",
        $timestamp,
        $username,
        $user_id,
        $action,
        $details,
        $ip_address
    );

    // Write to log file (create logs directory first)
    $log_file = __DIR__ . '/logs/activity.log';
    $log_dir = dirname($log_file);

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    error_log($log_entry, 3, $log_file);
}

/**
 * Rate limiting helper (prevent brute force)
 */
function checkRateLimit($identifier, $max_attempts = 5, $time_window = 900)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = 'rate_limit_' . $identifier;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => $now];
    }

    $data = $_SESSION[$key];

    // Reset if time window has passed
    if ($now - $data['first_attempt'] > $time_window) {
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
        return true;
    }

    // Check if limit exceeded
    if ($data['attempts'] >= $max_attempts) {
        return false;
    }

    // Increment attempts
    $_SESSION[$key]['attempts']++;
    return true;
}

/**
 * Get remaining lockout time
 */
function getRemainingLockoutTime($identifier, $time_window = 900)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = 'rate_limit_' . $identifier;
    $now = time();

    if (!isset($_SESSION[$key])) {
        return 0;
    }

    $elapsed = $now - $_SESSION[$key]['first_attempt'];
    $remaining = $time_window - $elapsed;

    return max(0, $remaining);
}

/**
 * Format remaining time for display
 */
function formatLockoutTime($seconds)
{
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf("%d:%02d", $minutes, $secs);
}
