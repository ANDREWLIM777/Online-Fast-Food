<?php
session_start();
require '../db_connect.php';

// Debug mode
$debug = true;
$logFile = 'confirmation_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Increase memory limit for Dompdf
ini_set('memory_limit', '256M');
$logMessage("Memory limit: " . ini_get('memory_limit'));

// Check for vendor/autoload.php
$autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $logMessage("Autoload file not found at: $autoloadPath");
    if ($debug) {
        die("Debug: Autoload file not found at: $autoloadPath");
    }
    die("System error: Required dependencies are missing. Please contact support.");
}
require $autoloadPath;

use Dompdf\Dompdf;

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: ../../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];

// Handle order_id from GET or session
$orderCode = '';
$order = [];
$sessionItems = [];

if (isset($_GET['order_id'])) {
    $orderCode = $_GET['order_id'];
    // Fetch from payment_history and orders
    $stmt = $conn->prepare("
        SELECT ph.order_id, ph.date AS timestamp, ph.amount, ph.status, ph.method, ph.payment_details, ph.delivery_method, ph.delivery_address,
               o.items
        FROM payment_history ph
        LEFT JOIN orders o ON ph.order_id = o.order_id
        WHERE ph.order_id = ? AND ph.customer_id = ?
    ");
    $stmt->bind_param("si", $orderCode, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $logMessage("Order not found: $orderCode for customer_id: $customerId");
        header("Location: payment_history.php?error=Order not found");
        exit();
    }

    $amount = $order['amount'];
    $method = $order['method'] ?? 'unknown';
    $paymentDetails = $order['payment_details'] ?? 'N/A';
    $deliveryMethod = $order['delivery_method'] ?? 'pickup';
    $deliveryAddress = $order['delivery_address'] ? json_decode($order['delivery_address'], true) : null;
    $timestamp = $order['timestamp'];
    $sessionItems = $order['items'] ? json_decode($order['items'], true) : [];
} elseif (isset($_SESSION['last_order'])) {
    $order = $_SESSION['last_order'];
    $orderCode = $order['order_code'] ?? '';
    $amount = $order['amount'] ?? 0;
    $method = $order['method'] ?? 'unknown';
    $paymentDetails = $order['payment_details'] ?? 'N/A';
    $deliveryMethod = $order['delivery_method'] ?? 'pickup';
    $deliveryAddress = $order['delivery_address'] ?? null;
    if (is_string($deliveryAddress)) {
        $deliveryAddress = json_decode($deliveryAddress, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logMessage("JSON decode error for delivery_address: " . json_last_error_msg());
            $deliveryAddress = null;
        }
    } elseif (!is_array($deliveryAddress)) {
        $deliveryAddress = null;
    }
    $timestamp = $order['timestamp'] ?? date('Y-m-d H:i:s');
    $sessionItems = $order['items'] ?? [];
} else {
    $logMessage("No order_id or last_order for customer_id: $customerId");
    header("Location: cart.php");
    exit();
}

// Validate order code
if (empty($orderCode)) {
    $logMessage("Empty order_code for customer_id: $customerId");
    header("Location: cart.php?error=Invalid order");
    exit();
}

// Fetch customer email
$stmt = $conn->prepare("SELECT email FROM customers WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$customerEmail = $customer['email'] ?? 'unknown@example.com';
$stmt->close();

// Update order status to completed if payment is confirmed
if (isset($_SESSION['last_order'])) {
    $stmt = $conn->prepare("
        SELECT status FROM payment_history WHERE order_id = ? AND customer_id = ?
    ");
    $stmt->bind_param("si", $orderCode, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if ($payment && $payment['status'] === 'completed') {
        $stmt = $conn->prepare("
            UPDATE orders SET status = 'completed' WHERE order_id = ? AND customer_id = ?
        ");
        $stmt->bind_param("si", $orderCode, $customerId);
        if ($stmt->execute()) {
            $logMessage("Updated order status to completed for order: $orderCode");
        } else {
            $logMessage("Failed to update order status for order: $orderCode - " . $stmt->error);
        }
        $stmt->close();
    }
}

// Fetch order items from order_items table
$orderItems = [];
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

// Fallback to orders.items if order_items is empty
if (empty($orderItems)) {
    $logMessage("No order items in order_items table for order: $orderCode");
    $stmt = $conn->prepare("SELECT items FROM orders WHERE order_id = ?");
    $stmt->bind_param("s", $orderCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $itemsJson = $row['items'];
        if ($itemsJson) {
            $orderItems = json_decode($itemsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $logMessage("JSON decode error for orders.items: " . json_last_error_msg());
                $orderItems = [];
            }
        }
    }
    $stmt->close();
}

// Fallback to session items if still empty
if (empty($orderItems) && !empty($sessionItems)) {
    $logMessage("No order items in database, using session items for order: $orderCode");
    $orderItems = $sessionItems;
}

// Log order details
$logMessage("Displaying confirmation for order: $orderCode, Customer ID: $customerId");
$logMessage("Order items count: " . count($orderItems));
$logMessage("Order items: " . json_encode($orderItems));
$logMessage("Delivery method: $deliveryMethod");
$logMessage("Delivery address: " . json_encode($deliveryAddress));
$logMessage("Customer email: $customerEmail");

// Handle PDF download
if (isset($_GET['download_invoice'])) {
    try {
        $dompdf = new Dompdf(['enable_remote' => true]);
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                h1 { color: #f97316; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background-color: #f3f4f6; }
                img { max-width: 60px; height: auto; }
                .header { margin-bottom: 20px; }
                .footer { margin-top: 20px; font-size: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Invoice #<?= htmlspecialchars($orderCode) ?></h1>
                <p><strong>Order ID:</strong> <?= htmlspecialchars($orderCode) ?></p>
                <p><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($timestamp)) ?></p>
                <p><strong>Total:</strong> RM <?= number_format($amount, 2) ?></p>
                <p><strong>Delivery Method:</strong> <?= htmlspecialchars($deliveryMethod) ?></p>
                <p><strong>Delivery Address:</strong> 
                    <?php
                    if ($deliveryMethod === 'delivery' && is_array($deliveryAddress)) {
                        $addressParts = [];
                        if (!empty($deliveryAddress['street_address'])) {
                            $addressParts[] = htmlspecialchars($deliveryAddress['street_address']);
                        }
                        if (!empty($deliveryAddress['city'])) {
                            $addressParts[] = htmlspecialchars($deliveryAddress['city']);
                        }
                        if (!empty($deliveryAddress['postal_code'])) {
                            $addressParts[] = htmlspecialchars($deliveryAddress['postal_code']);
                        }
                        echo implode(', ', $addressParts) ?: 'N/A';
                    } else {
                        echo 'Pick Up';
                    }
                    ?>
                </p>
                <p><strong>Customer Email:</strong> <?= htmlspecialchars($customerEmail) ?></p>
            </div>
            <h3>Items Ordered</h3>
            <?php if (!empty($orderItems)): ?>
                <table>
                    <tr><th>Image</th><th>Item</th><th>Quantity</th><th>Price</th><th>Total</th></tr>
                    <?php foreach ($orderItems as $item): ?>
                        <?php
                        $imageRelativePath = '/Online-Fast-Food/Admin/Manage_Menu_Item/' . ($item['photo'] ?: 'placeholder.jpg');
                        $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . $imageRelativePath;
                        $imagePath = $_SERVER['DOCUMENT_ROOT'] . $imageRelativePath;
                        if (!file_exists($imagePath)) {
                            $logMessage("Image not found for item {$item['item_name']}: $imagePath");
                            $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/Online-Fast-Food/Admin/Manage_Menu_Item/placeholder.jpg';
                        }
                        ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>"></td>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>RM <?= number_format($item['price'], 2) ?></td>
                            <td>RM <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No items found for this order.</p>
            <?php endif; ?>
            <div class="footer">
                <p>Thank you for choosing Brizo Fast Food Melaka!</p>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $logMessage("Downloaded invoice for order: $orderCode");
        $dompdf->stream("Invoice_$orderCode.pdf", ['Attachment' => true]);
        exit();
    } catch (Exception $e) {
        $logMessage("PDF generation failed for order: $orderCode - " . $e->getMessage());
        if ($debug) {
            die("PDF Error: " . htmlspecialchars($e->getMessage()));
        }
        header("Location: confirmation.php?error=PDF generation failed: " . urlencode($e->getMessage()));
        exit();
    }
}

// Generate CSRF token for email form
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
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
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .invoice-section { background-color: white; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; }
        .btn-orange { background-color: #f97316; color: white; }
        .btn-orange:hover { background-color: #ea580c; }
        .btn-green { background-color: #10b981; color: white; }
        .btn-green:hover { background-color: #059669; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        .invoice-table th { background-color: #f3f4f6; }
        .invoice-table img { max-width: 60px; height: auto; }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-orange-600">Brizo Fast Food Melaka</h1>
            <a href="cart.php" class="text-orange-600 hover:text-orange-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Cart
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (isset($_GET['email_sent'])): ?>
            <div class="mb-6 p-4 rounded-lg <?= $_GET['email_sent'] === 'success' ? 'bg-green-50 border-l-4 border-green-500' : 'bg-red-50 border-l-4 border-red-500' ?>">
                <p class="<?= $_GET['email_sent'] === 'success' ? 'text-green-700' : 'text-red-700' ?> text-lg flex items-center">
                    <i class="fas <?= $_GET['email_sent'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                    <?= $_GET['email_sent'] === 'success' ? 'Invoice successfully sent to your email!' : 'Failed to send invoice: ' . htmlspecialchars($_GET['message'] ?? 'Please try again later.') ?>
                </p>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500">
                <p class="text-red-700 text-lg flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?>
                </p>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Order Confirmation</h2>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                <p class="text-green-700 text-lg flex items-center">
                    <i class="fas fa-check-circle mr-2"></i> Thank you! Your order has been successfully placed.
                </p>
            </div>

            <!-- Invoice Section -->
            <div id="invoice-section" class="invoice-section">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Invoice #<?= htmlspecialchars($orderCode) ?></h3>
                <div class="bg-gray-50 p-6 rounded-lg mb-4">
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
                    <p class="text-gray-600"><strong>Delivery Address:</strong> 
                        <?php
                        if ($deliveryMethod === 'delivery' && is_array($deliveryAddress)) {
                            $addressParts = [];
                            if (!empty($deliveryAddress['street_address'])) {
                                $addressParts[] = htmlspecialchars($deliveryAddress['street_address']);
                            }
                            if (!empty($deliveryAddress['city'])) {
                                $addressParts[] = htmlspecialchars($deliveryAddress['city']);
                            }
                            if (!empty($deliveryAddress['postal_code'])) {
                                $addressParts[] = htmlspecialchars($deliveryAddress['postal_code']);
                            }
                            echo implode(', ', $addressParts) ?: 'N/A';
                        } else {
                            echo 'Pick Up';
                        }
                        ?>
                    </p>
                    <p class="text-gray-600"><strong>Customer Email:</strong> <?= htmlspecialchars($customerEmail) ?></p>
                </div>

                <!-- Order Items -->
                <h3 class="text-xl font-medium text-gray-700 mb-4">Items Ordered</h3>
                <?php if (!empty($orderItems)): ?>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <img src="/Online-Fast-Food/Admin/Manage_Menu_Item/<?= htmlspecialchars($item['photo'] ?: 'placeholder.jpg') ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" onerror="this.src='/Online-Fast-Food/Admin/Manage_Menu_Item/placeholder.jpg'" style="max-width: 60px; height: auto;">
                                    </td>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>RM <?= number_format($item['price'], 2) ?></td>
                                    <td>RM <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-red-600">No items found for this order.</p>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="?download_invoice=1&order_id=<?= urlencode($orderCode) ?>" class="px-4 py-2 btn-orange rounded-lg flex items-center">
                    <i class="fas fa-download mr-2"></i> Download Invoice
                </a>
                <form action="send_invoice.php" method="POST" class="inline-flex">
                    <input type="hidden" name="order_code" value="<?= htmlspecialchars($orderCode) ?>">
                    <input type="hidden" name="customer_email" value="<?= htmlspecialchars($customerEmail) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit" class="px-4 py-2 btn-green rounded-lg flex items-center">
                        <i class="fas fa-envelope mr-2"></i> Send Invoice via Email
                    </button>
                </form>
                <a href="payment_history.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-history mr-2"></i> View Payment History
                </a>
                <a href="cart.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center">
                    <i class="fas fa-shopping-cart mr-2"></i> Continue Shopping
                </a>
                <a href="feedback.php?order_id=<?= urlencode($orderCode) ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
                    <i class="fas fa-star mr-2"></i> Provide Feedback
                </a>
            </div>
        </div>
    </main>
</body>
</html>