<?php
require 'db_connect.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM payments ORDER BY created_at DESC");
    $payments = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'payments' => $payments]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>