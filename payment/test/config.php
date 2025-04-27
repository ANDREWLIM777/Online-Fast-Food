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

// Create tables in correct order with proper foreign keys
$tables = [
    // 1. Independent tables first
    "CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        category VARCHAR(50) NOT NULL,
        image_url VARCHAR(255),
        is_active BOOLEAN DEFAULT true
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    // 2. Tables that depend on the above
    "CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        session_id VARCHAR(100) NOT NULL,
        item_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(20) NOT NULL UNIQUE,
        customer_id INT,
        session_id VARCHAR(100) NOT NULL,
        order_date DATETIME NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
        delivery_type ENUM('pickup', 'delivery') NOT NULL,
        delivery_address TEXT,
        delivery_fee DECIMAL(10,2) DEFAULT 0,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    
    // 3. Tables that depend on orders
    "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(20) NOT NULL,
        item_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        special_instructions TEXT,
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS payment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(20) NOT NULL,
        date DATETIME NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) NOT NULL,
        method VARCHAR(100) NOT NULL,
        transaction_id VARCHAR(100),
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        session_id VARCHAR(100) NOT NULL,
        type VARCHAR(20) NOT NULL,
        last_four VARCHAR(4) NOT NULL,
        card_type VARCHAR(20) NOT NULL,
        expiry VARCHAR(5) NOT NULL,
        name VARCHAR(100) NOT NULL,
        is_default BOOLEAN DEFAULT false,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS refund_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(20) NOT NULL,
        reason VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        date DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Generate a unique order ID
function generateOrderId() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Function to get cart items
function getCartItems($conn, $session_id) {
    $cart_items = [];
    $sql = "SELECT c.*, m.name, m.price, m.image_url 
            FROM cart c 
            JOIN menu_items m ON c.item_id = m.id 
            WHERE c.session_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $session_id);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    $stmt->close();
    
    return $cart_items;
}

// Function to calculate cart total
function calculateCartTotal($conn, $session_id) {
    $total = 0;
    $sql = "SELECT SUM(c.quantity * m.price) as total 
            FROM cart c 
            JOIN menu_items m ON c.item_id = m.id 
            WHERE c.session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total = $row['total'] ?? 0;
    }
    $stmt->close();
    
    return $total;
}

// Function to fetch payment history
function getPaymentHistory($conn, $session_id) {
    $payment_history = [];
    $sql = "SELECT ph.* FROM payment_history ph
            JOIN orders o ON ph.order_id = o.order_id
            WHERE o.session_id = ? 
            ORDER BY ph.date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $payment_history[] = $row;
    }
    $stmt->close();
    
    return $payment_history;
}

// Function to fetch payment methods
function getPaymentMethods($conn, $session_id) {
    $payment_methods = [];
    $sql = "SELECT * FROM payment_methods WHERE session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $payment_methods[] = $row;
    }
    $stmt->close();
    
    return $payment_methods;
}

// Function to fetch refund requests
function getRefundRequests($conn, $session_id) {
    $refund_requests = [];
    $sql = "SELECT rr.* FROM refund_requests rr
            JOIN orders o ON rr.order_id = o.order_id
            WHERE o.session_id = ? 
            ORDER BY rr.date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $refund_requests[] = $row;
    }
    $stmt->close();
    
    return $refund_requests;
}

// Function to fetch customer orders
function getCustomerOrders($conn, $session_id) {
    $orders = [];
    $sql = "SELECT * FROM orders WHERE session_id = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    
    return $orders;
}

// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['session_id'])) {
    $_SESSION['session_id'] = uniqid('sess_', true);
}
?>