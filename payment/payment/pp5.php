<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'brizo_user');
define('DB_PASS', 'secure_password');
define('DB_NAME', 'brizo');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    $_SESSION['delivery_type'] = 'pickup';
    $_SESSION['delivery_fee'] = 0;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $item_id = intval($_POST['item_id']);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        // Get item details
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();
            
            // Add to cart or update quantity
            if (isset($_SESSION['cart'][$item_id])) {
                $_SESSION['cart'][$item_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$item_id] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $quantity,
                    'image' => $item['image_url']
                ];
            }
            
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity')),
                'subtotal' => calculateSubtotal()
            ]);
            exit;
        }
    }
    elseif (isset($_POST['update_cart_item'])) {
        $item_id = intval($_POST['item_id']);
        $quantity = intval($_POST['quantity']);
        
        if (isset($_SESSION['cart'][$item_id])) {
            if ($quantity > 0) {
                $_SESSION['cart'][$item_id]['quantity'] = $quantity;
            } else {
                unset($_SESSION['cart'][$item_id]);
            }
            
            // Return updated cart info
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity')),
                'subtotal' => calculateSubtotal()
            ]);
            exit;
        }
    }
    elseif (isset($_POST['remove_cart_item'])) {
        $item_id = intval($_POST['item_id']);
        
        if (isset($_SESSION['cart'][$item_id])) {
            unset($_SESSION['cart'][$item_id]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity')),
                'subtotal' => calculateSubtotal()
            ]);
            exit;
        }
    }
    elseif (isset($_POST['update_delivery_option'])) {
        $delivery_type = $_POST['delivery_type'];
        $_SESSION['delivery_type'] = $delivery_type;
        $_SESSION['delivery_fee'] = ($delivery_type === 'delivery') ? 5.00 : 0.00;
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'delivery_fee' => $_SESSION['delivery_fee'],
            'total' => calculateTotal()
        ]);
        exit;
    }
    elseif (isset($_POST['process_payment'])) {
        // Process payment and create order
        $delivery_type = $_SESSION['delivery_type'];
        $delivery_address = isset($_POST['delivery_address']) ? $conn->real_escape_string($_POST['delivery_address']) : '';
        $contact_number = $conn->real_escape_string($_POST['contact_number']);
        $special_instructions = isset($_POST['special_instructions']) ? $conn->real_escape_string($_POST['special_instructions']) : '';
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        
        // Calculate totals
        $subtotal = calculateSubtotal();
        $delivery_fee = $_SESSION['delivery_fee'];
        $total_amount = $subtotal + $delivery_fee;
        
        // Generate unique order ID
        $order_id = 'ORD-' . strtoupper(uniqid());
        
        // Create order (assuming user is logged in, otherwise use NULL for user_id)
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
        
        $stmt = $conn->prepare("INSERT INTO orders (order_id, user_id, order_date, total_amount, status, delivery_type, delivery_address, delivery_fee, contact_number, special_instructions) VALUES (?, ?, NOW(), ?, 'pending', ?, ?, ?, ?, ?)");
        $stmt->bind_param("sidsdsdss", $order_id, $user_id, $total_amount, $delivery_type, $delivery_address, $delivery_fee, $contact_number, $special_instructions);
        $stmt->execute();
        
        // Add order items
        foreach ($_SESSION['cart'] as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siid", $order_id, $item['id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }
        
        // Record payment
        $stmt = $conn->prepare("INSERT INTO payment_history (order_id, date, amount, status, method) VALUES (?, NOW(), ?, 'completed', ?)");
        $stmt->bind_param("sds", $order_id, $total_amount, $payment_method);
        $stmt->execute();
        
        // Clear cart
        $_SESSION['cart'] = [];
        $_SESSION['delivery_type'] = 'pickup';
        $_SESSION['delivery_fee'] = 0;
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'order_id' => $order_id,
            'total_amount' => number_format($total_amount, 2),
            'delivery_type' => $delivery_type,
            'delivery_fee' => number_format($delivery_fee, 2),
            'subtotal' => number_format($subtotal, 2)
        ]);
        exit;
    }
}

// Helper functions
function calculateSubtotal() {
    $subtotal = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
    }
    return $subtotal;
}

function calculateTotal() {
    $subtotal = calculateSubtotal();
    $delivery_fee = isset($_SESSION['delivery_fee']) ? $_SESSION['delivery_fee'] : 0;
    return $subtotal + $delivery_fee;
}

// Fetch menu items
$menu_items = [];
$result = $conn->query("SELECT * FROM menu_items ORDER BY category, name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $menu_items[$row['category']][] = $row;
    }
}

// Fetch other data
$payment_history = [];
$payment_methods = [];
$refund_requests = [];
$orders = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Fetch payment history
    $result = $conn->query("SELECT * FROM payment_history WHERE order_id IN (SELECT order_id FROM orders WHERE user_id = $user_id) ORDER BY date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payment_history[] = $row;
        }
    }
    
    // Fetch payment methods
    $result = $conn->query("SELECT * FROM payment_methods WHERE user_id = $user_id ORDER BY is_default DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payment_methods[] = $row;
        }
    }
    
    // Fetch refund requests
    $result = $conn->query("SELECT * FROM refund_requests WHERE order_id IN (SELECT order_id FROM orders WHERE user_id = $user_id) ORDER BY date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $refund_requests[] = $row;
        }
    }
    
    // Fetch orders
    $result = $conn->query("SELECT * FROM orders WHERE user_id = $user_id ORDER BY order_date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fast Food Ordering System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0066cc;
            --primary-dark: #004c99;
            --secondary: #ff6b00;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px 0;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .cart-icon {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--secondary);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .menu-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
        }
        
        .menu-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .menu-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .menu-item:hover {
            transform: translateY(-5px);
        }
        
        .menu-item-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        
        .menu-item-details {
            padding: 15px;
        }
        
        .menu-item-name {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .menu-item-price {
            color: var(--primary);
            font-weight: bold;
            font-size: 1.2rem;
            margin: 10px 0;
        }
        
        .menu-item-description {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .add-to-cart {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: var(--light-gray);
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        
        .quantity-input {
            width: 40px;
            text-align: center;
            margin: 0 5px;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            padding: 5px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #e05d00;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        /* Cart Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            max-width: 800px;
            width: 90%;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .cart-items {
            margin-bottom: 20px;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 15px;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .cart-item-price {
            color: var(--primary);
            font-weight: bold;
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
        }
        
        .cart-item-quantity {
            width: 50px;
            text-align: center;
            margin: 0 10px;
        }
        
        .summary {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 8px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-total {
            font-weight: bold;
            font-size: 1.2rem;
            border-top: 1px solid var(--gray);
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .delivery-options {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }
        
        .delivery-option {
            flex: 1;
            padding: 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .delivery-option.active {
            border-color: var(--primary);
            background: rgba(0, 119, 204, 0.1);
        }
        
        .delivery-option i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 80px;
        }
        
        /* Payment section */
        .payment-methods {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }
        
        .payment-method {
            flex: 1;
            padding: 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .payment-method.active {
            border-color: var(--primary);
            background: rgba(0, 119, 204, 0.1);
        }
        
        .payment-method i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .menu-items {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .delivery-options,
            .payment-methods {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-hamburger"></i> Fast Food Ordering System</h1>
            <p>Order your favorite meals with just a few clicks</p>
        </header>
        
        <!-- Floating Cart Icon -->
        <div class="cart-icon" onclick="openCartModal()">
            <i class="fas fa-shopping-cart"></i>
            <div class="cart-badge" id="cart-badge">
                <?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?>
            </div>
        </div>
        
        <!-- Menu Sections -->
        <?php foreach ($menu_items as $category => $items): ?>
            <div class="menu-section">
                <h2 class="section-title">
                    <?php 
                    $icons = [
                        'Burgers' => 'fa-hamburger',
                        'Sandwiches' => 'fa-bread-slice',
                        'Sides' => 'fa-french-fries',
                        'Drinks' => 'fa-glass-whiskey',
                        'Desserts' => 'fa-ice-cream'
                    ];
                    $icon = $icons[$category] ?? 'fa-utensils';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i> <?php echo $category; ?>
                </h2>
                
                <div class="menu-items">
                    <?php foreach ($items as $item): ?>
                        <div class="menu-item">
                            <img src="<?php echo $item['image_url']; ?>" alt="<?php echo $item['name']; ?>" class="menu-item-image">
                            <div class="menu-item-details">
                                <h3 class="menu-item-name"><?php echo $item['name']; ?></h3>
                                <p class="menu-item-description"><?php echo $item['description']; ?></p>
                                <div class="menu-item-price">RM<?php echo number_format($item['price'], 2); ?></div>
                                <div class="add-to-cart">
                                    <div class="quantity-selector">
                                        <button class="quantity-btn minus" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">-</button>
                                        <input type="text" class="quantity-input" value="1" id="quantity-<?php echo $item['id']; ?>">
                                        <button class="quantity-btn plus" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">+</button>
                                    </div>
                                    <button class="btn btn-primary" onclick="addToCart(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Cart Modal -->
    <div class="modal" id="cart-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-shopping-cart"></i> Your Order</h3>
                <button class="close-modal" onclick="closeCartModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="cart-items" id="cart-items-container">
                    <?php if (empty($_SESSION['cart'])): ?>
                        <p style="text-align: center; padding: 20px;">Your cart is empty</p>
                    <?php else: ?>
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                            <div class="cart-item" data-item-id="<?php echo $item['id']; ?>">
                                <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="cart-item-image">
                                <div class="cart-item-details">
                                    <h4 class="cart-item-name"><?php echo $item['name']; ?></h4>
                                    <div class="cart-item-price">RM<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                <div class="cart-item-actions">
                                    <button class="btn btn-outline" onclick="updateCartItem(<?php echo $item['id']; ?>, -1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="text" class="cart-item-quantity" value="<?php echo $item['quantity']; ?>" 
                                           onchange="updateCartItemInput(<?php echo $item['id']; ?>, this.value)">
                                    <button class="btn btn-outline" onclick="updateCartItem(<?php echo $item['id']; ?>, 1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="removeCartItem(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($_SESSION['cart'])): ?>
                    <div class="delivery-options">
                        <div class="delivery-option <?php echo $_SESSION['delivery_type'] === 'pickup' ? 'active' : ''; ?>" 
                             onclick="selectDeliveryOption('pickup')">
                            <i class="fas fa-store"></i>
                            <div>Pick Up</div>
                            <small>Collect at our restaurant</small>
                        </div>
                        <div class="delivery-option <?php echo $_SESSION['delivery_type'] === 'delivery' ? 'active' : ''; ?>" 
                             onclick="selectDeliveryOption('delivery')">
                            <i class="fas fa-truck"></i>
                            <div>Delivery</div>
                            <small>RM5.00 delivery fee</small>
                        </div>
                    </div>
                    
                    <div id="delivery-details" style="<?php echo $_SESSION['delivery_type'] === 'delivery' ? '' : 'display: none;'; ?>">
                        <div class="form-group">
                            <label for="delivery-address">Delivery Address</label>
                            <textarea id="delivery-address" placeholder="Enter your delivery address"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact-number">Contact Number</label>
                        <input type="text" id="contact-number" placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="special-instructions">Special Instructions</label>
                        <textarea id="special-instructions" placeholder="Any special requests?"></textarea>
                    </div>
                    
                    <div class="summary">
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span id="summary-subtotal">RM<?php echo number_format(calculateSubtotal(), 2); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Delivery Fee:</span>
                            <span id="summary-delivery">RM<?php echo number_format($_SESSION['delivery_fee'], 2); ?></span>
                        </div>
                        <div class="summary-item summary-total">
                            <span>Total:</span>
                            <span id="summary-total">RM<?php echo number_format(calculateTotal(), 2); ?></span>
                        </div>
                    </div>
                    
                    <h3 style="margin: 20px 0 10px;">Payment Method</h3>
                    <div class="payment-methods">
                        <div class="payment-method active" onclick="selectPaymentMethod('card')">
                            <i class="far fa-credit-card"></i>
                            <div>Credit/Debit Card</div>
                        </div>
                        <div class="payment-method" onclick="selectPaymentMethod('cash')">
                            <i class="fas fa-money-bill-wave"></i>
                            <div>Cash on Delivery</div>
                        </div>
                    </div>
                    
                    <div id="card-details" style="margin-top: 20px;">
                        <div class="form-group">
                            <label for="card-number">Card Number</label>
                            <input type="text" id="card-number" placeholder="1234 5678 9012 3456">
                        </div>
                        <div style="display: flex; gap: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="card-expiry">Expiry Date</label>
                                <input type="text" id="card-expiry" placeholder="MM/YY">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="card-cvv">CVV</label>
                                <input type="text" id="card-cvv" placeholder="123">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="card-name">Name on Card</label>
                            <input type="text" id="card-name" placeholder="John Doe">
                        </div>
                    </div>
                    
                    <button class="btn btn-success" style="width: 100%; padding: 12px; margin-top: 20px;" onclick="processPayment()">
                        <i class="fas fa-lock"></i> Complete Order (RM<?php echo number_format(calculateTotal(), 2); ?>)
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Order Confirmation Modal -->
    <div class="modal" id="confirmation-modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-check-circle"></i> Order Confirmation</h3>
                <button class="close-modal" onclick="closeConfirmationModal()">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <div style="font-size: 5rem; color: var(--success); margin: 20px 0;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 style="margin-bottom: 15px;">Thank you for your order!</h3>
                <p id="confirmation-message"></p>
                <div style="margin: 30px 0;">
                    <div style="font-weight: bold; font-size: 1.2rem;">Order ID: <span id="confirmation-order-id"></span></div>
                    <div>Total Paid: <span id="confirmation-total"></span></div>
                </div>
                <button class="btn btn-primary" onclick="closeConfirmationModal()">
                    <i class="fas fa-home"></i> Back to Menu
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPaymentMethod = 'card';
        
        // Open cart modal
        function openCartModal() {
            document.getElementById('cart-modal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Close cart modal
        function closeCartModal() {
            document.getElementById('cart-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close confirmation modal
        function closeConfirmationModal() {
            document.getElementById('confirmation-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
            window.location.reload();
        }
        
        // Update quantity in menu
        function updateQuantity(itemId, change) {
            const input = document.getElementById(`quantity-${itemId}`);
            let quantity = parseInt(input.value) + change;
            if (quantity < 1) quantity = 1;
            input.value = quantity;
        }
        
        // Add item to cart
        function addToCart(itemId) {
            const quantity = parseInt(document.getElementById(`quantity-${itemId}`).value);
            
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `add_to_cart=true&item_id=${itemId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update cart badge
                    document.getElementById('cart-badge').textContent = data.cart_count;
                    
                    // Show success message
                    alert('Item added to cart!');
                }
            });
        }
        
        // Update cart item quantity
        function updateCartItem(itemId, change) {
            const input = document.querySelector(`.cart-item[data-item-id="${itemId}"] .cart-item-quantity`);
            let quantity = parseInt(input.value) + change;
            
            if (quantity > 0) {
                updateCartItemQuantity(itemId, quantity);
            } else if (quantity === 0) {
                if (confirm('Remove this item from your cart?')) {
                    removeCartItem(itemId);
                }
            }
        }
        
        // Update cart item quantity via input
        function updateCartItemInput(itemId, quantity) {
            quantity = parseInt(quantity);
            if (isNaN(quantity) quantity = 1;
            if (quantity < 1) quantity = 1;
            
            updateCartItemQuantity(itemId, quantity);
        }
        
        // Send AJAX request to update cart item quantity
        function updateCartItemQuantity(itemId, quantity) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `update_cart_item=true&item_id=${itemId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update cart badge
                    document.getElementById('cart-badge').textContent = data.cart_count;
                    
                    // If quantity is 0, remove item from DOM
                    if (quantity === 0) {
                        document.querySelector(`.cart-item[data-item-id="${itemId}"]`).remove();
                    }
                    
                    // Update totals
                    updateOrderSummary(data.subtotal);
                }
            });
        }
        
        // Remove item from cart
        function removeCartItem(itemId) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `remove_cart_item=true&item_id=${itemId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update cart badge
                    document.getElementById('cart-badge').textContent = data.cart_count;
                    
                    // Remove item from DOM
                    document.querySelector(`.cart-item[data-item-id="${itemId}"]`).remove();
                    
                    // Update totals
                    updateOrderSummary(data.subtotal);
                    
                    // If cart is empty, reload the page
                    if (data.cart_count == 0) {
                        window.location.reload();
                    }
                }
            });
        }
        
        // Select delivery option
        function selectDeliveryOption(option) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `update_delivery_option=true&delivery_type=${option}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update UI
                    document.querySelectorAll('.delivery-option').forEach(el => {
                        el.classList.remove('active');
                    });
                    event.currentTarget.classList.add('active');
                    
                    // Show/hide delivery address
                    document.getElementById('delivery-details').style.display = 
                        option === 'delivery' ? 'block' : 'none';
                    
                    // Update totals
                    document.getElementById('summary-delivery').textContent = 'RM' + data.delivery_fee;
                    document.getElementById('summary-total').textContent = 'RM' + data.total;
                }
            });
        }
        
        // Select payment method
        function selectPaymentMethod(method) {
            currentPaymentMethod = method;
            
            // Update UI
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Show/hide card details
            document.getElementById('card-details').style.display = 
                method === 'card' ? 'block' : 'none';
        }
        
        // Update order summary
        function updateOrderSummary(subtotal) {
            document.getElementById('summary-subtotal').textContent = 'RM' + subtotal.toFixed(2);
            
            const deliveryFee = parseFloat(document.getElementById('summary-delivery').textContent.replace('RM', ''));
            const total = subtotal + deliveryFee;
            
            document.getElementById('summary-total').textContent = 'RM' + total.toFixed(2);
            
            // Update checkout button
            const checkoutBtn = document.querySelector('.btn-success');
            if (checkoutBtn) {
                checkoutBtn.innerHTML = `<i class="fas fa-lock"></i> Complete Order (RM${total.toFixed(2)})`;
            }
        }
        
        // Process payment
        function processPayment() {
            const deliveryType = document.querySelector('.delivery-option.active').textContent.trim().toLowerCase();
            const contactNumber = document.getElementById('contact-number').value.trim();
            
            // Validate inputs
            if (!contactNumber) {
                alert('Please enter your contact number');
                return;
            }
            
            if (deliveryType === 'delivery') {
                const deliveryAddress = document.getElementById('delivery-address').value.trim();
                if (!deliveryAddress) {
                    alert('Please enter your delivery address');
                    return;
                }
            }
            
            if (currentPaymentMethod === 'card') {
                const cardNumber = document.getElementById('card-number').value.trim();
                const cardExpiry = document.getElementById('card-expiry').value.trim();
                const cardCvv = document.getElementById('card-cvv').value.trim();
                const cardName = document.getElementById('card-name').value.trim();
                
                if (!cardNumber || !cardExpiry || !cardCvv || !cardName) {
                    alert('Please fill in all card details');
                    return;
                }
            }
            
            // Prepare payment method text
            let paymentMethod = '';
            if (currentPaymentMethod === 'card') {
                const cardNumber = document.getElementById('card-number').value.trim();
                paymentMethod = `Card ending with ${cardNumber.slice(-4)}`;
            } else {
                paymentMethod = 'Cash on Delivery';
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('process_payment', 'true');
            formData.append('payment_method', paymentMethod);
            formData.append('contact_number', contactNumber);
            
            if (deliveryType === 'delivery') {
                formData.append('delivery_address', document.getElementById('delivery-address').value.trim());
            }
            
            const specialInstructions = document.getElementById('special-instructions').value.trim();
            if (specialInstructions) {
                formData.append('special_instructions', specialInstructions);
            }
            
            // Show loading state
            const checkoutBtn = document.querySelector('.btn-success');
            checkoutBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Processing...`;
            checkoutBtn.disabled = true;
            
            // Send request
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Close cart modal
                    closeCartModal();
                    
                    // Show confirmation
                    document.getElementById('confirmation-message').textContent = 
                        `Your order #${data.order_id} has been placed successfully. ` + 
                        (deliveryType === 'delivery' ? 'It will be delivered soon!' : 'Ready for pickup soon!');
                    
                    document.getElementById('confirmation-order-id').textContent = data.order_id;
                    document.getElementById('confirmation-total').textContent = 'RM' + data.total_amount;
                    
                    document.getElementById('confirmation-modal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('There was an error processing your order. Please try again.');
                    checkoutBtn.innerHTML = `<i class="fas fa-lock"></i> Complete Order`;
                    checkoutBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('There was an error processing your order. Please try again.');
                checkoutBtn.innerHTML = `<i class="fas fa-lock"></i> Complete Order`;
                checkoutBtn.disabled = false;
            });
        }
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Format card number input
            document.getElementById('card-number')?.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '')
                    .replace(/(\d{4})(?=\d)/g, '$1 ');
            });
            
            // Format expiry date input
            document.getElementById('card-expiry')?.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '')
                    .replace(/(\d{2})(?=\d)/g, '$1/')
                    .substr(0, 5);
            });
            
            // Format CVV input
            document.getElementById('card-cvv')?.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '').substr(0, 4);
            });
        });
    </script>
</body>
</html>