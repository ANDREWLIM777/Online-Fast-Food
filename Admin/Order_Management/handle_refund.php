<?php
require 'db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    $order_id = $pdo->prepare("SELECT order_id FROM refund_requests WHERE id = ?");
    $order_id->execute([$id]);
    $orderCode = $order_id->fetchColumn();

    if ($action === 'approve') {
        $pdo->prepare("UPDATE refund_requests SET status = 'refunded' WHERE id = ?")->execute([$id]);
        $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE order_code = ?")->execute([$orderCode]);

        // 记录 refund 到 history
        $amount = $pdo->prepare("SELECT total FROM orders WHERE order_code = ?");
        $amount->execute([$orderCode]);
        $total = $amount->fetchColumn();

        $pdo->prepare("INSERT INTO payment_history (order_id, date, amount, status, method)
                       VALUES (?, NOW(), ?, 'refunded', 'User Request')")
            ->execute([$orderCode, $total]);

    } elseif ($action === 'reject') {
        $reason = ($_POST['reason'] === 'custom') ? $_POST['custom_reason'] : $_POST['reason'];
        $pdo->prepare("UPDATE refund_requests SET status = ? WHERE id = ?")->execute([$reason, $id]);

        // 将订单设为 completed，让它回到 Order History
        $pdo->prepare("UPDATE orders SET status = 'completed' WHERE order_code = ?")->execute([$orderCode]);
    }

    header("Location: index.php");
    exit();
}
