<?php
session_start();
require '../db_connect.php';

header('Content-Type: application/json');

// 🔒 Secure - Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['item_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

// 📥 Input
$itemId = (int) $_POST['item_id'];
$customerId = $_SESSION['customer_id'] ?? null;
$isGuest = $_SESSION['is_guest'] ?? false;

// ❌ Guest users are not allowed to add to cart
if ($isGuest || !$customerId) {
    echo json_encode(['status' => 'error', 'message' => 'Guests cannot order. Please login!']);
    exit;
}

// ✅ Fetch item details
$stmt = $conn->prepare("SELECT id, item_name, price FROM menu_items WHERE id = ? AND is_available = 1");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($item = $result->fetch_assoc()) {
    // 🔍 Check if item already exists in cart
    $check = $conn->prepare("SELECT quantity FROM cart WHERE customer_id = ? AND item_id = ?");
    $check->bind_param("ii", $customerId, $itemId);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {
        // ➡️ Update existing cart
        $update = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE customer_id = ? AND item_id = ?");
        $update->bind_param("ii", $customerId, $itemId);
        $update->execute();
    } else {
        // ➡️ Insert new cart item
        $insert = $conn->prepare("INSERT INTO cart (customer_id, item_id, quantity) VALUES (?, ?, 1)");
        $insert->bind_param("ii", $customerId, $itemId);
        $insert->execute();
    }

    // 🔄 Fetch latest cart count
    $cartCount = 0;
    $countStmt = $conn->prepare("SELECT SUM(quantity) AS total_qty FROM cart WHERE customer_id = ?");
    $countStmt->bind_param("i", $customerId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($row = $countResult->fetch_assoc()) {
        $cartCount = (int) ($row['total_qty'] ?? 0);
    }

    echo json_encode([
        'status'    => 'success',
        'message'   => 'Item added to cart!',
        'cartCount' => $cartCount
    ]);
    exit;
}

// ❌ Item not found
echo json_encode(['status' => 'error', 'message' => 'Item not found or unavailable.']);
exit;
?>
