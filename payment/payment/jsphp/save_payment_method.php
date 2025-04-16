<?php
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

try {
    $stmt = $pdo->prepare("INSERT INTO payment_methods 
        (user_id, method_type, card_type, last_four, expiry, card_name) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        1, // Static user_id for demo - replace with actual user ID
        'card',
        $data['card_type'],
        $data['last_four'],
        $data['expiry'],
        $data['card_name']
    ]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>