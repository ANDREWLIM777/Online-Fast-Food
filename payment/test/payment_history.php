<?php
require_once 'db_connect.php';

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $conn->prepare("SELECT * FROM payment_history ORDER BY date DESC");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        $stmt = $conn->prepare("INSERT INTO payment_history 
                              (order_id, amount, date, method, status) 
                              VALUES (:order_id, :amount, :date, :method, :status)");
        
        $stmt->execute([
            ':order_id' => $data['orderId'],
            ':amount' => $data['amount'],
            ':date' => $data['date'],
            ':method' => $data['method'],
            ':status' => $data['status'] ?? 'completed'
        ]);
        
        echo json_encode([
            "status" => "success", 
            "id" => $conn->lastInsertId()
        ]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>