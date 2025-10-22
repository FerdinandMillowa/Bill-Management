<?php

/**
 * Admin Verification Handler
 * Verifies admin credentials for inline privilege elevation
 */

header('Content-Type: application/json');
session_start();

require_once 'db-connection.php';
require_once 'auth-helper.php';

$response = [
    'success' => false,
    'message' => '',
    'reload' => false
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You must be logged in to perform this action.");
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    // Get inputs
    $username = secureFormInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $action = $_POST['action'] ?? '';
    $data = json_decode($_POST['data'] ?? '{}', true);

    // Validate inputs
    if (empty($action)) {
        throw new Exception("No action specified.");
    }

    // Check if user is already admin and using bypass
    $isAlreadyAdmin = isAdmin();
    $isBypass = ($password === 'admin_bypass' && $isAlreadyAdmin);

    if ($isBypass) {
        // Admin is already authenticated, allow action without re-verification
        $admin = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ];
    } else {
        // Regular user requesting admin privileges - verify credentials
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }

        // Check rate limiting
        $rate_limit_key = 'admin_verify_' . $_SESSION['user_id'];
        if (!checkRateLimit($rate_limit_key, 3, 300)) { // 3 attempts in 5 minutes
            $remaining = getRemainingLockoutTime($rate_limit_key, 300);
            throw new Exception("Too many authentication attempts. Please try again in " . formatLockoutTime($remaining));
        }

        // Verify admin credentials
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            throw new Exception("Invalid admin credentials.");
        }

        $admin = $result->fetch_assoc();

        // Verify password
        if (!password_verify($password, $admin['password'])) {
            throw new Exception("Invalid admin credentials.");
        }

        $stmt->close();

        // Store admin verification in session (temporary elevation)
        $_SESSION['admin_verified'] = true;
        $_SESSION['admin_verified_at'] = time();
        $_SESSION['admin_verified_by'] = $admin['username'];
    }

    // Perform action based on type
    switch ($action) {
        case 'approve_customer':
            $result = approveCustomer($conn, $data);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            $response['reload'] = true;
            break;

        case 'delete_customer':
            $result = deleteCustomer($conn, $data);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            $response['reload'] = true;
            break;

        case 'edit_bill':
            $result = editBill($conn, $data);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            $response['reload'] = true;
            break;

        case 'delete_bill':
            $result = deleteBill($conn, $data);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            $response['reload'] = true;
            break;

        case 'generic':
            // Generic admin verification (just verify credentials)
            $response['success'] = true;
            $response['message'] = 'Admin credentials verified successfully.';
            break;

        default:
            throw new Exception("Unknown action: $action");
    }

    // Log the activity
    logActivity("admin_verification", "Admin {$admin['username']} verified for action: $action");
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;

/**
 * Action Functions
 */

function approveCustomer($conn, $data)
{
    try {
        $customer_id = intval($data['customer_id'] ?? 0);

        if ($customer_id <= 0) {
            throw new Exception("Invalid customer ID.");
        }

        // Check if customer exists and is pending
        $check_stmt = $conn->prepare("SELECT id FROM customers WHERE id = ? AND status = 'pending'");
        $check_stmt->bind_param("i", $customer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            throw new Exception("Customer not found or already approved.");
        }
        $check_stmt->close();

        // Approve customer
        $stmt = $conn->prepare("UPDATE customers SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $customer_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to approve customer.");
        }

        $stmt->close();

        return [
            'success' => true,
            'message' => 'Customer approved successfully.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function deleteCustomer($conn, $data)
{
    try {
        $customer_id = intval($data['customer_id'] ?? 0);

        if ($customer_id <= 0) {
            throw new Exception("Invalid customer ID.");
        }

        // Check if customer has bills or payments
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM bills WHERE customer_id = ?");
        $check_stmt->bind_param("i", $customer_id);
        $check_stmt->execute();
        $bills_count = $check_stmt->get_result()->fetch_assoc()['count'];
        $check_stmt->close();

        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE customer_id = ?");
        $check_stmt->bind_param("i", $customer_id);
        $check_stmt->execute();
        $payments_count = $check_stmt->get_result()->fetch_assoc()['count'];
        $check_stmt->close();

        if ($bills_count > 0 || $payments_count > 0) {
            throw new Exception("Cannot delete customer with existing bills or payments.");
        }

        // Delete customer
        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to delete customer.");
        }

        $stmt->close();

        return [
            'success' => true,
            'message' => 'Customer deleted successfully.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function editBill($conn, $data)
{
    try {
        $bill_id = intval($data['bill_id'] ?? 0);
        $amount = floatval($data['amount'] ?? 0);
        $description = secureFormInput($data['description'] ?? '');

        if ($bill_id <= 0) {
            throw new Exception("Invalid bill ID.");
        }

        if ($amount <= 0) {
            throw new Exception("Invalid amount.");
        }

        // Update bill
        $stmt = $conn->prepare("UPDATE bills SET amount = ?, description = ? WHERE id = ?");
        $stmt->bind_param("dsi", $amount, $description, $bill_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update bill.");
        }

        $stmt->close();

        return [
            'success' => true,
            'message' => 'Bill updated successfully.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function deleteBill($conn, $data)
{
    try {
        $bill_id = intval($data['bill_id'] ?? 0);

        if ($bill_id <= 0) {
            throw new Exception("Invalid bill ID.");
        }

        // Delete bill
        $stmt = $conn->prepare("DELETE FROM bills WHERE id = ?");
        $stmt->bind_param("i", $bill_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to delete bill.");
        }

        $stmt->close();

        return [
            'success' => true,
            'message' => 'Bill deleted successfully.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
