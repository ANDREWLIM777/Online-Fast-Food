<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'burger_palace');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_to_cart':
            addToCart($conn);
            break;
        case 'update_cart':
            updateCart();
            break;
        case 'remove_from_cart':
            removeFromCart();
            break;
        case 'place_order':
            placeOrder($conn);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
    exit;
}

// Add item to cart
function addToCart($conn) {
    $productId = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']) ?: 1;
    
    // Get product details from database
    $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        return;
    }
    
    $product = $result->fetch_assoc();
    
    // Add to cart
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'],
            'quantity' => $quantity
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity')),
        'message' => $product['name'] . ' added to cart'
    ]);
}

// Update cart item quantity
function updateCart() {
    $productId = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    if (isset($_SESSION['cart'][$productId])) {
        if ($quantity > 0) {
            $_SESSION['cart'][$productId]['quantity'] = $quantity;
        } else {
            unset($_SESSION['cart'][$productId]);
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity')),
        'subtotal' => calculateSubtotal(),
        'total' => calculateTotal()
    ]);
}

// Remove item from cart
function removeFromCart() {
    $productId = intval($_POST['product_id']);
    
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
    
    echo json_encode([
        'status' => 'success',
        'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity')),
        'subtotal' => calculateSubtotal(),
        'total' => calculateTotal()
    ]);
}

// Place order
function placeOrder($conn) {
    // Validate input
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $instructions = trim($_POST['instructions']);
    $paymentMethod = trim($_POST['payment_method']);
    
    if (empty($name) || empty($phone) || empty($address) || empty($paymentMethod)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields']);
        return;
    }
    
    if (empty($_SESSION['cart'])) {
        echo json_encode(['status' => 'error', 'message' => 'Your cart is empty']);
        return;
    }
    
    // Calculate totals
    $subtotal = calculateSubtotal();
    $deliveryFee = 2.99;
    $total = $subtotal + $deliveryFee;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert customer information
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, phone, address, instructions, payment_method, subtotal, delivery_fee, total, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssssddd", $name, $phone, $address, $instructions, $paymentMethod, $subtotal, $deliveryFee, $total);
        $stmt->execute();
        $orderId = $conn->insert_id;
        
        // Insert order items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity) 
                               VALUES (?, ?, ?, ?, ?)");
        
        foreach ($_SESSION['cart'] as $item) {
            $stmt->bind_param("iisdi", $orderId, $item['id'], $item['name'], $item['price'], $item['quantity']);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        echo json_encode([
            'status' => 'success',
            'order_id' => $orderId,
            'customer_name' => $name
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Order failed: ' . $e->getMessage()]);
    }
}

// Calculate cart subtotal
function calculateSubtotal() {
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    return $subtotal;
}

// Calculate cart total
function calculateTotal() {
    return calculateSubtotal() + 2.99; // $2.99 delivery fee
}

// Get menu categories
function getCategories($conn) {
    $result = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

// Get menu items
function getMenuItems($conn, $categoryId = null) {
    $sql = "SELECT p.* FROM products p";
    $params = [];
    
    if ($categoryId) {
        $sql .= " WHERE p.category_id = ?";
        $params[] = $categoryId;
    }
    
    $sql .= " ORDER BY p.name";
    
    $stmt = $conn->prepare($sql);
    
    if ($categoryId) {
        $stmt->bind_param("i", $categoryId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return $items;
}

// Get promotions
function getPromotions($conn) {
    $currentDate = date('Y-m-d');
    $result = $conn->query("SELECT * FROM promotions WHERE start_date <= '$currentDate' AND end_date >= '$currentDate' ORDER BY end_date");
    $promotions = [];
    while ($row = $result->fetch_assoc()) {
        $promotions[] = $row;
    }
    return $promotions;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Burger Palace - Fast Food Restaurant</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .hero-slide {
            animation: slide 15s infinite;
        }
        @keyframes slide {
            0%, 30% { background-image: url('https://images.unsplash.com/photo-1586190848861-99aa4a171e90?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'); }
            33%, 63% { background-image: url('https://images.unsplash.com/photo-1559847844-5315695dadae?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'); }
            66%, 97% { background-image: url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'); }
        }
        .toast {
            animation: fadeInOut 3s ease-in-out;
        }
        @keyframes fadeInOut {
            0%, 100% { opacity: 0; }
            10%, 90% { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div>
                        <a href="#" class="flex items-center py-4 px-2">
                            <span class="font-semibold text-gray-900 text-2xl">Burger Palace</span>
                        </a>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-3">
                    <a href="#" class="py-4 px-2 text-green-500 font-semibold border-b-4 border-green-500">Home</a>
                    <a href="#menu" class="py-4 px-2 text-gray-500 font-semibold hover:text-green-500 transition duration-300">Menu</a>
                    <a href="#promotions" class="py-4 px-2 text-gray-500 font-semibold hover:text-green-500 transition duration-300">Promotions</a>
                    <a href="#contact" class="py-4 px-2 text-gray-500 font-semibold hover:text-green-500 transition duration-300">Contact Us</a>
                    <a href="#" class="py-4 px-2 text-gray-500 font-semibold hover:text-green-500 transition duration-300">Login</a>
                    <a href="#cart" class="py-2 px-4 bg-green-500 text-white font-semibold rounded-full hover:bg-green-600 transition duration-300 flex items-center">
                        <i class="fas fa-shopping-cart mr-2"></i> Cart <span id="cart-count" class="ml-2 bg-white text-green-500 rounded-full w-6 h-6 flex items-center justify-center"><?= array_sum(array_column($_SESSION['cart'], 'quantity')) ?></span>
                    </a>
                </div>
                <div class="md:hidden flex items-center">
                    <button class="outline-none mobile-menu-button">
                        <i class="fas fa-bars text-gray-500 text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="hidden mobile-menu">
            <ul>
                <li><a href="#" class="block text-sm px-2 py-4 bg-green-500 text-white font-semibold">Home</a></li>
                <li><a href="#menu" class="block text-sm px-2 py-4 hover:bg-green-500 hover:text-white transition duration-300">Menu</a></li>
                <li><a href="#promotions" class="block text-sm px-2 py-4 hover:bg-green-500 hover:text-white transition duration-300">Promotions</a></li>
                <li><a href="#contact" class="block text-sm px-2 py-4 hover:bg-green-500 hover:text-white transition duration-300">Contact Us</a></li>
                <li><a href="#" class="block text-sm px-2 py-4 hover:bg-green-500 hover:text-white transition duration-300">Login</a></li>
                <li><a href="#cart" class="block text-sm px-2 py-4 hover:bg-green-500 hover:text-white transition duration-300">Cart</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section with Promotion Slider -->
    <section class="hero-slide bg-cover bg-center h-96 flex items-center relative">
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>
        <div class="max-w-6xl mx-auto px-4 relative z-10 text-white">
            <h1 class="text-4xl md:text-6xl font-bold mb-4">Delicious Fast Food</h1>
            <p class="text-xl md:text-2xl mb-8">Try our special combo meals today!</p>
            <div class="flex space-x-4">
                <a href="#menu" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-full transition duration-300">Order Now</a>
                <a href="#promotions" class="bg-white hover:bg-gray-100 text-green-500 font-bold py-3 px-6 rounded-full transition duration-300">Promotions</a>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section id="menu" class="py-16 bg-white">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Our Menu</h2>
            
            <!-- Categories -->
            <div class="flex overflow-x-auto pb-4 mb-8 scrollbar-hide">
                <button class="category-btn active bg-green-500 text-white px-6 py-2 rounded-full mr-3 whitespace-nowrap">All-Time Favorites</button>
                <?php foreach (getCategories($conn) as $category): ?>
                    <button class="category-btn bg-gray-200 hover:bg-gray-300 px-6 py-2 rounded-full mr-3 whitespace-nowrap"><?= htmlspecialchars($category['name']) ?></button>
                <?php endforeach; ?>
            </div>
            
            <!-- Search and Filter -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="relative mb-4 md:mb-0 md:w-1/2">
                    <input type="text" placeholder="Search menu items..." class="w-full pl-10 pr-4 py-2 border rounded-full focus:outline-none focus:ring-2 focus:ring-green-500">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
                <div class="flex space-x-2">
                    <select class="border rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option>Sort by</option>
                        <option>Price: Low to High</option>
                        <option>Price: High to Low</option>
                        <option>Most Popular</option>
                    </select>
                    <select class="border rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option>Filter</option>
                        <option>Vegetarian</option>
                        <option>Spicy</option>
                        <option>New Items</option>
                    </select>
                </div>
            </div>
            
            <!-- Menu Items Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach (getMenuItems($conn) as $item): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="relative h-48 overflow-hidden">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                            <?php if ($item['is_popular']): ?>
                                <div class="absolute top-2 left-2 bg-yellow-400 text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                    <i class="fas fa-star mr-1"></i> Popular
                                </div>
                            <?php endif; ?>
                            <?php if ($item['is_new']): ?>
                                <div class="absolute top-2 left-2 bg-blue-400 text-white text-xs font-bold px-2 py-1 rounded-full">
                                    New
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-lg mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                            <p class="text-gray-600 text-sm mb-2"><?= htmlspecialchars($item['description']) ?></p>
                            <div class="flex justify-between items-center mt-4">
                                <span class="font-bold text-green-500">$<?= number_format($item['price'], 2) ?></span>
                                <button class="add-to-cart bg-green-500 text-white p-2 rounded-full hover:bg-green-600 transition" 
                                        data-id="<?= $item['id'] ?>" 
                                        data-name="<?= htmlspecialchars($item['name']) ?>" 
                                        data-price="<?= $item['price'] ?>" 
                                        data-image="<?= htmlspecialchars($item['image']) ?>">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Promotions Section -->
    <section id="promotions" class="py-16 bg-gray-100">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Current Promotions</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach (getPromotions($conn) as $promo): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="relative h-48 overflow-hidden">
                            <img src="<?= htmlspecialchars($promo['image']) ?>" alt="<?= htmlspecialchars($promo['title']) ?>" class="w-full h-full object-cover">
                            <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-70 text-white p-4">
                                <h3 class="font-bold text-xl"><?= htmlspecialchars($promo['title']) ?></h3>
                                <p class="text-sm">Valid until <?= date('M j, Y', strtotime($promo['end_date'])) ?></p>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-2xl font-bold text-green-500"><?= htmlspecialchars($promo['discount_text']) ?></span>
                                <?php if ($promo['original_price']): ?>
                                    <span class="line-through text-gray-500">$<?= number_format($promo['original_price'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-600 mb-4"><?= htmlspecialchars($promo['description']) ?></p>
                            <button class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded transition duration-300">
                                <?= $promo['is_combo'] ? 'Add to Cart' : 'View Menu' ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Cart Section -->
    <section id="cart" class="py-16 bg-white hidden">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Your Cart</h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <div id="cart-items">
                            <?php if (empty($_SESSION['cart'])): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">Your cart is empty</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <div class="flex items-center justify-between py-4 border-b border-gray-200">
                                        <div class="flex items-center">
                                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-16 h-16 object-cover rounded">
                                            <div class="ml-4">
                                                <h4 class="font-semibold"><?= htmlspecialchars($item['name']) ?></h4>
                                                <p class="text-green-500">$<?= number_format($item['price'], 2) ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <button class="decrease-quantity px-2 py-1 bg-gray-200 rounded" data-id="<?= $item['id'] ?>">
                                                <i class="fas fa-minus text-xs"></i>
                                            </button>
                                            <span class="mx-2"><?= $item['quantity'] ?></span>
                                            <button class="increase-quantity px-2 py-1 bg-gray-200 rounded" data-id="<?= $item['id'] ?>">
                                                <i class="fas fa-plus text-xs"></i>
                                            </button>
                                            <button class="remove-item ml-4 text-red-500" data-id="<?= $item['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="bg-gray-50 rounded-lg p-6 sticky top-4">
                        <h3 class="text-xl font-bold mb-4">Order Summary</h3>
                        <div class="space-y-2 mb-6">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span id="subtotal">$<?= number_format(calculateSubtotal(), 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Delivery Fee</span>
                                <span>$2.99</span>
                            </div>
                            <div class="border-t border-gray-200 my-2"></div>
                            <div class="flex justify-between font-bold">
                                <span>Total</span>
                                <span id="total">$<?= number_format(calculateTotal(), 2) ?></span>
                            </div>
                        </div>
                        <button id="checkout-btn" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded transition duration-300 <?= empty($_SESSION['cart']) ? 'disabled:opacity-50' : '' ?>" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Checkout Section -->
    <section id="checkout" class="py-16 bg-gray-100 hidden">
        <div class="max-w-4xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Checkout</h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold mb-4">Delivery Information</h3>
                    <form id="checkout-form">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="name">Full Name</label>
                            <input type="text" id="name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="phone">Phone Number</label>
                            <input type="tel" id="phone" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="address">Delivery Address</label>
                            <textarea id="address" rows="3" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="instructions">Special Instructions</label>
                            <textarea id="instructions" rows="2" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                        </div>
                        
                        <h3 class="text-xl font-bold mb-4 mt-8">Payment Method</h3>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="radio" id="cash" name="payment" value="cash" class="mr-2" checked>
                                <label for="cash">Cash on Delivery</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio" id="card" name="payment" value="card" class="mr-2">
                                <label for="card">Credit/Debit Card</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio" id="mobile" name="payment" value="mobile" class="mr-2">
                                <label for="mobile">Mobile Payment</label>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div>
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                        <h3 class="text-xl font-bold mb-4">Order Summary</h3>
                        <div id="checkout-items" class="mb-6">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <div class="flex justify-between py-2 border-b border-gray-200">
                                    <div>
                                        <span class="font-semibold"><?= htmlspecialchars($item['name']) ?></span>
                                        <span class="text-gray-500 text-sm">x<?= $item['quantity'] ?></span>
                                    </div>
                                    <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="space-y-2 mb-6">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span id="checkout-subtotal">$<?= number_format(calculateSubtotal(), 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Delivery Fee</span>
                                <span>$2.99</span>
                            </div>
                            <div class="border-t border-gray-200 my-2"></div>
                            <div class="flex justify-between font-bold">
                                <span>Total</span>
                                <span id="checkout-total">$<?= number_format(calculateTotal(), 2) ?></span>
                            </div>
                        </div>
                        <button id="place-order-btn" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded transition duration-300">
                            Place Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Order Confirmation -->
    <section id="order-confirmation" class="py-16 bg-white hidden">
        <div class="max-w-2xl mx-auto px-4 text-center">
            <div class="bg-green-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-green-500 text-3xl"></i>
            </div>
            <h2 class="text-3xl font-bold mb-4">Order Confirmed!</h2>
            <p class="text-xl mb-6">Thank you for your order <span id="customer-name" class="font-bold"></span>!</p>
            <p class="mb-8">Your order #<span id="order-number" class="font-bold"></span> is being prepared and will arrive in approximately <span class="font-bold">30-45 minutes</span>.</p>
            <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left">
                <h3 class="text-xl font-bold mb-4">Order Details</h3>
                <div id="confirmation-items">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="flex justify-between py-2">
                            <div>
                                <span><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-gray-500 text-sm">x<?= $item['quantity'] ?></span>
                            </div>
                            <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-gray-200 my-4"></div>
                <div class="flex justify-between font-bold">
                    <span>Total</span>
                    <span id="confirmation-total">$<?= number_format(calculateTotal(), 2) ?></span>
                </div>
            </div>
            <a href="#" class="inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-full transition duration-300">
                Back to Home
            </a>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-gray-100">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Contact Us</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Get in Touch</h3>
                    <form class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2" for="contact-name">Name</label>
                            <input type="text" id="contact-name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2" for="contact-email">Email</label>
                            <input type="email" id="contact-email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2" for="contact-message">Message</label>
                            <textarea id="contact-message" rows="4" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                        </div>
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded transition duration-300">
                            Send Message
                        </button>
                    </form>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4">Our Location</h3>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3022.215209179051!2d-73.9878449242399!3d40.75798597138951!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c25855c6480299%3A0x55194ec5a1ae072e!2sTimes%20Square!5e0!3m2!1sen!2sus!4v1681234567890!5m2!1sen!2sus" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-start">
                            <i class="fas fa-map-marker-alt text-green-500 mt-1 mr-3"></i>
                            <span>123 Food Street, New York, NY 10001</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone-alt text-green-500 mr-3"></i>
                            <span>(123) 456-7890</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-green-500 mr-3"></i>
                            <span>info@burgerpalace.com</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock text-green-500 mr-3"></i>
                            <span>Open daily 8:00 AM - 10:00 PM</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Burger Palace</h3>
                    <p class="text-gray-400">Delicious fast food made with quality ingredients for your enjoyment.</p>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Home</a></li>
                        <li><a href="#menu" class="text-gray-400 hover:text-white transition">Menu</a></li>
                        <li><a href="#promotions" class="text-gray-400 hover:text-white transition">Promotions</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white transition">Contact Us</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Refund Policy</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Follow Us</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition text-xl"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition text-xl"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition text-xl"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition text-xl"><i class="fab fa-tiktok"></i></a>
                    </div>
                    <div class="mt-4">
                        <p class="text-gray-400">Download our app:</p>
                        <div class="flex space-x-2 mt-2">
                            <a href="#"><img src="https://via.placeholder.com/120x40?text=App+Store" alt="App Store" class="h-10"></a>
                            <a href="#"><img src="https://via.placeholder.com/120x40?text=Google+Play" alt="Google Play" class="h-10"></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?= date('Y') ?> Burger Palace. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg hidden toast">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span id="toast-message">Item added to cart!</span>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-lg text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-500 mx-auto mb-4"></div>
            <p class="text-lg font-semibold">Processing your order...</p>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-button').addEventListener('click', function() {
            document.querySelector('.mobile-menu').classList.toggle('hidden');
        });

        // Category buttons
        document.querySelectorAll('.category-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.category-btn').forEach(btn => {
                    btn.classList.remove('bg-green-500', 'text-white');
                    btn.classList.add('bg-gray-200', 'hover:bg-gray-300');
                });
                this.classList.remove('bg-gray-200', 'hover:bg-gray-300');
                this.classList.add('bg-green-500', 'text-white');
            });
        });

        // Add to cart
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add_to_cart&product_id=${productId}&quantity=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update cart count
                        document.getElementById('cart-count').textContent = data.cart_count;
                        
                        // Show toast
                        document.getElementById('toast-message').textContent = data.message;
                        const toast = document.getElementById('toast');
                        toast.classList.remove('hidden');
                        setTimeout(() => {
                            toast.classList.add('hidden');
                        }, 3000);
                        
                        // If cart is visible, update it
                        if (!document.getElementById('cart').classList.contains('hidden')) {
                            updateCartDisplay();
                        }
                    }
                });
            });
        });

        // Update cart display
        function updateCartDisplay() {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_cart'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update cart count
                    document.getElementById('cart-count').textContent = data.cart_count;
                    
                    // Update cart items if cart is visible
                    if (!document.getElementById('cart').classList.contains('hidden')) {
                        renderCartItems();
                    }
                    
                    // Update totals
                    document.getElementById('subtotal').textContent = '$' + data.subtotal.toFixed(2);
                    document.getElementById('total').textContent = '$' + data.total.toFixed(2);
                    
                    // Enable/disable checkout button
                    document.getElementById('checkout-btn').disabled = data.cart_count === 0;
                }
            });
        }

        // Render cart items
        function renderCartItems() {
            const cartItemsEl = document.getElementById('cart-items');
            
            if (<?= empty($_SESSION['cart']) ? 'true' : 'false' ?>) {
                cartItemsEl.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Your cart is empty</p>
                    </div>
                `;
                return;
            }
            
            let itemsHTML = '';
            
            <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                itemsHTML += `
                    <div class="flex items-center justify-between py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-16 h-16 object-cover rounded">
                            <div class="ml-4">
                                <h4 class="font-semibold"><?= htmlspecialchars($item['name']) ?></h4>
                                <p class="text-green-500">$<?= number_format($item['price'], 2) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <button class="decrease-quantity px-2 py-1 bg-gray-200 rounded" data-id="<?= $id ?>">
                                <i class="fas fa-minus text-xs"></i>
                            </button>
                            <span class="mx-2"><?= $item['quantity'] ?></span>
                            <button class="increase-quantity px-2 py-1 bg-gray-200 rounded" data-id="<?= $id ?>">
                                <i class="fas fa-plus text-xs"></i>
                            </button>
                            <button class="remove-item ml-4 text-red-500" data-id="<?= $id ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            <?php endforeach; ?>
            
            cartItemsEl.innerHTML = itemsHTML;
            
            // Add event listeners to quantity buttons
            document.querySelectorAll('.decrease-quantity').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-id');
                    updateCartItem(productId, -1);
                });
            });
            
            document.querySelectorAll('.increase-quantity').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-id');
                    updateCartItem(productId, 1);
                });
            });
            
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-id');
                    removeCartItem(productId);
                });
            });
        }

        // Update cart item quantity
        function updateCartItem(productId, change) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_cart&product_id=${productId}&change=${change}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateCartDisplay();
                }
            });
        }

        // Remove cart item
        function removeCartItem(productId) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_from_cart&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateCartDisplay();
                }
            });
        }

        // Navigation to cart
        document.querySelectorAll('a[href="#cart"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('section').forEach(section => {
                    section.classList.add('hidden');
                });
                document.getElementById('cart').classList.remove('hidden');
                renderCartItems();
            });
        });

        // Checkout button
        document.getElementById('checkout-btn').addEventListener('click', function() {
            document.querySelectorAll('section').forEach(section => {
                section.classList.add('hidden');
            });
            document.getElementById('checkout').classList.remove('hidden');
            
            // Update checkout items
            const checkoutItemsEl = document.getElementById('checkout-items');
            let itemsHTML = '';
            
            <?php foreach ($_SESSION['cart'] as $item): ?>
                itemsHTML += `
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <div>
                            <span class="font-semibold"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="text-gray-500 text-sm">x<?= $item['quantity'] ?></span>
                        </div>
                        <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                    </div>
                `;
            <?php endforeach; ?>
            
            checkoutItemsEl.innerHTML = itemsHTML;
            
            // Update totals
            document.getElementById('checkout-subtotal').textContent = '$<?= number_format(calculateSubtotal(), 2) ?>';
            document.getElementById('checkout-total').textContent = '$<?= number_format(calculateTotal(), 2) ?>';
        });

        // Place order button
        document.getElementById('place-order-btn').addEventListener('click', function() {
            const name = document.getElementById('name').value;
            const phone = document.getElementById('phone').value;
            const address = document.getElementById('address').value;
            const instructions = document.getElementById('instructions').value;
            const paymentMethod = document.querySelector('input[name="payment"]:checked').value;
            
            // Show loading
            document.getElementById('loading').classList.remove('hidden');
            
            // Submit order
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=place_order&name=${encodeURIComponent(name)}&phone=${encodeURIComponent(phone)}&address=${encodeURIComponent(address)}&instructions=${encodeURIComponent(instructions)}&payment_method=${paymentMethod}`
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading
                document.getElementById('loading').classList.add('hidden');
                
                if (data.status === 'success') {
                    // Hide checkout, show confirmation
                    document.getElementById('checkout').classList.add('hidden');
                    document.getElementById('order-confirmation').classList.remove('hidden');
                    
                    // Update confirmation page
                    document.getElementById('customer-name').textContent = data.customer_name;
                    document.getElementById('order-number').textContent = data.order_id;
                    
                    // Update confirmation items
                    const confirmationItemsEl = document.getElementById('confirmation-items');
                    let itemsHTML = '';
                    
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        itemsHTML += `
                            <div class="flex justify-between py-2">
                                <div>
                                    <span><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="text-gray-500 text-sm">x<?= $item['quantity'] ?></span>
                                </div>
                                <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                            </div>
                        `;
                    <?php endforeach; ?>
                    
                    confirmationItemsEl.innerHTML = itemsHTML;
                    
                    // Update total
                    document.getElementById('confirmation-total').textContent = '$<?= number_format(calculateTotal(), 2) ?>';
                } else {
                    alert(data.message);
                }
            });
        });

        // Back to home from confirmation
        document.querySelector('#order-confirmation a').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('section').forEach(section => {
                section.classList.add('hidden');
            });
            document.querySelector('section:first-of-type').classList.remove('hidden');
        });
    </script>
</body>
</html>