<?php
require 'db_conn.php';

$id = $_GET['id'] ?? null;
if (!$id) die('Missing order ID.');

// Enquire about orders and customer information
$stmt = $pdo->prepare("SELECT o.*, c.fullname, c.email 
                       FROM orders o 
                       LEFT JOIN customers c ON o.customer_id = c.id 
                       WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) die('Order not found.');

// Query product details (order_items + menu_items)
$stmt = $pdo->prepare("
    SELECT oi.item_id, oi.quantity, oi.price, oi.total, m.item_name
    FROM order_items oi
    LEFT JOIN menu_items m ON oi.item_id = m.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order['order_id']]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Details</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: Arial, sans-serif; background: #fff; color: #000; padding: 2rem; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .btn {
      background: #c0a23d;
      color: #000;
      padding: 10px 20px;
      text-decoration: none;
      border-radius: 8px;
      font-weight: bold;
    }
    h2 { color: #c0a23d; }
    .info { margin-bottom: 2rem; }
    .info p { margin: 0.4rem 0; }
    table { width: 100%; border-collapse: collapse; }
    table th, table td { border: 1px solid #ccc; padding: 10px; text-align: left; }
    table th { background: #eee; }
  </style>
</head>
<body>
  <div class="header">
    <a href="index.php" class="btn"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="#" class="btn" onclick="window.print()"><i class="fas fa-print"></i> Print</a>
  </div>

  <h2>Order: <?= htmlspecialchars($order['order_id']) ?></h2>

  <div class="info">
    <p><strong>Customer:</strong> <?= htmlspecialchars($order['fullname'] ?? 'Guest') ?> (<?= htmlspecialchars($order['email'] ?? 'N/A') ?>)</p>
    <p><strong>Date:</strong> <?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>
    <p><strong>Total:</strong> RM <?= number_format($order['total'], 2) ?></p>
  </div>

  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th>Unit Price</th>
        <th>Quantity</th>
        <th>Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orderItems as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?></td>
          <td>RM <?= number_format($item['price'], 2) ?></td>
          <td><?= $item['quantity'] ?></td>
          <td>RM <?= number_format($item['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="font-weight:bold; background:#f0f0f0">
        <td colspan="3" style="text-align:right">Grand Total:</td>
        <td>RM <?= number_format($order['total'], 2) ?></td>
      </tr>
    </tbody>
  </table>
</body>
</html>
