<?php
require 'db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    // 取得 refund_requests 对应的 order_id
    $stmt = $pdo->prepare("SELECT order_id FROM refund_requests WHERE id = ?");
    $stmt->execute([$id]);
    $orderId = $stmt->fetchColumn();

    if (!$orderId) {
        die('Invalid refund request.');
    }

    if ($action === 'approve') {
        // 1. 更新 refund_requests 状态为 approved
        $pdo->prepare("UPDATE refund_requests SET status = 'approved', updated_at = NOW() WHERE id = ?")
            ->execute([$id]);

        // 2. 更新订单状态为 refunded
        $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE order_id = ?")
            ->execute([$orderId]);

        // 3. 写入 payment_history
        $stmt = $pdo->prepare("SELECT total FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $amount = $stmt->fetchColumn();

        if ($amount) {
            $pdo->prepare("INSERT INTO payment_history (order_id, date, amount, status, method)
                           VALUES (?, NOW(), ?, 'refunded', 'User Request')")
                ->execute([$orderId, $amount]);
        }

    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? 'No reason';
        // 1. 更新 refund_requests 状态为 rejected 并写入 admin_notes
        $pdo->prepare("UPDATE refund_requests SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$reason, $id]);

        // 2. 将订单恢复为 completed 状态
        $pdo->prepare("UPDATE orders SET status = 'completed' WHERE order_id = ?")
            ->execute([$orderId]);
    }

    header("Location: index.php#refund");
    exit();
}
?>
