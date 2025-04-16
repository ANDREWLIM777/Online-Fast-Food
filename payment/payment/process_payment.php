<?php
require 'config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Insert payment into database
    $stmt = $pdo->prepare("INSERT INTO payments (order_id, payment_date, amount, payment_method, card_last_four, status) 
                          VALUES (?, NOW(), ?, ?, ?, ?)");
    $stmt->execute([
        $data['orderId'],
        $data['amount'],
        $data['paymentMethod'],
        substr($data['cardNumber'], -4),
        'completed'
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>