<?php
session_start();
require '../db_connect.php';

// 🛡️ Optional guest restriction
if (isset($_SESSION['is_guest']) && $_SESSION['is_guest']) {
    header("Location: ../menu/menu.php?error=guest_cannot_view_notifications");
    exit;
}

// 📥 Fetch notifications with original and repost info
$notifications = [];
$stmt = $conn->prepare("
    SELECT 
        n.title, 
        n.message, 
        n.created_at, 
        n.is_pinned, 
        a1.fullname AS created_by_name,
        n.reposted_at, 
        a2.fullname AS reposted_by_name
    FROM notifications n
    JOIN admin a1 ON n.created_by = a1.id
    LEFT JOIN admin a2 ON n.reposted_by = a2.id
    ORDER BY n.is_pinned DESC, n.created_at DESC
");

if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
} else {
    die("❌ Failed to fetch notifications.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🛎️ Notifications - Brizo Fast Food</title>
  <link rel="stylesheet" href="notification.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="notification-container">
  <h1>🛎️ Latest Notifications</h1>

  <?php if (empty($notifications)): ?>
    <p class="no-notice">No announcements at the moment.</p>
  <?php else: ?>
    <?php foreach ($notifications as $note): ?>
      <div class="notice <?= $note['is_pinned'] ? 'pinned' : '' ?>">
        <?php if ($note['is_pinned']): ?>
          <span class="pin-tag">📌 Pinned</span>
        <?php endif; ?>
        
        <h2><?= htmlspecialchars($note['title']) ?></h2>
        <p class="msg"><?= nl2br(htmlspecialchars($note['message'])) ?></p>

        <div class="meta">
          Posted by <strong><?= htmlspecialchars($note['created_by_name']) ?></strong>
          on <?= date('d M Y, H:i', strtotime($note['created_at'])) ?>
          <?php if (!empty($note['reposted_by_name'])): ?>
            <br><small>♻️ Reposted by <?= htmlspecialchars($note['reposted_by_name']) ?> 
            on <?= date('d M Y, H:i', strtotime($note['reposted_at'])) ?></small>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <a href="../menu/menu.php" class="back-btn">⬅️ Back to Menu</a>
</div>

</body>
</html>
