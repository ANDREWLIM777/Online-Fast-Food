<?php
session_start();
require '../db_connect.php';

// Check if order details exist in session
if (!isset($_SESSION['last_order'])) {
    header("Location: cart.php");
    exit();
}

$lastOrder = $_SESSION['last_order'];
$orderId = $lastOrder['order_code'];

// Fetch order details from the orders table
$stmt = $conn->prepare("
    SELECT items
    FROM orders
    WHERE order_id = ?
");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

// Check if the order was found
if (!$order) {
    file_put_contents('confirmation_errors.log', date('Y-m-d H:i:s') . " - Order not found for order_id: $orderId\n", FILE_APPEND);
    header("Location: cart.php?error=order_not_found");
    exit();
}

// Decode the items JSON if it exists
$itemsJson = $order['items'];
file_put_contents('confirmation_errors.log', date('Y-m-d H:i:s') . " - Items JSON for order $orderId: " . ($itemsJson ?? 'NULL') . "\n", FILE_APPEND);
$orderItems = json_decode($itemsJson, true);
file_put_contents('confirmation_errors.log', date('Y-m-d H:i:s') . " - Decoded orderItems for order $orderId: " . json_encode($orderItems) . "\n", FILE_APPEND);

// Prepare items for display
$items = [];
if (!empty($orderItems)) {
    // Use the items from the JSON
    foreach ($orderItems as $item) {
        $items[] = [
            'item_name' => $item['item_name'],
            'photo' => $item['photo'],
            'quantity' => $item['quantity'],
            'price' => $item['price']
        ];
    }
} else {
    // Fallback: Fetch items from order_items table and join with menu_items
    $stmt = $conn->prepare("
        SELECT oi.item_id, oi.quantity, oi.price, mi.item_name, mi.photo
        FROM order_items oi
        LEFT JOIN menu_items mi ON oi.item_id = mi.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'item_name' => $row['item_name'] ?? 'Unknown Item',
            'photo' => $row['photo'] ?? 'default-food-image.jpg',
            'quantity' => $row['quantity'],
            'price' => $row['price']
        ];
    }
    $stmt->close();
}

// Format the payment method for display
$paymentMethodDisplay = '';
switch ($lastOrder['method']) {
    case 'card':
        $paymentMethodDisplay = 'Card';
        break;
    case 'online_banking':
        $paymentMethodDisplay = 'Online Banking';
        break;
    case 'digital_wallet':
        $paymentMethodDisplay = 'Digital Wallet';
        break;
    default:
        $paymentMethodDisplay = ucfirst($lastOrder['method']);
}

// Base URL for images
$imageBaseUrl = '/Online-Fast-Food/Admin/Manage_Menu_Item/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation - Brizo Fast Food Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .success-message { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f8f8; }
        .cart-item img { width: 70px; height: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Order Confirmation</h2>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> Payment Successful! Thank you for your order.
        </div>
        <p><strong>Order ID:</strong> <?= htmlspecialchars($lastOrder['order_code']) ?></p>
        <p><strong>Amount:</strong> RM <?= number_format($lastOrder['amount'], 2) ?></p>
        <p><strong>Payment Method:</strong> <?= htmlspecialchars($paymentMethodDisplay) ?></p>
        <p><strong>Payment Details:</strong> <?= htmlspecialchars($lastOrder['payment_details'] ?? 'N/A') ?></p>
        <p><strong>Delivery Method:</strong> <?= htmlspecialchars(ucfirst($lastOrder['delivery_method'])) ?></p>
        <?php if ($lastOrder['delivery_method'] === 'delivery'): ?>
            <p><strong>Delivery Address:</strong> <?= htmlspecialchars($lastOrder['delivery_address']) ?></p>
        <?php endif; ?>
        <p><strong>Order Date:</strong> <?= htmlspecialchars($lastOrder['timestamp']) ?></p>
        <h3>Items Ordered:</h3>
        <?php if (empty($items)): ?>
            <p>No items found for this order.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Price (RM)</th>
                        <th>Subtotal (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="cart-item">
                                <img src="<?= htmlspecialchars($imageBaseUrl . ($item['photo'] ?? 'default-food-image.jpg')) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                            </td>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><?= htmlspecialchars($item['quantity']) ?></td>
                            <td><?= number_format($item['price'], 2) ?></td>
                            <td><?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <a href="cart.php">Back to Cart</a>
    </div>
    <?php unset($_SESSION['last_order']); ?>
</body>
</html>