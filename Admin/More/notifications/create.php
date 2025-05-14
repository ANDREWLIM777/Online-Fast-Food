<?php
require 'db_conn.php';
include '../../auth_notifications.php';
check_permission('superadmin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if ($title && $message) {
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$title, $message, $_SESSION['user_id']]);
        header("Location: index.php");
        exit();
    } else {
        $error = "Title and message cannot be empty.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Announcement</title>
    <style>
        body { background: #0f0f0f; color: #eee; font-family: sans-serif; padding: 2rem; }
        .form-container { background: #1c1c1c; padding: 2rem; border-radius: 10px; max-width: 600px; margin: auto; }
        input, textarea { width: 100%; padding: 10px; margin-bottom: 1rem; border-radius: 6px; border: 1px solid #333; background: #252525; color: white; }
        button { padding: 10px 20px; background: #c0a23d; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        button:hover { background: #e8d48b; }
        .error { color: red; margin-bottom: 1rem; }

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

    <div class="form-container">
        <h2>📝 New Announcement</h2>
        <?php if (isset($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="text" name="title" placeholder="Title" required>
            <textarea name="message" rows="6" placeholder="Message" required></textarea>
            <button type="submit">Post Announcement</button>
        </form>
    </div>
</body>
</html>
