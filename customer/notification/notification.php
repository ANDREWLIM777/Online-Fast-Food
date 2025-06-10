<?php
session_start();
require '../db_connect.php';

// (Optional) Block guests or non-admins if needed
// if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

// 🔒 Block guests
if (!isset($_SESSION['customer_id']) || !empty($_SESSION['is_guest'])) {
  header("Location: ../login.php");
  exit();
}

$customerId = $_SESSION['customer_id'];

$query = "
  SELECT n.*, a1.name AS created_by_name, a2.name AS reposted_by_name
  FROM notifications n
  JOIN admin a1 ON n.created_by = a1.id
  LEFT JOIN admin a2 ON n.reposted_by = a2.id
  ORDER BY n.is_pinned DESC, n.created_at DESC
";

$result = $conn->query($query);
$notifications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>📣 News - Brizo Fast Food Melaka</title>
  <link rel="stylesheet" href="notification.css">
</head>
<body>
  <div class="notification-container">
    <h1>📣 What's News?</h1>

    <?php if (empty($notifications)): ?>
      <p>No notifications to show.</p>
    <?php else: ?>
      <ul class="notification-list">
        <?php foreach ($notifications as $n): ?>
          <li class="notification <?= $n['is_pinned'] ? 'pinned' : '' ?>">
            <h2><?= htmlspecialchars($n['title']) ?></h2>
            <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>
            <div class="meta">
              <span>🕒 <?= date("d M Y, h:i A", strtotime($n['created_at'])) ?></span>
              <span>👤 Posted by: <?= htmlspecialchars($n['created_by_name']) ?></span>
              <?php if ($n['reposted_by_name']): ?>
                <span>🔁 Reposted by <?= htmlspecialchars($n['reposted_by_name']) ?> at <?= date("d M Y, h:i A", strtotime($n['reposted_at'])) ?></span>
              <?php endif; ?>
              <?php if ($n['is_pinned']): ?>
                <span class="badge">📌 Pinned</span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <a href="/Online-Fast-Food/customer/menu/menu.php" class="back-menu">Back</a>
  </div>
  <?php include '../menu_icon.php'; ?>
  <?php include '../footer.php'; ?>
</body>
</html>
