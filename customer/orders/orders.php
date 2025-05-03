<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: ../customer/login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$ordersResult = $stmt->get_result();

$orders = [];
while ($order = $ordersResult->fetch_assoc()) {
    $orderId = $order['order_id'];
    $itemStmt = $conn->prepare("
        SELECT oi.quantity, oi.price, mi.item_name
        FROM order_items oi
        JOIN menu_items mi ON oi.item_id = mi.id
        WHERE oi.order_id = ?
    ");
    $itemStmt->bind_param("s", $orderId);
    $itemStmt->execute();
    $itemsResult = $itemStmt->get_result();
    $order['items'] = $itemsResult->fetch_all(MYSQLI_ASSOC);
    $orders[] = $order;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🧾 My Orders</title>
  <link rel="stylesheet" href="orders.css">
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
      border-left: 6px solid #ccc;
      padding: 1rem 1.2rem;
      margin-bottom: 1.5rem;
      border-radius: 8px;
      background: #fdfdfd;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    .notif-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    }
    .notif-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.3rem;
    }
    .type-tag {
      padding: 2px 8px;
      border-radius: 5px;
      font-size: 0.75rem;
      font-weight: bold;
      text-transform: uppercase;
      color: white;
    }
    .status-pending   { background-color: #e67e22; }
    .status-completed { background-color: #00b894; }
    .status-refunded  { background-color: #d63031; }

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
    }
    .item-list {
      display: none;
      margin-top: 1rem;
      font-size: 15px;
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
  </style>
</head>
<body>

<div class="notif-container">
  <h1>🧾 Your Orders</h1>

  <?php if (empty($orders)): ?>
    <p class="empty">You haven’t made any orders yet.</p>
  <?php else: ?>
    <?php foreach ($orders as $order): ?>
      <div class="notif-card">
        <div class="notif-header">
          <div>
            <strong>Order #<?= htmlspecialchars($order['order_id']) ?></strong><br>
            <span class="notif-time"><?= date("d M Y, h:i A", strtotime($order['created_at'])) ?></span>
          </div>
          <div class="type-tag status-<?= $order['status'] ?>">
            <?= ucfirst($order['status']) ?>
          </div>
        </div>
        <div class="total">Total: RM <?= number_format($order['total'], 2) ?></div>

        <div class="toggle-items" onclick="toggleItems(this)">📦 View Items</div>
        <div class="item-list">
          <?php foreach ($order['items'] as $item): ?>
            <div class="item">
              <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
              <span>RM <?= number_format($item['price'] * $item['quantity'], 2) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <a class="back-link" href="/Online-Fast-Food/customer/menu/menu.php">Back to Menu</a>
</div>

<script>
  function toggleItems(el) {
    const box = el.nextElementSibling;
    const show = box.style.display !== 'block';
    box.style.display = show ? 'block' : 'none';
    el.textContent = show ? '🔽 Hide Items' : '📦 View Items';
  }
</script>

</body>
</html>

<?php include '../menu_icon.php'; ?>
<?php include '../footer.php'; ?>
<?php include '../footer2.php'; ?>