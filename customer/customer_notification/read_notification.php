<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['customer_id']) || !empty($_SESSION['is_guest'])) {
    header("Location: ../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];
$notificationId = (int) ($_GET['id'] ?? 0);

if ($notificationId === 0) {
    header("Location: customer_notification.php");
    exit();
}

// Mark as read
$update = $conn->prepare("UPDATE customer_notifications SET is_read = 1 WHERE id = ? AND customer_id = ?");
$update->bind_param("ii", $notificationId, $customerId);
$update->execute();

// Fetch notification
$stmt = $conn->prepare("
    SELECT cn.title, cn.message, cn.created_at, cn.type, n.title AS source_title
    FROM customer_notifications cn
    LEFT JOIN notifications n ON cn.notification_id = n.id
    WHERE cn.id = ? AND cn.customer_id = ?
");
$stmt->bind_param("ii", $notificationId, $customerId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "Notification not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($data['title']) ?> - Brizo</title>
  <link rel="stylesheet" href="customer_notification.css">
  <link rel="stylesheet" href="../menu/order_now_button/order_now.css">

</head>
<body>

<div class="top-right-button">
  <a href="../menu/menu.php" class="button-cool-effect">🍔 Order Now</a>
</div>

  <div class="notif-container">
    <h1><?= htmlspecialchars($data['title']) ?></h1>
    <div class="notif-card">
      <div class="notif-header">
        <span class="type-tag <?= $data['type'] ?>"><?= ucfirst($data['type']) ?></span>
        <span class="notif-time"><?= date('d M Y, H:i', strtotime($data['created_at'])) ?></span>
      </div>
      <p><?= nl2br(htmlspecialchars($data['message'])) ?></p>
      <?php if ($data['source_title']): ?>
        <small>🔄 From admin: <i><?= htmlspecialchars($data['source_title']) ?></i></small>
      <?php endif; ?>
    </div>
    <a href="customer_notification.php" class="back-link">⬅ Back to Notifications</a>
  </div>
</body>
</html>

<?php include '../footer.php'; ?>
<?php include '../menu_icon.php'; ?>
