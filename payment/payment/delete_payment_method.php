<?php
require 'config.php';

header('Content-Type: application/json');

$methodId = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("DELETE FROM user_payment_methods WHERE method_id = ? AND user_id = 1");
    $stmt->execute([$methodId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment method removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment method not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>