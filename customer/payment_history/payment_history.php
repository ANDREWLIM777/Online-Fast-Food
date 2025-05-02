<?php
session_start();
require '../db_connect.php';

// 🔐 Block guest access
if (!isset($_SESSION['customer_id']) || !empty($_SESSION['is_guest'])) {
    echo "<script>alert('Please log in to view payment history.'); window.location.href='../login.php';</script>";
    exit;
}

$customerId = $_SESSION['customer_id'];

// ✅ Fetch payment history for customer
$stmt = $conn->prepare("
    SELECT p.order_id, p.date, p.amount, p.status, p.method
    FROM payment_history p
    JOIN orders o ON p.order_id = o.id
    WHERE o.customer_id = ?
    ORDER BY p.date DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🧾 Payment History - Brizo</title>
  <link rel="stylesheet" href="payment_history.css">
</head>
<body>

<div class="payment-history-container">
  <h1>🧾 Your Payment History</h1>

  <?php if (empty($payments)): ?>
    <p class="no-data">You have no payment records.</p>
  <?php else: ?>
    <table class="history-table">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Date</th>
          <th>Amount (RM)</th>
          <th>Status</th>
          <th>Method</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $payment): ?>
          <tr>
            <td><?= htmlspecialchars($payment['order_id']) ?></td>
            <td><?= date('d M Y, h:i A', strtotime($payment['date'])) ?></td>
            <td><?= number_format($payment['amount'], 2) ?></td>
            <td class="status <?= strtolower($payment['status']) ?>">
              <?= htmlspecialchars($payment['status']) ?>
            </td>
            <td><?= htmlspecialchars($payment['method']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <a href="/Online-Fast-Food/customer/menu/menu.php" class="back-btn">⬅️ Back to Menu</a>
</div>

</body>
</html>
