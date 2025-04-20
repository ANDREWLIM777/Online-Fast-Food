<?php
require_once 'db_connect.php';

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $conn->prepare("SELECT * FROM refund_requests ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        $stmt = $conn->prepare("INSERT INTO refund_requests 
                              (order_id, reason, details, status) 
                              VALUES (:order_id, :reason, :details, 'pending')");
        
        $stmt->execute([
            ':order_id' => $data['orderId'],
            ':reason' => $data['reason'],
            ':details' => $data['details']
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