<?php
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

try {
    $stmt = $pdo->prepare("INSERT INTO payments 
        (order_id, amount, payment_method, payment_details, status) 
        VALUES (?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['order_id'],
        $data['amount'],
        $data['payment_method'],
        json_encode($data['payment_details']),
        'completed'
    ]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>