<?php
session_start();
require '../db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: ../../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];

// Check if order details are available in session
if (!isset($_SESSION['last_order'])) {
    header("Location: cart.php");
    exit();
}

$order = $_SESSION['last_order'];
$orderCode = $order['order_code'];
$amount = $order['amount'];
$method = $order['method'];
$paymentDetails = $order['payment_details'];
$deliveryMethod = $order['delivery_method'];
$deliveryAddress = $order['delivery_address'];
$timestamp = $order['timestamp'];
$items = $order['items'];

// Fetch order items from the database to ensure data integrity
$stmt = $conn->prepare("
    SELECT oi.item_id, oi.quantity, oi.price, m.item_name, m.photo
    FROM order_items oi
    JOIN menu_items m ON oi.item_id = m.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("s", $orderCode);
$stmt->execute();
$result = $stmt->get_result();
$orderItems = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Log order details for debugging
$logFile = 'confirmation_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};
$logMessage("Displaying confirmation for order: $orderCode, Customer ID: $customerId");
$logMessage("Order items: " . json_encode($orderItems));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Brizo Fast Food Melaka</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-800">Brizo Fast Food Melaka</h1>
            <a href="cart.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Cart
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Order Confirmation</h2>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                <p class="text-green-700 text-lg flex items-center">
                    <i class="fas fa-check-circle mr-2"></i> Thank you! Your order has been placed successfully.
                </p>
            </div>

            <!-- Order Summary -->
            <section class="mb-8">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Order Details</h3>
                <div class="bg-gray-50 p-6 rounded-lg">
                    <p class="text-gray-600"><strong>Order ID:</strong> <?= htmlspecialchars($orderCode) ?></p>
                    <p class="text-gray-600"><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($timestamp)) ?></p>
                    <p class="text-gray-600"><strong>Total:</strong> RM <?= number_format($amount, 2) ?></p>
                    <p class="text-gray-600"><strong>Payment Method:</strong> 
                        <i class="fas <?= $method === 'card' ? 'fa-credit-card' : ($method === 'online_banking' ? 'fa-university' : 'fa-wallet') ?> mr-1"></i>
                        <?= ucfirst(htmlspecialchars($method)) ?> (<?= htmlspecialchars($paymentDetails) ?>)
                    </p>
                    <p class="text-gray-600"><strong>Delivery Method:</strong> 
                        <i class="fas <?= $deliveryMethod === 'delivery' ? 'fa-truck' : 'fa-store' ?> mr-1"></i>
                        <?= ucfirst(htmlspecialchars($deliveryMethod)) ?>
                    </p>
                    <?php if ($deliveryMethod === 'delivery' && $deliveryAddress): ?>
                        <p class="text-gray-600"><strong>Delivery Address:</strong> <?= htmlspecialchars($deliveryAddress) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Order Items -->
            <section>
                <h3 class="text-xl font-medium text-gray-700 mb-4">Items Ordered</h3>
                <div class="space-y-4">
                    <?php foreach ($orderItems as $item): ?>
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg card-hover">
                            <img src="/Online-Fast-Food/Admin/Manage_Menu_Item/<?= htmlspecialchars($item['photo']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" class="w-20 h-20 object-cover rounded-lg mr-4">
                            <div class="flex-1">
                                <h4 class="text-lg font-medium text-gray-800"><?= htmlspecialchars($item['item_name']) ?></h4>
                                <p class="text-gray-600">Quantity: <?= $item['quantity'] ?> | Price: RM <?= number_format($item['price'], 2) ?> each | Total: RM <?= number_format($item['quantity'] * $item['price'], 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="payment_history.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-history mr-2"></i> View Payment History
                </a>
                <a href="cart.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center">
                    <i class="fas fa-shopping-cart mr-2"></i> Continue Shopping
                </a>
            </div>
        </div>
    </main>
</body>
</html>