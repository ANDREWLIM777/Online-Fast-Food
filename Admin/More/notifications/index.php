<?php
require 'db_conn.php';
include '../../auth_notifications.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 查询通知 + 创建者信息
$stmt = $pdo->query("
    SELECT n.*, 
           a.name AS creator_name, 
           r.name AS reposter_name 
    FROM notifications n
    JOIN admin a ON n.created_by = a.id
    LEFT JOIN admin r ON n.reposted_by = r.id
    ORDER BY is_pinned DESC, created_at DESC
");
$notifications = $stmt->fetchAll();

$isSuperAdmin = ($_SESSION['user_role'] ?? '') === 'superadmin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f0f0f;
            color: #eee;
            padding: 2rem;
        }

        /* 背景发光环 */
body::after {
  content: '';
  position: fixed;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at 50% 50%, rgba(244, 227, 178, 0.07) 0%, transparent 70%);
  animation: auraPulse 8s infinite;
  pointer-events: none;
  z-index: -1; /* ⬅ 放底层 */
}

/* 星尘粒子 */
body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-image: 
    radial-gradient(circle at 20% 30%, rgba(244, 228, 178, 0.15) 1px, transparent 2px),
    radial-gradient(circle at 80% 70%, rgba(244, 228, 178, 0.15) 1px, transparent 2px);
  background-size: 60px 60px;
  animation: stardust 20s linear infinite;
  pointer-events: none;
  z-index: -2; /* ⬅ 更底层 */
}

@keyframes auraPulse {
  0% { transform: scale(0.8); opacity: 0.3; }
  50% { transform: scale(1.2); opacity: 0.08; }
  100% { transform: scale(0.8); opacity: 0.3; }
}

@keyframes stardust {
  0% { background-position: 0 0, 100px 100px; }
  100% { background-position: 100px 100px, 0 0; }
}

        h1 {
            color: #c0a23d;
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .back-btn {
            display: inline-block;
            background: none;
            color: #e8d48b;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            border: 1px solid #c0a23d;
            padding: 8px 16px;
            border-radius: 10px;
            margin-bottom: 0rem;
        }

        .back-btn:hover {
            background: #c0a23d;
            color: #000;
        }

        .notification {
            background: #1c1c1c;
            border-left: 5px solid #c0a23d;
            padding: 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .notification.pinned {
            border-left-color: gold;
            background: #2b250a;
        }

        .notification h2 {
            margin-top: 0;
            color: #f0e68c;
        }

        .meta {
            font-size: 0.9rem;
            color: #aaa;
            margin-top: 8px;
        }

        .admin-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .admin-actions a {
            color: #ccc;
            margin-left: 10px;
            text-decoration: none;
        }

        .admin-actions a:hover {
            color: #fff;
        }

        .new-btn {
            display: inline-block;
            background: linear-gradient(to right, #c0a23d, #e8d48b);
            padding: 0.5rem 1rem;
            color: #000;
            text-decoration: none;
            font-weight: bold;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .new-btn:hover {
            background: #e8d48b;
        }
    </style>
</head>
<body>

    <a class="back-btn" href="../more.php"><i class="fas fa-arrow-left"></i> Back to More Page</a>

    <h1>📢 Notifications</h1>

    <?php if ($isSuperAdmin): ?>
        <a class="new-btn" href="create.php"><i class="fas fa-plus"></i> New Notification</a>
    <?php endif; ?>

    <?php foreach ($notifications as $n): ?>
        <div class="notification <?= $n['is_pinned'] ? 'pinned' : '' ?>">
            <h2><?= htmlspecialchars($n['title']) ?></h2>
            <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>

            <div class="meta">
                Posted by <?= htmlspecialchars($n['creator_name']) ?> on <?= date('M d, Y H:i', strtotime($n['created_at'])) ?>
                <?php if (!empty($n['reposted_by'])): ?>
                    <br><strong style="color: gold;">
                        Repost by <?= htmlspecialchars($n['reposter_name']) ?> on <?= date('M d, Y H:i', strtotime($n['reposted_at'])) ?>
                    </strong>
                <?php endif; ?>
            </div>

            <?php if ($isSuperAdmin): ?>
                <div class="admin-actions">
                    <a href="pin.php?id=<?= $n['id'] ?>&act=<?= $n['is_pinned'] ? 'unpin' : 'pin' ?>">
                        <i class="fas fa-thumbtack"></i>
                    </a>
                    <a href="edit.php?id=<?= $n['id'] ?>"><i class="fas fa-edit"></i></a>
                    <a href="delete.php?id=<?= $n['id'] ?>"><i class="fas fa-trash"></i></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</body>
</html>
