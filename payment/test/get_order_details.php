<?php
require_once 'config.php';

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // Get order details
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }
    
    // Get order items
    $stmt = $conn->prepare("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.id WHERE oi.order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get payment details
    $stmt = $conn->prepare("SELECT * FROM payment_history WHERE order_id = ? ORDER BY date DESC LIMIT 1");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'order' => $order,
        'items' => $items,
        'payment' => $payment ?: ['method' => 'Unknown']
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Order ID not provided']);
}

$conn->close();
?>