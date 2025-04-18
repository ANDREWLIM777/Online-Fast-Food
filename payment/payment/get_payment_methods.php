<?php
require 'config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM user_payment_methods WHERE user_id = 1 ORDER BY created_at DESC");
    $methods = $stmt->fetchAll();
    
    echo json_encode($methods);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>