<?php
require 'config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

try {
    // In production, you should encrypt the card number before storing
    $cardNumber = $data['cardNumber'];
    $lastFour = substr($cardNumber, -4);
    
    $stmt = $pdo->prepare("INSERT INTO user_payment_methods 
                          (user_id, method_type, card_type, card_number, card_last_four, expiry, name_on_card)
                          VALUES (?, 'card', ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        1, // In a real app, this would be the logged-in user's ID
        $data['cardType'],
        $cardNumber,
        $lastFour,
        $data['expiry'],
        $data['name']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Payment method saved successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>