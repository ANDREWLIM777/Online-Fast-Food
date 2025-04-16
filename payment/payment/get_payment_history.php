<?php
require 'config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM payments ORDER BY payment_date DESC");
    $payments = $stmt->fetchAll();
    
    echo json_encode($payments);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>