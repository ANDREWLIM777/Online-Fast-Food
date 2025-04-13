<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fastfood_payment');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize session
session_start();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'process_payment':
                processPayment($conn);
                break;
            case 'submit_refund':
                submitRefundRequest($conn);
                break;
            case 'save_payment_method':
                savePaymentMethod($conn);
                break;
            case 'remove_payment_method':
                removePaymentMethod($conn);
                break;
        }
    }
}

// Process payment function
function processPayment($conn) {
    $orderId = sanitizeInput($_POST['order_id']);
    $amount = floatval($_POST['amount']);
    $paymentMethod = sanitizeInput($_POST['payment_method']);
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Validate input
    if (empty($orderId) || $amount <= 0 || empty($paymentMethod)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        return;
    }
    
    // Insert payment into database
    $stmt = $conn->prepare("INSERT INTO payments (user_id, order_id, amount, payment_method, status) VALUES (?, ?, ?, ?, 'completed')");
    $stmt->bind_param("isds", $userId, $orderId, $amount, $paymentMethod);
    
    if ($stmt->execute()) {
        $paymentId = $stmt->insert_id;
        
        // Get payment details for receipt
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->bind_param("i", $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        echo json_encode([
            'status' => 'success',
            'payment' => $payment,
            'receipt' => generateReceipt($payment)
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Payment processing failed']);
    }
}

// Submit refund request function
function submitRefundRequest($conn) {
    $orderId = sanitizeInput($_POST['order_id']);
    $reason = sanitizeInput($_POST['reason']);
    $details = sanitizeInput($_POST['details']);
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Validate input
    if (empty($orderId) || empty($reason) || empty($details)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        return;
    }
    
    // Check if order exists and is eligible for refund
    $stmt = $conn->prepare("SELECT id FROM payments WHERE order_id = ? AND user_id = ? AND status = 'completed'");
    $stmt->bind_param("si", $orderId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found or not eligible for refund']);
        return;
    }
    
    // Insert refund request
    $stmt = $conn->prepare("INSERT INTO refund_requests (payment_id, user_id, reason, details, status) 
                           VALUES ((SELECT id FROM payments WHERE order_id = ?), ?, ?, ?, 'pending')");
    $stmt->bind_param("siss", $orderId, $userId, $reason, $details);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Refund request submitted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit refund request']);
    }
}

// Save payment method function
function savePaymentMethod($conn) {
    $cardNumber = sanitizeInput($_POST['card_number']);
    $expiry = sanitizeInput($_POST['expiry']);
    $cvv = sanitizeInput($_POST['cvv']);
    $name = sanitizeInput($_POST['card_name']);
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Validate input
    if (empty($cardNumber) || empty($expiry) || empty($cvv) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        return;
    }
    
    // Determine card type
    $cardType = (strpos($cardNumber, '4') === 0) ? 'VISA' : 'Mastercard';
    $lastFour = substr($cardNumber, -4);
    
    // Insert payment method
    $stmt = $conn->prepare("INSERT INTO payment_methods (user_id, card_type, last_four, expiry, name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $cardType, $lastFour, $expiry, $name);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Payment method saved successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save payment method']);
    }
}

// Remove payment method function
function removePaymentMethod($conn) {
    $methodId = intval($_POST['method_id']);
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Delete payment method
    $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $methodId, $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Payment method removed successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove payment method']);
    }
}

// Get payment history function
function getPaymentHistory($conn, $userId) {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    return $payments;
}

// Get refund requests function
function getRefundRequests($conn, $userId) {
    $stmt = $conn->prepare("SELECT r.*, p.order_id FROM refund_requests r 
                           JOIN payments p ON r.payment_id = p.id 
                           WHERE r.user_id = ? ORDER BY r.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $refunds = [];
    while ($row = $result->fetch_assoc()) {
        $refunds[] = $row;
    }
    
    return $refunds;
}

// Get payment methods function
function getPaymentMethods($conn, $userId) {
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $methods = [];
    while ($row = $result->fetch_assoc()) {
        $methods[] = $row;
    }
    
    return $methods;
}

// Generate receipt HTML
function generateReceipt($payment) {
    $date = date('d-M-Y H:i', strtotime($payment['created_at']));
    
    return "
        <div class='receipt'>
            <h3>Order Receipt</h3>
            <div class='receipt-item'>
                <span>Order ID:</span>
                <span>{$payment['order_id']}</span>
            </div>
            <div class='receipt-item'>
                <span>Date:</span>
                <span>{$date}</span>
            </div>
            <div class='receipt-item'>
                <span>Payment Method:</span>
                <span>{$payment['payment_method']}</span>
            </div>
            <div class='receipt-item'>
                <span>Amount:</span>
                <span>RM" . number_format($payment['amount'], 2) . "</span>
            </div>
            <div class='receipt-item receipt-total'>
                <span>Total Paid:</span>
                <span>RM" . number_format($payment['amount'], 2) . "</span>
            </div>
        </div>
    ";
}

// Sanitize input data
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Close database connection
$conn->close();
?>