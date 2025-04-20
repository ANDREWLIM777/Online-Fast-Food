<?php
require_once 'db_connect.php';

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $conn->prepare("SELECT * FROM payments ORDER BY payment_date DESC");
        $stmt->execute();
        echo json_encode([
            "success" => true,
            "payments" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        $stmt = $conn->prepare("INSERT INTO payments 
                              (order_id, amount, payment_method, payment_date, card_last_four, status) 
                              VALUES (:order_id, :amount, :method, :date, :last_four, 'completed')");
        
        $stmt->execute([
            ':order_id' => $data['orderId'],
            ':amount' => $data['amount'],
            ':method' => $data['paymentMethod'],
            ':date' => date('Y-m-d H:i:s'),
            ':last_four' => $data['cardLastFour'] ?? null
        ]);
        
        echo json_encode([
            "success" => true,
            "message" => "Payment saved successfully",
            "payment_id" => $conn->lastInsertId()
        ]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
?>