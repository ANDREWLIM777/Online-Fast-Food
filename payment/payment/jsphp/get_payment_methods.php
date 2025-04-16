<?php
require 'db_connect.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM payment_methods WHERE user_id = 1 ORDER BY created_at DESC");
    $methods = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'methods' => $methods]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>