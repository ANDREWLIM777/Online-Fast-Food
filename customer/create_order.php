<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$user_id = $_SESSION['user_id'];
$cart = json_decode(file_get_contents('php://input'), true);

try {
    $conn->begin_transaction();
    
    // Create order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price) VALUES (?, ?)");
    $total = array_reduce($cart, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);
    $stmt->bind_param("id", $user_id, $total);
    $stmt->execute();
    $order_id = $conn->insert_id;
    
    // Add order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($cart as $item) {
        $stmt->bind_param("iiid", $order_id, $item['item_id'], $item['quantity'], $item['price']);
        $stmt->execute();
    }
    
    $conn->commit();
    echo json_encode({ success: true, order_id: $order_id });
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode({ error: $e->getMessage() });
}
?>