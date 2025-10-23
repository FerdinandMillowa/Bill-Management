<?php
session_start();

// DB connection and security
require_once 'db-connection.php';
require_once 'auth-helper.php';

// Only admins can perform user actions
requireAdminAuth();

$action = $_GET['action'] ?? '';
$user_id = intval($_GET['id'] ?? 0);

// Prevent admin from deleting/modifying themselves
if ($user_id === $_SESSION['user_id']) {
    $_SESSION['error_message'] = "You cannot perform this action on your own account.";
    header("Location: manage-users.php");
    exit();
}

try {
    switch ($action) {
        case 'delete':
            deleteUser($conn, $user_id);
            break;

        case 'reset_password':
            resetUserPassword($conn, $user_id);
            break;

        case 'toggle_status':
            toggleUserStatus($conn, $user_id);
            break;

        default:
            throw new Exception("Invalid action specified.");
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: manage-users.php");
    exit();
}

/**
 * Delete user from system
 */
function deleteUser($conn, $user_id)
{
    // Check if user exists
    $check_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("User not found.");
    }

    $user = $result->fetch_assoc();
    $username = $user['username'];
    $check_stmt->close();

    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        logActivity('user_deleted', "User deleted: $username (ID: $user_id)");
        $_SESSION['success_message'] = "User '$username' has been deleted successfully.";
    } else {
        throw new Exception("Failed to delete user.");
    }

    $stmt->close();
    header("Location: manage-users.php");
    exit();
}

/**
 * Reset user password
 */
function resetUserPassword($conn, $user_id)
{
    // Check if user exists
    $check_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("User not found.");
    }

    $user = $result->fetch_assoc();
    $username = $user['username'];
    $email = $user['email'];
    $check_stmt->close();

    // Generate temporary password
    $temp_password = generateRandomPassword();
    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ?, password_reset_required = 1 WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $user_id);

    if ($stmt->execute()) {
        logActivity('password_reset', "Password reset for user: $username (ID: $user_id)");

        // Store temp password in session to display to admin
        $_SESSION['temp_password'] = $temp_password;
        $_SESSION['temp_password_user'] = $username;

        // TODO: Send email with new password if email exists
        // sendPasswordResetEmail($email, $username, $temp_password);

        $_SESSION['success_message'] = "Password reset successfully for user '$username'.";
    } else {
        throw new Exception("Failed to reset password.");
    }

    $stmt->close();
    header("Location: manage-users.php");
    exit();
}

/**
 * Toggle user active/inactive status
 */
function toggleUserStatus($conn, $user_id)
{
    // Check current status
    $check_stmt = $conn->prepare("SELECT username, status FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("User not found.");
    }

    $user = $result->fetch_assoc();
    $username = $user['username'];
    $current_status = $user['status'] ?? 'active';
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';
    $check_stmt->close();

    // Update status
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);

    if ($stmt->execute()) {
        logActivity('user_status_changed', "User $username status changed to: $new_status");
        $_SESSION['success_message'] = "User '$username' is now " . $new_status . ".";
    } else {
        throw new Exception("Failed to update user status.");
    }

    $stmt->close();
    header("Location: manage-users.php");
    exit();
}

/**
 * Generate random secure password
 */
function generateRandomPassword($length = 12)
{
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*';

    $all = $uppercase . $lowercase . $numbers . $special;

    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];

    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

/**
 * Send password reset email (placeholder)
 */
function sendPasswordResetEmail($email, $username, $temp_password)
{
    if (empty($email)) {
        return false;
    }

    $subject = "Password Reset - Bill Management System";
    $message = "
    Hello $username,
    
    Your password has been reset by an administrator.
    
    Your new temporary password is: $temp_password
    
    Please login and change your password immediately.
    
    Login at: " . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php
    
    If you did not request this password reset, please contact your administrator immediately.
    
    Best regards,
    Bill Management System
    ";

    $headers = "From: noreply@billmanagement.com\r\n";
    $headers .= "Reply-To: admin@billmanagement.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Uncomment when mail server is configured
    // return mail($email, $subject, $message, $headers);

    return true;
}
