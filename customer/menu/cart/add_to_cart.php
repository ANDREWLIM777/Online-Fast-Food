<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $itemId = (int) $_POST['item_id'];

    // Get the item from the DB
    $stmt = $conn->prepare("SELECT id, item_name, price FROM menu_items WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($item = $result->fetch_assoc()) {
        // If item already in cart, increase qty
        if (isset($_SESSION['cart'][$itemId])) {
            $_SESSION['cart'][$itemId]['qty'] += 1;
        } else {
            $_SESSION['cart'][$itemId] = [
                'id' => $item['id'],
                'name' => $item['item_name'],
                'price' => $item['price'],
                'qty' => 1
            ];
        }

        // Calculate new total cart count
        $cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));

        echo json_encode([
            'status' => 'success',
            'cartCount' => $cartCount
        ]);
        exit;
    }
}

echo json_encode([
    'status' => 'error',
    'message' => 'Item not found or invalid request'
]);
exit;
