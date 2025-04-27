<?php
session_start();
require '../db_connect.php';
header('Content-Type: application/json');

$customerId = $_SESSION['customer_id'] ?? null;
$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

if (!$customerId || !$itemId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing customer or item ID.'
    ]);
    exit;
}

// Delete item from customer's cart in DB
$stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ? AND item_id = ?");
$stmt->bind_param("ii", $customerId, $itemId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Item removed from cart.'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Item not found or already removed.'
    ]);
}
exit;
