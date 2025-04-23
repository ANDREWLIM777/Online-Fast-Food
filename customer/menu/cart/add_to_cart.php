<?php
session_start();
require '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['item_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$itemId = (int) $_POST['item_id'];
$customerId = $_SESSION['customer_id'] ?? null;

if (!$customerId) {
    echo json_encode(['status' => 'error', 'message' => 'Please log in first.']);
    exit;
}

// Fetch item details
$stmt = $conn->prepare("SELECT id, item_name, price FROM menu_items WHERE id = ? AND is_available = 1");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($item = $result->fetch_assoc()) {
    // Check if item already in DB cart
    $check = $conn->prepare("SELECT quantity FROM cart WHERE customer_id = ? AND item_id = ?");
    $check->bind_param("ii", $customerId, $itemId);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {
        // Item already in cart → update qty
        $update = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE customer_id = ? AND item_id = ?");
        $update->bind_param("ii", $customerId, $itemId);
        $update->execute();
    } else {
        // Insert new cart item
        $insert = $conn->prepare("INSERT INTO cart (customer_id, item_id, quantity) VALUES (?, ?, 1)");
        $insert->bind_param("ii", $customerId, $itemId);
        $insert->execute();
    }

    // Update session cart
    $_SESSION['cart'][$itemId]['id']    = $item['id'];
    $_SESSION['cart'][$itemId]['name']  = $item['item_name'];
    $_SESSION['cart'][$itemId]['price'] = $item['price'];
    $_SESSION['cart'][$itemId]['qty']   = ($_SESSION['cart'][$itemId]['qty'] ?? 0) + 1;

    $cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));

    echo json_encode([
        'status'    => 'success',
        'message'   => 'Item added.',
        'cartCount' => $cartCount
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Item not found.']);
exit;
