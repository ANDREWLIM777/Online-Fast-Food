<?php
require 'db_conn.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $orderId = intval($_POST['id']);
    
    $stmt = $pdo->prepare("UPDATE orders SET status = 'preparing' WHERE id = ?");
    $stmt->execute([$orderId]);

    header("Location: index.php");
    exit();
}
?>
