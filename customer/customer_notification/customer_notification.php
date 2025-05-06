<?php
session_start();
require '../db_connect.php';

// 🔒 Block guests
if (!isset($_SESSION['customer_id']) || !empty($_SESSION['is_guest'])) {
    header("Location: ../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];

// 📥 Fetch notifications for the logged-in customer
$stmt = $conn->prepare("
    SELECT cn.id, cn.title, cn.message, cn.type, cn.is_read, cn.created_at, n.title AS source_title
    FROM customer_notifications cn
    LEFT JOIN notifications n ON cn.notification_id = n.id
    WHERE cn.customer_id = ?
    ORDER BY cn.is_read ASC, cn.created_at DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>📩 My Notifications - Brizo</title>
  <link rel="stylesheet" href="customer_notification.css">
  <link rel="stylesheet" href="../menu/order_now_button/order_now.css">

</head>
<body>

<div class="top-right-button">
  <a href="../menu/menu.php" class="button-cool-effect">🍔 Order Now</a>
</div>

  <div class="notif-container">
    <h1>📩 My Notifications</h1>

    <?php if (empty($notifications)): ?>
      <p class="empty">You have no notifications yet.</p>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <a href="read_notification.php?id=<?= $n['id'] ?>" class="notif-link">
  <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>">
    <div class="notif-header">
      <span class="type-tag <?= $n['type'] ?>"><?= ucfirst($n['type']) ?></span>
      <span class="notif-time"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
    </div>
    <h3><?= htmlspecialchars($n['title']) ?></h3>
    <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>
    <?php if ($n['source_title']): ?>
      <small>🔄 From admin: <i><?= htmlspecialchars($n['source_title']) ?></i></small>
    <?php endif; ?>
  </div>
</a>

      <?php endforeach; ?>
    <?php endif; ?>

    <a href="/Online-Fast-Food/customer/menu/menu.php" class="back-link">⬅ Back to Menu</a>
  </div>
</body>
</html>

<?php include '../footer.php'; ?>
<?php include '../menu_icon.php'; ?>
