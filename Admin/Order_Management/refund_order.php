<?php
require 'db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['note'])) {
    $orderId = intval($_POST['id']);
    $note = trim($_POST['note']);

    // 更新订单状态
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?");
    $stmt->execute([$orderId]);

    // 插入记录到 payment_history
    $order = $pdo->prepare("SELECT order_code, total FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $info = $order->fetch();

    if ($info) {
        $insert = $pdo->prepare("INSERT INTO payment_history (order_id, date, amount, status, method) 
                                 VALUES (?, NOW(), ?, 'refunded', ?)");
        $insert->execute([$info['order_code'], $info['total'], $note]);
    }

    header("Location: index.php");
    exit();
}
?>
