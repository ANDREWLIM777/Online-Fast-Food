<?php
require 'db_conn.php';

$id = $_GET['id'] ?? null;
$orderId = $_GET['order_id'] ?? null;
$refund = null;

if ($id) {
    // 优先查 refund_requests（LEFT JOIN 避免 JOIN 失败）
    $stmt = $pdo->prepare("
    SELECT r.*, r.created_at AS date, r.customer_id, o.order_id AS real_order_id, o.created_at AS order_date, o.total, o.status AS order_status,
           c.fullname, c.email
    FROM refund_requests r
    LEFT JOIN orders o ON r.order_id = o.order_id
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE r.id = ?
");

    $stmt->execute([$id]);
    $refund = $stmt->fetch();
}

if (!$id || !$refund) {
    if (!$orderId) {
        $orderId = $_GET['id'] ?? null;
    }
    // 查 orders 表中的 refunded 状态
    $stmt = $pdo->prepare("SELECT o.id AS real_order_id, o.order_id, o.created_at AS order_date, o.total, o.status, c.fullname, c.email
                           FROM orders o
                           JOIN customers c ON o.customer_id = c.id
                           WHERE o.order_id = ? AND o.status = 'refunded'");
    $stmt->execute([$orderId]);
    $refund = $stmt->fetch();

    if (!$refund) {
        die('Refund not found.');
    }

    // 构造默认值
    $refund['reason'] = 'Refunded order';
    $refund['details'] = 'No specific refund request submitted.';
    $refund['date'] = $refund['order_date'];
    $refund['status'] = 'refunded';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Refund Details</title>
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
    .reason-box {
      border: 1px solid #ccc;
      padding: 1rem;
      border-radius: 8px;
      background: #f9f9f9;
      margin-top: 1rem;
    }
    img { margin-top: 10px; max-width: 300px; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="header">
  <a href="index.php?tab=refunds" class="btn"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="#" class="btn" onclick="window.print()"><i class="fas fa-print"></i> Print</a>
  </div>

  <h2>Refund for Order: <?= htmlspecialchars($refund['order_id'] ?? $refund['real_order_id']) ?></h2>

  <div class="info">
    <p><strong>Customer:</strong> <?= htmlspecialchars($refund['fullname'] ?? 'Unknown') ?> (<?= htmlspecialchars($refund['email'] ?? '-') ?>)</p>
    <p><strong>Order Date:</strong> <?= date('Y-m-d H:i', strtotime($refund['order_date'])) ?></p>
    <p><strong>Refund Requested:</strong> <?= date('Y-m-d H:i', strtotime($refund['date'])) ?></p>
    <p><strong>Order Total:</strong> RM <?= number_format($refund['total'], 2) ?></p>
    <p><strong>Refund Status:</strong> <?= htmlspecialchars($refund['status']) ?></p>
  </div>

  <div class="reason-box">
    <p><strong>Reason:</strong> <?= htmlspecialchars($refund['reason']) ?></p>
    <p><?= nl2br(htmlspecialchars($refund['details'])) ?></p>
    <?php if (!empty($refund['evidence_path'])): ?>
      <p><strong>Evidence:</strong><br>
        <img src="<?= htmlspecialchars($refund['evidence_path']) ?>" alt="Evidence">
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
