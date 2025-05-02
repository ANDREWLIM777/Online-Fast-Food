<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$servername = "localhost";
$username = "brizo_user";
$password = "secure_password";
$dbname = "brizo";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
$sql = "CREATE TABLE IF NOT EXISTS payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    date DATETIME NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    method VARCHAR(100) NOT NULL
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    last_four VARCHAR(4) NOT NULL,
    card_type VARCHAR(20) NOT NULL,
    expiry VARCHAR(5) NOT NULL,
    name VARCHAR(100) NOT NULL
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    details TEXT NOT NULL,
    date DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
)";
$conn->query($sql);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payment'])) {
        // Process payment
        $order_id = $_POST['order_id'];
        $amount = $_POST['amount'];
        $method = $_POST['payment_method'];
        $status = 'completed';
        
        $stmt = $conn->prepare("INSERT INTO payment_history (order_id, date, amount, status, method) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->bind_param("sdss", $order_id, $amount, $status, $method);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
        exit;
    } elseif (isset($_POST['submit_refund'])) {
        // Process refund request
        $order_id = $_POST['refund_order'];
        $reason = $_POST['refund_reason'];
        $details = $_POST['refund_details'];
        
        $stmt = $conn->prepare("INSERT INTO refund_requests (order_id, reason, details, date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $order_id, $reason, $details);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Refund request submitted successfully']);
        exit;
    } elseif (isset($_POST['save_payment_method'])) {
        // Save new payment method
        $card_number = str_replace(' ', '', $_POST['new_card_number']);
        $expiry = $_POST['new_expiry'];
        $cvv = $_POST['new_cvv'];
        $name = $_POST['new_card_name'];
        
        $first_digit = substr($card_number, 0, 1);
        $card_type = $first_digit === '4' ? 'VISA' : 
                     ($first_digit === '5' ? 'Mastercard' : 
                     ($first_digit === '3' ? 'American Express' : 'Card'));
        
        $last_four = substr($card_number, -4);
        
        $stmt = $conn->prepare("INSERT INTO payment_methods (type, last_four, card_type, expiry, name) VALUES ('card', ?, ?, ?, ?)");
        $stmt->bind_param("ssss", $last_four, $card_type, $expiry, $name);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Payment method saved successfully']);
        exit;
    } elseif (isset($_POST['remove_payment_method'])) {
        // Remove payment method
        $id = $_POST['method_id'];
        
        $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Payment method removed successfully']);
        exit;
    }
}

// Function to fetch payment history
function getPaymentHistory($conn) {
    $payment_history = [];
    $result = $conn->query("SELECT * FROM payment_history ORDER BY date DESC");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $payment_history[] = $row;
        }
    }
    return $payment_history;
}

// Function to fetch payment methods
function getPaymentMethods($conn) {
    $payment_methods = [];
    $result = $conn->query("SELECT * FROM payment_methods");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $payment_methods[] = $row;
        }
    }
    return $payment_methods;
}

// Function to fetch refund requests
function getRefundRequests($conn) {
    $refund_requests = [];
    $result = $conn->query("SELECT * FROM refund_requests ORDER BY date DESC");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $refund_requests[] = $row;
        }
    }
    return $refund_requests;
}
?>