<?php
// auth-helper.php
// Authentication helper functions

function requireAdminAuth($redirect = 'admin-login.php')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: $redirect");
        exit();
    }
}

function requireUserAuth($redirect = 'login.php')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect");
        exit();
    }
}

function isAdminLoggedIn()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function isUserLoggedIn()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

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

function validateCSRFToken($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone)
{
    return preg_match('/^(?:\+265|0)\d{9}$/', $phone);
}

function validateAmount($amount)
{
    return is_numeric($amount) && $amount > 0;
}

function logoutUser()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = array();

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

    session_destroy();
}

// Form security functions
function secureFormInput($input)
{
    if (is_array($input)) {
        return array_map('secureFormInput', $input);
    }
    return trim(strip_tags($input));
}

function validateFormToken()
{
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception("CSRF token validation failed.");
    }
}

function getFormTokenField()
{
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}
