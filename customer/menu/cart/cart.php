<?php
session_start();
require '../db_connect.php';

// Validate session
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

// 🔄 Handle item removal (before fetching cart)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $itemId = (int) $_POST['item_id'];

    $deleteStmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ? AND item_id = ?");
    $deleteStmt->bind_param("ii", $customerId, $itemId);
    $deleteStmt->execute();

    // Redirect to refresh the cart view
    header("Location: cart.php");
    exit();
}

$total = 0;

// 📦 Fetch cart items
$stmt = $conn->prepare("
    SELECT c.item_id, c.quantity, m.item_name, m.price
    FROM cart c
    JOIN menu_items m ON c.item_id = m.id
    WHERE c.customer_id = ?
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$cartItems = [];
while ($row = $result->fetch_assoc()) {
    $cartItems[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>🛒 Cart - Brizo Fast Food Melaka</title>
  <link rel="stylesheet" href="cart.css">
</head>
<body>

<div class="cart-wrapper">
  <h1>Your Cart</h1>

  <?php if (empty($cartItems)): ?>
    <p>Your cart is empty.</p>
    <a href="../menu.php" class="back-menu">⬅️ Back to Menu</a>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th>Qty</th>
          <th>Price (RM)</th>
          <th>Subtotal (RM)</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cartItems as $item): 
          $subtotal = $item['quantity'] * $item['price'];
          $total += $subtotal;
        ?>
        <tr>
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td><?= $item['quantity'] ?></td>
          <td><?= number_format($item['price'], 2) ?></td>
          <td><?= number_format($subtotal, 2) ?></td>
          <td>
            <form method="POST" action="cart.php">
              <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
              <button type="submit" class="remove-btn">Remove</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="3">Total:</td>
          <td colspan="2">RM <?= number_format($total, 2) ?></td>
        </tr>
      </tbody>
    </table>

    <a href="../menu.php" class="back-menu">⬅️ Continue Shopping</a>
  <?php endif; ?>
</div>

</body>
</html>
