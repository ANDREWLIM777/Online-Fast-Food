<?php
require_once("../db_connect.php");
session_start();
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 400);
    }
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    if (empty($email) || empty($password) || empty($confirm_password)) {
        throw new Exception("All fields are required.", 400);
    }
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.", 400);
    }
    if (strlen($password) < 8) {
        throw new Exception("Password must be at least 8 characters.", 400);
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $dbc->prepare("UPDATE customers SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed_password, $email);
    if ($stmt->execute() && $stmt->affected_rows === 1) {
        $title = "Password Reset Successful";
        $message = "Your password has been reset. Contact support if you didn’t initiate this.";
        $stmt = $dbc->prepare("INSERT INTO customer_notifications (customer_id, title, message, type) 
                               SELECT id, ?, ?, 'admin' FROM customers WHERE email = ?");
        $stmt->bind_param("sss", $title, $message, $email);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Password reset successfully! Redirecting to login...']);
    } else {
        throw new Exception("Failed to reset password. Please try again.", 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>