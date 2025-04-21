<?php
require_once 'db_connect.php';

header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $conn->prepare("SELECT * FROM payment_methods ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (isset($data['id'])) {
            $stmt = $conn->prepare("UPDATE payment_methods SET 
                                  type = :type, last_four = :last_four, 
                                  card_type = :card_type, expiry = :expiry, 
                                  name = :name WHERE id = :id");
        } else {
            $stmt = $conn->prepare("INSERT INTO payment_methods 
                                  (type, last_four, card_type, expiry, name) 
                                  VALUES (:type, :last_four, :card_type, :expiry, :name)");
        }
        
        $stmt->execute([
            ':type' => $data['type'],
            ':last_four' => $data['lastFour'],
            ':card_type' => $data['cardType'],
            ':expiry' => $data['expiry'],
            ':name' => $data['name'],
            ':id' => $data['id'] ?? null
        ]);
        
        echo json_encode([
            "status" => "success", 
            "id" => isset($data['id']) ? $data['id'] : $conn->lastInsertId()
        ]);
        break;
        
    case 'DELETE':
        $id = $_GET['id'];
        $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(["status" => "success"]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>