<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$itemId = $_GET['id'] ?? null;

if (!$itemId || !is_numeric($itemId)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid item ID']));
}

try {
    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$itemId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
    }
} catch (PDOException $e) {
    error_log("Delete Error: ".$e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}