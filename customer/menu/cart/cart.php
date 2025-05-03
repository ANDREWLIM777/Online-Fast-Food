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
              <span class="qty-number"><?= $item['quantity'] ?></span>
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

    <a href="../cart/payment.php" class="back-menu">Proceed to Payment ➡️</a>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const toast = document.querySelector('.ultra-toast.show');
  if (toast) {
    setTimeout(() => toast.classList.remove('show'), 2500);
    setTimeout(() => toast.remove(), 3000);
  }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('toast');
  if (toast) {
    toast.classList.add('show');
    setTimeout(() => toast.remove(), 3000);
  }

  const showFeedback = (msg = "✅ Cart Updated!") => {
    const toast = document.getElementById("ultra-toast");
    toast.querySelector(".toast-text").textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 2500);
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
        row.querySelector('.subtotal').textContent = (price * newQty).toFixed(2);

        let total = 0;
        document.querySelectorAll('.subtotal').forEach(td => {
          total += parseFloat(td.textContent);
        });
        document.getElementById('total-amount').textContent = 'RM ' + total.toFixed(2);
        showFeedback();
      } else {
        alert(data.message || 'Failed to update cart.');
      }
    });
  });
});
</script>
<script src="cart.js"></script>
<?php include '../../menu_icon.php'; ?>
<?php include '../../footer.php'; ?>
<?php include '../../footer2.php'; ?>
</body>
</html>