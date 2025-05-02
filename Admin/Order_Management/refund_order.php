<?php
require 'db_conn.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['note'])) {
    $orderId = intval($_POST['id']);
    $note = trim($_POST['note']);

    // 获取订单信息
    $order = $pdo->prepare("SELECT order_id, total, customer_id FROM orders WHERE id = ?");
$order->execute([$orderId]);
$info = $order->fetch();

    if ($order) {
        // 1. 更新订单状态
        $updateOrder = $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?");
        $updateOrder->execute([$orderId]);

        // 2. 插入到 payment_history
        $insertPayment = $pdo->prepare("INSERT INTO payment_history (order_id, date, amount, status, method) 
                                        VALUES (?, NOW(), ?, 'refunded', ?)");
        $insertPayment->execute([$order['order_id'], $order['total'], $note]);

        // 3. 新增记录到 refund_requests
        $insertRefund = $pdo->prepare("INSERT INTO refund_requests 
            (customer_id, order_id, reason, details, status, created_at, admin_notes) 
            VALUES (?, ?, 'other', ?, 'approved', NOW(), ?)");
        $insertRefund->execute([$order['customer_id'], $order['order_id'], $note, 'Approved manually via refund_order.php']);
    }

    header("Location: index.php#approve");
    exit();
}
?>
