<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['order_id'])) {
    $orderId = $_POST['order_id'];
    $customerId = $_SESSION['customer_id'];

    // Update order status ONLY if the order belongs to this customer and is currently 'delivered'
    $stmt = $conn->prepare("UPDATE orders SET status = 'completed' WHERE order_id = ? AND customer_id = ? AND status = 'delivered'");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Success, redirect with notification flag
        header("Location: orders.php?completed=1");
        exit();
    } else {
        // Either order not found or status not 'delivered'
        header("Location: orders.php?error=not_updated");
        exit();
    }
} else {
    // Invalid access, redirect to orders page
    header("Location: orders.php");
    exit();
}
?>
