<?php
require 'db_conn.php';
include '../../auth_notifications.php';
check_permission('superadmin');

if (session_status() === PHP_SESSION_NONE) session_start();

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
$stmt->execute([$id]);
$notification = $stmt->fetch();

if (!$notification) {
    echo "Notification not found.";
    exit();
}

// 执行删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delete = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $delete->execute([$id]);
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Notification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f0f0f;
            color: #eee;
            padding: 2rem;
        }

        .container {
            background: #1c1c1c;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 0 12px rgba(255, 0, 0, 0.2);
        }

        h2 {
            color: #e57373;
            margin-top: 0;
            text-align: center;
        }

        p {
            text-align: center;
            color: #ccc;
        }

        .btn-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 2rem;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            border: none;
        }

        .btn-cancel {
            background: #444;
            color: #eee;
        }

        .btn-cancel:hover {
            background: #666;
        }

        .btn-delete {
            background: #ff4d4d;
            color: white;
        }

        .btn-delete:hover {
            background: #ff1a1a;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 1.5rem;
            background: none;
            color: #e8d48b;
            border: 1px solid #c0a23d;
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #c0a23d;
            color: #000;
        }
    </style>
</head>
<body>

<a class="back-btn" href="index.php"><i class="fas fa-arrow-left"></i> Back to Announcement</a>

<div class="container">
    <h2>⚠️ Confirm Deletion</h2>
    <p>Are you sure you want to delete this notification?</p>
    <p><strong><?= htmlspecialchars($notification['title']) ?></strong></p>

    <form method="POST">
        <div class="btn-group">
            <a href="index.php" class="btn btn-cancel">Cancel</a>
            <button type="submit" class="btn btn-delete">Delete</button>
        </div>
    </form>
</div>

</body>
</html>
