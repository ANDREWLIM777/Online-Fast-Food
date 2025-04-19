<?php
session_start();
require 'db_connect.php';

// Retrieve cart data
$cart = $_SESSION['cart'] ?? [];
$total = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🛒 Cart - Brizo Fast Food Melaka</title>
  <link rel="stylesheet" href="menu.css">
  <style>
    .cart-wrapper {
      max-width: 800px;
      margin: 40px auto;
      padding: 20px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    h1 {
      text-align: center;
      margin-bottom: 30px;
      color: #d63f3f;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    th, td {
      padding: 12px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }

    th {
      background-color: #f9f9f9;
    }

    .total-row td {
      font-weight: bold;
      font-size: 1.1em;
      text-align: right;
    }

    .remove-btn {
      background-color: #e74c3c;
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
    }

    .remove-btn:hover {
      background-color: #c0392b;
    }

    .back-menu {
      display: inline-block;
      padding: 10px 16px;
      background-color: #1a8917;
      color: white;
      border-radius: 8px;
      text-decoration: none;
    }

    .back-menu:hover {
      background-color: #166f14;
    }
  </style>
</head>
<body>

<div class="cart-wrapper">
  <h1>Your Cart</h1>

  <?php if (empty($cart)): ?>
    <p>Your cart is empty.</p>
    <a href="menu.php" class="back-menu">⬅️ Back to Menu</a>
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
        <?php foreach ($cart as $item): 
          $subtotal = $item['qty'] * $item['price'];
          $total += $subtotal;
        ?>
        <tr>
          <td><?= htmlspecialchars($item['name']) ?></td>
          <td><?= $item['qty'] ?></td>
          <td><?= number_format($item['price'], 2) ?></td>
          <td><?= number_format($subtotal, 2) ?></td>
          <td>
            <form method="POST" action="remove_from_cart.php">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
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

    <a href="menu.php" class="back-menu">⬅️ Continue Shopping</a>
  <?php endif; ?>
</div>

</body>
</html>
