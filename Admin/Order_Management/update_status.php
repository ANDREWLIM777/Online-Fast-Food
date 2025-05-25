<?php
require 'db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $allowed = ['pending', 'preparing', 'delivering', 'delivered'];

    if (!in_array($status, $allowed)) die("Invalid status");

    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
?>
