<?php
session_start();
require '../db_connect.php';

header('Content-Type: application/json');

// 🔒 Require logged in customer
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

// 📥 Get input
$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($itemId <= 0 || $quantity < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid item or quantity.']);
    exit;
}

// ✅ Update the cart table
$stmt = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE customer_id = ? AND item_id = ?");
$stmt->bind_param("iii", $quantity, $customerId, $itemId);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Cart updated.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update cart.']);
}
exit;
