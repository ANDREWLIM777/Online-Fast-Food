<?php
ob_start();
session_start();
require '../db_connect.php';

// Debug mode
$debug = true;
$logFile = 'confirmation_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    $logMessage("Session expired for customer_id: " . ($_SESSION['customer_id'] ?? 'unknown'));
    header("Location: ../../login.php?message=Session+expired");
    exit();
}
$_SESSION['last_activity'] = time();

// Check database connection
if ($conn->connect_error) {
    $logMessage("Database connection failed: " . $conn->connect_error);
    header("Location: ../../error.php?message=Database+connection+failed");
    exit();
}

// Check for vendor/autoload.php
$autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $logMessage("Autoload file not found at: $autoloadPath");
    if ($debug) {
        die("Debug: Autoload file not found at: $autoloadPath");
    }
    header("Location: ../../error.php?message=Required+dependencies+missing");
    exit();
}
require $autoloadPath;

use Dompdf\Dompdf;

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: ../../login.php");
    exit();
}

$customerId = (int)$_SESSION['customer_id'];

// Handle order_id from GET or session
$orderCode = '';
$order = [];
$sessionItems = [];
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

if (isset($_GET['order_id'])) {
    $orderCode = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING);
    $stmt = $conn->prepare("
        SELECT ph.order_id, ph.date AS timestamp, ph.amount, ph.status, ph.method, ph.payment_details, ph.delivery_method, ph.delivery_address
        FROM payment_history ph
        WHERE ph.order_id = ? AND ph.customer_id = ?
    ");
    if (!$stmt) {
        $logMessage("Prepare failed for payment_history: " . $conn->error);
        header("Location: ../../error.php?message=Database+error");
        exit();
    }
    $stmt->bind_param("si", $orderCode, $customerId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $logMessage("Order not found: $orderCode for customer_id: $customerId");
        $errorMessage = "Order not found. Please select an order from your payment history.";
    }
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
    $errorMessage = "No order selected. Please choose an order from your payment history.";
}

// Validate order code
if (empty($orderCode) && empty($errorMessage)) {
    $logMessage("Empty order_code for customer_id: $customerId");
    $errorMessage = "Invalid order. Please select an order from your payment history.";
}

// Fetch customer email
$stmt = $conn->prepare("SELECT email FROM customers WHERE id = ?");
if (!$stmt) {
    $logMessage("Prepare failed for customer email: " . $conn->error);
    header("Location: ../../error.php?message=Database+error");
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$customerEmail = $customer['email'] ?? 'unknown@example.com';
$stmt->close();

// Update order status to completed if payment is confirmed
if (isset($_SESSION['last_order']) && !empty($orderCode)) {
    $stmt = $conn->prepare("
        SELECT status FROM payment_history WHERE order_id = ? AND customer_id = ?
    ");
    if (!$stmt) {
        $logMessage("Prepare failed for payment status: " . $conn->error);
        header("Location: ../../error.php?message=Database+error");
        exit();
    }
    $stmt->bind_param("si", $orderCode, $customerId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($payment && $payment['status'] === 'completed') {
        $stmt = $conn->prepare("
            UPDATE orders SET status = 'completed' WHERE order_id = ? AND customer_id = ?
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for order update: " . $conn->error);
        } else {
            $stmt->bind_param("si", $orderCode, $customerId);
            if ($stmt->execute()) {
                $logMessage("Updated order status to completed for order: $orderCode");
            } else {
                $logMessage("Failed to update order status for order: $orderCode - " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// Fetch order items
$orderItems = [];
if (!empty($orderCode)) {
    $stmt = $conn->prepare("
        SELECT oi.item_id, oi.quantity, oi.price, m.item_name, m.photo
        FROM order_items oi
        JOIN menu_items m ON oi.item_id = m.id
        WHERE oi.order_id = ?
    ");
    if (!$stmt) {
        $logMessage("Prepare failed for order items: " . $conn->error);
        $errorMessage = "Database error while fetching order items.";
    } else {
        $stmt->bind_param("s", $orderCode);
        $stmt->execute();
        $orderItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Fallback to session items
if (empty($orderItems) && !empty($sessionItems)) {
    $logMessage("No order items in database, using session items for order: $orderCode");
    $orderItems = $sessionItems;
}

// Log order details
$logMessage("Displaying confirmation for order: $orderCode, Customer ID: $customerId");
$logMessage("Order items count: " . count($orderItems));
$logMessage("Order items: " . json_encode($orderItems));
$logMessage("Delivery method: " . ($order['delivery_method'] ?? 'N/A'));
$logMessage("Delivery address: " . json_encode($order['delivery_address'] ?? null));
$logMessage("Customer email: $customerEmail");

// Handle PDF download
if (isset($_GET['download_invoice']) && !empty($orderCode)) {
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
                <p><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($order['timestamp'] ?? date('Y-m-d H:i:s'))) ?></p>
                <p><strong>Total:</strong> RM <?= number_format($order['amount'] ?? 0, 2) ?></p>
                <p><strong>Delivery Method:</strong> <?= htmlspecialchars($order['delivery_method'] ?? 'pickup') ?></p>
                <p><strong>Delivery Address:</strong> 
                    <?php
                    $deliveryAddress = $order['delivery_address'] ?? null;
                    if ($deliveryAddress && $order['delivery_method'] === 'delivery') {
                        if (is_string($deliveryAddress)) {
                            $deliveryAddress = json_decode($deliveryAddress, true);
                        }
                        if (is_array($deliveryAddress)) {
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
                            echo 'N/A';
                        }
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
                        ?>
                        <tr>
                            <td><img src=".<?= htmlspecialchars($imageRelativePath) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>"></td>
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
        $errorMessage = "Unable to generate PDF: " . htmlspecialchars($e->getMessage());
    }
}

// Generate CSRF token
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
        .btn-primary { background-color: #f97316; color: white; }
        .btn-primary:hover { background-color: #ea580c; }
        .text-primary { color: #f97316; }
        .text-primary:hover { color: #ea580c; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        .invoice-table th { background-color: #f3f4f6; }
        .invoice-table img { max-width: 60px; height: auto; }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="../../index.php" class="text-primary hover:text-primary flex items-center">
                <i class="fas fa-home mr-2"></i> Back to Home
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
        <?php if ($errorMessage): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500">
                <p class="text-red-700 text-lg flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= $errorMessage ?>
                </p>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Order Confirmation</h2>
            <?php if (empty($orderCode) || empty($order)): ?>
                <p class="text-gray-600">Please select an order from your payment history to view details.</p>
                <a href="payment_history.php" class="mt-4 px-4 py-2 btn-primary rounded-lg flex items-center inline-flex">
                    <i class="fas fa-history mr-2"></i> Go to Payment History
                </a>
            <?php else: ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                    <p class="text-green-700 text-lg flex items-center">
                        <i class="fas fa-check-circle mr-2"></i> Thank you! Your order has been successfully placed.
                    </p>
                </div>

                <!-- Invoice Section -->
                <div id="invoice-section" class="invoice-section" role="region" aria-labelledby="invoice-heading">
                    <h3 id="invoice-heading" class="text-xl font-medium text-gray-700 mb-4">Invoice #<?= htmlspecialchars($orderCode) ?></h3>
                    <div class="bg-gray-50 p-6 rounded-lg mb-4">
                        <p class="text-gray-600"><strong>Order ID:</strong> <?= htmlspecialchars($orderCode) ?></p>
                        <p class="text-gray-600"><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($order['timestamp'] ?? date('Y-m-d H:i:s'))) ?></p>
                        <p class="text-gray-600"><strong>Total:</strong> RM <?= number_format($order['amount'] ?? 0, 2) ?></p>
                        <p class="text-gray-600"><strong>Payment Method:</strong> 
                            <i class="fas <?= ($order['method'] ?? 'unknown') === 'card' ? 'fa-credit-card' : (($order['method'] ?? 'unknown') === 'online_banking' ? 'fa-university' : 'fa-wallet') ?> mr-1"></i>
                            <?= ucfirst(htmlspecialchars($order['method'] ?? 'unknown')) ?> (<?= htmlspecialchars($order['payment_details'] ?? 'N/A') ?>)
                        </p>
                        <p class="text-gray-600"><strong>Delivery Method:</strong> 
                            <i class="fas <?= ($order['delivery_method'] ?? 'pickup') === 'delivery' ? 'fa-truck' : 'fa-store' ?> mr-1"></i>
                            <?= ucfirst(htmlspecialchars($order['delivery_method'] ?? 'pickup')) ?>
                        </p>
                        <p class="text-gray-600"><strong>Delivery Address:</strong> 
                            <?php
                            $deliveryAddress = $order['delivery_address'] ?? null;
                            if ($deliveryAddress && ($order['delivery_method'] ?? 'pickup') === 'delivery') {
                                if (is_string($deliveryAddress)) {
                                    $deliveryAddress = json_decode($deliveryAddress, true);
                                }
                                if (is_array($deliveryAddress)) {
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
                                    echo 'N/A';
                                }
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
                        <table class="invoice-table" role="grid" aria-label="Order items">
                            <thead>
                                <tr>
                                    <th scope="col">Image</th>
                                    <th scope="col">Item</th>
                                    <th scope="col">Quantity</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Total</th>
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
                <div class="mt-8 flex justify-end space-x-4 flex-wrap">
                    <a href="?download_invoice=1&order_id=<?= urlencode($orderCode) ?>" class="px-4 py-2 btn-primary rounded-lg flex items-center m-1">
                        <i class="fas fa-download mr-2"></i> Download Invoice
                    </a>
                    <form action="send_invoice.php" method="POST" class="inline-flex m-1">
                        <input type="hidden" name="order_code" value="<?= htmlspecialchars($orderCode) ?>">
                        <input type="hidden" name="customer_email" value="<?= htmlspecialchars($customerEmail) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="px-4 py-2 btn-primary rounded-lg flex items-center">
                            <i class="fas fa-envelope mr-2"></i> Send Invoice via Email
                        </button>
                    </form>
                    <a href="payment_history.php" class="px-4 py-2 btn-primary rounded-lg flex items-center m-1">
                        <i class="fas fa-history mr-2"></i> View Payment History
                    </a>
                    <a href="../../index.php" class="px-4 py-2 btn-primary rounded-lg flex items-center m-1">
                        <i class="fas fa-shopping-cart mr-2"></i> Continue Shopping
                    </a>
                    <a href="feedback.php?order_id=<?= urlencode($orderCode) ?>" class="px-4 py-2 btn-primary rounded-lg flex items-center m-1">
                        <i class="fas fa-star mr-2"></i> Provide Feedback
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php
// Clear last_order session only if order is valid
if (!empty($orderCode)) {
    unset($_SESSION['last_order']);
}
ob_end_flush();
?>