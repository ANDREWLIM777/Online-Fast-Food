<?php
session_start();
require '../db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];

// Fetch all orders by customer
$stmt = $conn->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$ordersResult = $stmt->get_result();

$orders = [];
while ($order = $ordersResult->fetch_assoc()) {
    $orderId = $order['order_id'];  // This is VARCHAR string, not int

    $itemStmt = $conn->prepare("
        SELECT oi.quantity, oi.price, mi.item_name
        FROM order_items oi
        JOIN menu_items mi ON oi.item_id = mi.id
        WHERE oi.order_id = ?
    ");
    $itemStmt->bind_param("s", $orderId);  // bind as string, not int
    $itemStmt->execute();
    $itemsResult = $itemStmt->get_result();
    $items = $itemsResult->fetch_all(MYSQLI_ASSOC);

    // Assign items to current order
    $order['items'] = $items;

    // Optional: recalc total based on items (if you want to double-check)
    $calcTotal = 0;
    foreach ($items as $item) {
        $calcTotal += $item['quantity'] * $item['price'];
    }
    $order['calc_total'] = $calcTotal;

    $orders[] = $order;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>🧾 My Orders</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #fffbed;
      color: #333;
      margin: 0;
      padding: 0;
    }

    .notif-container {
      max-width: 800px;
      margin: 3rem auto;
      padding: 2rem;
      background: white;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
      border-radius: 12px;
    }

    h1 {
      text-align: center;
      color: #e17055;
      margin-bottom: 2rem;
    }

    .notif-card {
      display: flex;
      background: #fdfdfd;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      overflow: hidden;
    }

    .status-stripe {
      width: 6px;
    }

    .status-pending .status-stripe     { background-color: #f39c12; }
    .status-preparing .status-stripe   { background-color: #e67e22; }
    .status-delivering .status-stripe  { background-color: #3498db; }
    .status-delivered .status-stripe   { background-color: rgb(204, 204, 46); }
    .status-completed .status-stripe   { background-color: #27ae60; }

    .notif-content {
      padding: 1rem 1.2rem;
      flex: 1;
    }

    .notif-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }

    .type-tag {
      padding: 4px 10px;
      border-radius: 5px;
      font-size: 0.75rem;
      font-weight: bold;
      text-transform: uppercase;
      color: white;
    }

    .status-pending .type-tag     { background-color: #f39c12; }
    .status-preparing .type-tag   { background-color: #e67e22; }
    .status-delivering .type-tag  { background-color: #3498db; }
    .status-delivered .type-tag   { background-color: rgb(204, 204, 46); }
    .status-completed .type-tag   { background-color: #27ae60; }

    .notif-time {
      font-size: 0.8rem;
      color: #999;
    }

    .toggle-items {
      margin-top: 0.4rem;
      color: #0984e3;
      cursor: pointer;
      font-weight: 500;
      font-size: 14px;
      transition: color 0.2s ease;
    }

    .toggle-items:hover {
      text-decoration: underline;
    }

    .item-list {
      display: none;
      margin-top: 1rem;
      font-size: 15px;
      animation: fadeIn 0.3s ease;
    }

    .item {
      display: flex;
      justify-content: space-between;
      margin: 0.3rem 0;
    }

    .total {
      font-weight: bold;
      font-size: 15px;
      margin-top: 0.6rem;
    }

    .empty {
      text-align: center;
      font-style: italic;
      color: #aaa;
    }

    .back-link {
      display: inline-block;
      margin-top: 2rem;
      text-decoration: none;
      color: #2d3436;
      background: #ffeaa7;
      padding: 8px 14px;
      border-radius: 6px;
      font-weight: bold;
      transition: background 0.3s ease;
    }

    .back-link:hover {
      background: #fab1a0;
      color: black;
    }

    button.complete-btn {
      background: #27ae60;
      border: none;
      padding: 10px 16px;
      color: white;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
      margin-top: 1rem;
    }

    button.complete-btn:hover {
      background: #1e8449;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Notification styles & animations */
    #orderCompletedNotif {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background-color: #27ae60;
      color: white;
      padding: 20px 40px;
      font-size: 1.4rem;
      font-weight: bold;
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(39, 174, 96, 0.7);
      opacity: 0;
      pointer-events: none;
      z-index: 9999;
      user-select: none;
    }

    @keyframes fadeInNotif {
      from { opacity: 0; transform: translate(-50%, -60%) scale(0.8); }
      to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    }

    @keyframes fadeOutNotif {
      from { opacity: 1; transform: translate(-50%, -50%) scale(1); }
      to { opacity: 0; transform: translate(-50%, -40%) scale(0.8); }
    }
  </style>
</head>
<body>

<div class="notif-container">
  <h1>Your Orders</h1>

  <label style="display: block; margin-bottom: 1.5rem;">
  <input type="checkbox" id="hideCompletedCheckbox" onchange="toggleCompletedOrders()" />
  Hide Completed Orders
</label>

  <?php if (empty($orders)): ?>
    <p class="empty">You haven’t made any orders yet.</p>
  <?php else: ?>
    <?php foreach ($orders as $order): ?>
      <div class="notif-card status-<?= htmlspecialchars($order['status']) ?>">
        <div class="status-stripe"></div>
        <div class="notif-content">
          <div class="notif-header">
            <div>
              <strong>Order #<?= htmlspecialchars($order['order_id']) ?></strong><br>
              <span class="notif-time"><?= date("d M Y, h:i A", strtotime($order['created_at'])) ?></span>
            </div>
            <div class="type-tag"><?= ucfirst(htmlspecialchars($order['status'])) ?></div>
          </div>

          <div class="total">Total: RM <?= number_format($order['total'], 2) ?></div>
          <div class="toggle-items" onclick="toggleItems(this)">View Items</div>
          <div class="item-list">
            <?php foreach ($order['items'] as $item): ?>
              <div class="item">
                <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
                <span>RM <?= number_format($item['price'] * $item['quantity'], 2) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($order['status'] === 'delivered'): ?>
            <form action="complete_order.php" method="POST">
              <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
              <button type="submit" class="complete-btn">Complete</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <a class="back-link" href="/Online-Fast-Food/customer/menu/menu.php">Back to Menu</a>
</div>

<!-- Order Completed Notification -->
<div id="orderCompletedNotif" style="display:none;">
  Order Completed
</div>

<script>
  //hide completed orders
      function toggleCompletedOrders() {
  const hide = document.getElementById('hideCompletedCheckbox').checked;
  const completedCards = document.querySelectorAll('.status-completed');
  completedCards.forEach(card => {
    card.style.display = hide ? 'none' : 'flex';
  });
}

  function toggleItems(el) {
    const box = el.nextElementSibling;
    const show = box.style.display !== 'block';
    box.style.display = show ? 'block' : 'none';
    el.textContent = show ? 'Hide Items' : 'View Items';
  }

  // Show notification with animation if URL has ?completed=1
  window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('completed') === '1') {
      const notif = document.getElementById('orderCompletedNotif');
      notif.style.display = 'block';


      // Play fadeIn animation
      notif.style.animation = 'fadeInNotif 0.4s forwards';

      // After 3 seconds, play fadeOut and then hide
      setTimeout(() => {
        notif.style.animation = 'fadeOutNotif 0.4s forwards';
        notif.addEventListener('animationend', () => {
          notif.style.display = 'none';

          // Remove ?completed=1 from URL without reload
          if (window.history.replaceState) {
            const cleanUrl = window.location.href.split('?')[0];
            window.history.replaceState(null, '', cleanUrl);
          }
        }, { once: true });
      }, 4000);
    }
  });
</script>

<?php include '../menu_icon.php'; ?>
<?php include '../footer.php'; ?>
</body>
</html>
