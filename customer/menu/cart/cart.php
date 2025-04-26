<?php
session_start();
require '../db_connect.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $itemId = (int) $_POST['item_id'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ? AND item_id = ?");
    $stmt->bind_param("ii", $customerId, $itemId);
    $stmt->execute();
    header("Location: cart.php?removed=1");
    exit();
}

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
    .update-feedback {
      position: fixed;
      top: 80px;
      right: 40px;
      background: #2ecc71;
      color: #fff;
      padding: 12px 18px;
      border-radius: 8px;
      font-weight: bold;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
      opacity: 0;
      transform: translateY(-10px);
      transition: all 0.4s ease;
      z-index: 9999;
    }
    .update-feedback.show {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>
<body>

<div class="cart-wrapper">
  <div id="update-feedback" class="update-feedback">✅ Cart Updated!</div>
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
        <tr data-id="<?= $item['item_id'] ?>">
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td>
            <div class="qty-controls">
              <button class="qty-btn" data-change="-1">-</button>
              <span class="qty-number"><?= $item['quantity'] ?></span>
              <button class="qty-btn" data-change="1">+</button>
            </div>
          </td>
          <td class="price" data-price="<?= $item['price'] ?>"><?= number_format($item['price'], 2) ?></td>
          <td class="subtotal"><?= number_format($subtotal, 2) ?></td>
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
  <?php endif; ?>
</div>

<?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
  <div class="toast-message" id="toast">Item successfully removed!</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('toast');
  if (toast) {
    toast.classList.add('show');
    setTimeout(() => toast.remove(), 4000);
  }

  const showFeedback = (msg = "✅ Cart Updated!") => {
    const el = document.getElementById("update-feedback");
    el.textContent = msg;
    el.classList.add("show");
    setTimeout(() => el.classList.remove("show"), 2000);
  };

  document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
      const row = this.closest('tr');
      const itemId = row.dataset.id;
      const change = parseInt(this.dataset.change);
      const qtyElem = row.querySelector('.qty-number');
      const price = parseFloat(row.querySelector('.price').dataset.price);
      let currentQty = parseInt(qtyElem.textContent);

      const newQty = currentQty + change;
      if (newQty < 1) return;

      const formData = new URLSearchParams();
      formData.append('item_id', itemId);
      formData.append('quantity', newQty);

      const res = await fetch('update_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
      });

      const data = await res.json();
      if (data.status === 'success') {
        qtyElem.textContent = newQty;
        const newSubtotal = (price * newQty).toFixed(2);
        row.querySelector('.subtotal').textContent = newSubtotal;

        let total = 0;
        document.querySelectorAll('.subtotal').forEach(td => {
          total += parseFloat(td.textContent);
        });
        document.getElementById('total-amount').textContent = 'RM ' + total.toFixed(2);

        showFeedback(); // ✅ Show animated success
      } else {
        alert(data.message || 'Failed to update cart.');
      }
    });
  });
});
</script>

</body>
</html>
