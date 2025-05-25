<?php
session_start();
require '../db_connect.php';

// Block guests from viewing cart
if (!empty($_SESSION['is_guest'])) {
    $_SESSION['guest_notice'] = "Guests cannot view the cart. Please log in to continue.";
    header("Location: ../menu.php");
    exit();
}

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

// 🔄 Remove item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $itemId = (int) $_POST['item_id'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ? AND item_id = ?");
    $stmt->bind_param("ii", $customerId, $itemId);
    $stmt->execute();
    header("Location: cart.php?removed=1");
    exit();
}

// Fetch cart items
$total = 0;
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
    $total += $row['quantity'] * $row['price'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>🛒 Cart - Brizo Fast Food Melaka</title>
  <link rel="stylesheet" href="cart.css">
  <link rel="stylesheet" href="remove_from_cart.css">
  <style>
    table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }
    th, td {
      padding: 12px;
      text-align: center;
      border-bottom: 1px solid #ddd;
    }
    th {
      background-color: #f8f8f8;
      font-weight: bold;
    }
    .total-row td {
      font-weight: bold;
      background-color: #f1f1f1;
    }
    .qty-controls {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
    }
    .qty-btn {
      width: 30px;
      height: 30px;
      border: 1px solid #ccc;
      background-color: #fff;
      cursor: pointer;
      border-radius: 4px;
      font-size: 16px;
    }
    .qty-btn:hover {
      background-color: #f0f0f0;
    }
    .qty-input {
      width: 60px;
      padding: 5px;
      text-align: center;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 14px;
    }
    .qty-input::-webkit-inner-spin-button,
    .qty-input::-webkit-outer-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    .qty-input[type=number] {
      -moz-appearance: textfield;
    }
    .remove-btn {
      background-color: #ff4d4d;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
      cursor: pointer;
    }
    .remove-btn:hover {
      background-color: #e60000;
    }
    .back-menu {
      display: inline-block;
      margin: 10px 5px;
      padding: 10px 20px;
      background-color: #ffa751;
      color: white;
      text-decoration: none;
      border-radius: 4px;
    }
    .back-menu:hover {
      background-color: #ff8c00;
    }
  </style>
</head>
<body>

<div class="cart-wrapper">
<div class="ultra-toast" id="ultra-toast">
  <div class="checkmark-animation">
    <svg viewBox="0 0 52 52">
      <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
      <path class="checkmark-check" fill="none" d="M14 27l8 8 16-16"/>
    </svg>
  </div>
  <div class="toast-text">✅ Cart Updated!</div>
</div>

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
        <?php foreach ($cartItems as $item): ?>
        <tr data-id="<?= $item['item_id'] ?>">
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td>
            <div class="qty-controls">
              <button class="qty-btn" data-change="-1">-</button>
              <input type="number" class="qty-input" value="<?= $item['quantity'] ?>" min="1" max="30" step="1" data-id="<?= $item['item_id'] ?>">
              <button class="qty-btn" data-change="1">+</button>
            </div>
          </td>
          <td class="price" data-price="<?= $item['price'] ?>"><?= number_format($item['price'], 2) ?></td>
          <td class="subtotal"><?= number_format($item['quantity'] * $item['price'], 2) ?></td>
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
          <td colspan="2" id="total-amount">RM <?= number_format($total, 2) ?></td>
        </tr>
      </tbody>
    </table>

    <a href="../menu.php" class="back-menu">⬅️ Continue Shopping</a>
    <a href="../../../payment/brizo-fast-food-payment/payment.php" class="back-menu">Proceed to Payment ➡️</a>
  <?php endif; ?>
</div>

<?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
  <div class="ultra-toast show">
    <div class="checkmark-animation">
      <svg viewBox="0 0 52 52">
        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none" />
        <path class="checkmark-check" fill="none" d="M14 27l8 8 16-16" />
      </svg>
    </div>
    <div class="toast-text">🗑️ Item successfully removed!</div>
  </div>
<?php endif; ?>

<script src="cart.js"></script>
<?php include '../../menu_icon.php'; ?>
<?php include '../../footer.php'; ?>
</body>
</html>