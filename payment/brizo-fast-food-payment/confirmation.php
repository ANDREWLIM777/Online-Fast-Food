<?php
ob_start();
session_start();

// Define and verify database connection file path
$dbPath = '../db_connect.php';
$absoluteDbPath = realpath(dirname(__FILE__) . '/' . $dbPath);
if (!file_exists($absoluteDbPath)) {
    $dbPath = $_SERVER['DOCUMENT_ROOT'] . '/Online-Fast-Food/db_connect.php';
    $absoluteDbPath = realpath($dbPath);
    if (!file_exists($absoluteDbPath)) {
        die("Error: Database connection file not found at: $absoluteDbPath or DOCUMENT_ROOT fallback");
    }
}
require $dbPath;

// Enable debug mode for detailed logging
$debug = true;
$logFile = 'confirmation_logs.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Log incoming request data
$logMessage("GET data: " . json_encode($_GET));
$logMessage("SESSION data: " . json_encode($_SESSION));

// Check session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    $logMessage("Session expired for customer_id: " . ($_SESSION['customer_id'] ?? 'unknown'));
    header("Location: /Online-Fast-Food/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Verify database connection
if ($conn->connect_error) {
    $logMessage("Database connection failed: " . $conn->connect_error);
    header("Location: /Online-Fast-Food/error.php?error=" . urlencode("Database connection failed"));
    exit();
}

// Check for vendor/autoload.php for Dompdf
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $logMessage("Autoload file not found at: $autoloadPath");
    if ($debug) {
        die("Debug: Autoload file not found at: $autoloadPath");
    }
    header("Location: /Online-Fast-Food/error.php?error=" . urlencode("Required dependencies missing"));
    exit();
}
require_once $autoloadPath;

use Dompdf\Dompdf;

// Ensure user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: /Online-Fast-Food/login.php");
    exit();
}

$customerId = (int)$_SESSION['customer_id'];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$csrfParam = urlencode($csrfToken);

// Validate CSRF token for GET requests
if (isset($_GET['csrf_token']) && $_GET['csrf_token'] !== $csrfToken) {
    $logMessage("Invalid CSRF token for order_id: " . ($_GET['order_id'] ?? 'unknown'));
    header("Location: /Online-Fast-Food/error.php?error=" . urlencode("Invalid CSRF token"));
    exit();
}

// Initialize order variables
$orderCode = '';
$order = [];
$sessionItems = [];
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Retrieve order_id from GET or session
if (isset($_GET['order_id'])) {
    $orderCode = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING);
    $stmt = $conn->prepare("
        SELECT ph.order_id, ph.date AS timestamp, ph.amount, ph.status, ph.method, ph.payment_details, ph.delivery_method, ph.delivery_address
        FROM payment_history ph
        WHERE ph.order_id = ? AND ph.customer_id = ?
    ");
    if (!$stmt) {
        $logMessage("Prepare failed for payment_history query: " . $conn->error);
        header("Location: /Online-Fast-Food/error.php?error=" . urlencode("Database error"));
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
    $order['delivery_address'] = $deliveryAddress;
    $sessionItems = $order['items'] ?? [];
} else {
    $logMessage("No order_id or last_order for customer_id: $customerId");
    header("Location: /Online-Fast-Food/payment/brizo-fast-food-payment/payment_history.php?csrf_token=$csrfParam");
    exit();
}

// Validate order code
if (empty($orderCode) && empty($errorMessage)) {
    $logMessage("Empty order_code for customer_id: $customerId");
    $errorMessage = "Invalid order. Please select an order from your payment history.";
}

// Fetch customer email
$stmt = $conn->prepare("SELECT email FROM customers WHERE id = ?");
if (!$stmt) {
    $logMessage("Prepare failed for customer email query: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?error=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$customerEmail = $customer['email'] ?? 'unknown@example.com';
$stmt->close();

// Sync order to orders table with 'pending' status
if (!empty($orderCode)) {
    $stmt = $conn->prepare("SELECT order_id, status FROM orders WHERE order_id = ? AND customer_id = ?");
    if (!$stmt) {
        $logMessage("Prepare failed for orders check: " . $conn->error);
    } else {
        $stmt->bind_param("si", $orderCode, $customerId);
        $stmt->execute();
        $existingOrder = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existingOrder) {
            $stmt = $conn->prepare("
                INSERT INTO orders (order_id, customer_id, total, status, created_at)
                VALUES (?, ?, ?, 'pending', ?)
            ");
            if (!$stmt) {
                $logMessage("Prepare failed for orders insert: " . $conn->error);
            } else {
                $total = $order['amount'] ?? 0;
                $createdAt = $order['timestamp'] ?? date('Y-m-d H:i:s');
                $stmt->bind_param("sids", $orderCode, $customerId, $total, $createdAt);
                if ($stmt->execute()) {
                    $logMessage("Inserted new order: $orderCode with status 'pending' for customer_id: $customerId");
                } else {
                    $logMessage("Failed to insert order: $orderCode - " . $stmt->error);
                }
                $stmt->close();
            }
        } else {
            $logMessage("Order $orderCode already exists with status: " . $existingOrder['status']);
        }
    }
}

// Fetch order items
$orderItems = [];
if (!empty($orderCode)) {
    $stmt = $conn->prepare("
        SELECT oi.item_id, oi.quantity, oi.price, oi.total, m.item_name, m.photo
        FROM order_items oi
        JOIN menu_items m ON oi.item_id = m.id
        WHERE oi.order_id = ?
    ");
    if (!$stmt) {
        $logMessage("Prepare failed for order_items query: " . $conn->error);
        $errorMessage = "Database error while fetching order items.";
    } else {
        $stmt->bind_param("s", $orderCode);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['photo'] = $row['photo'] ?: 'placeholder.jpg';
            $orderItems[] = $row;
            $logMessage("Fetched item: " . $row['item_name'] . ", image: " . $row['photo']);
        }
        $stmt->close();
    }
}

// Fallback to session items if database query fails
if (empty($orderItems) && !empty($sessionItems)) {
    $logMessage("No order items in database, using session items for order: $orderCode");
    foreach ($sessionItems as $item) {
        $item['photo'] = $item['photo'] ?: 'placeholder.jpg';
        $orderItems[] = $item;
        $logMessage("Session item: " . $item['item_name'] . ", image: " . $item['photo']);
    }
}

// Sync order items from session if missing
if (!empty($sessionItems) && empty($orderItems)) {
    $stmt = $conn->prepare("
        INSERT INTO order_items (order_id, item_id, quantity, price, total)
        VALUES (?, ?, ?, ?, ?)
    ");
    if ($stmt) {
        foreach ($sessionItems as $item) {
            $itemId = $item['item_id'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $total = $quantity * $price;
            $stmt->bind_param("siidd", $orderCode, $itemId, $quantity, $price, $total);
            if ($stmt->execute()) {
                $logMessage("Inserted order item for order: $orderCode, item_id: $itemId");
            } else {
                $logMessage("Failed to insert order item for order: $orderCode - " . $stmt->error);
            }
        }
        $stmt->close();
        $stmt = $conn->prepare("
            SELECT oi.item_id, oi.quantity, oi.price, oi.total, m.item_name, m.photo
            FROM order_items oi
            JOIN menu_items m ON oi.item_id = m.id
            WHERE oi.order_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("s", $orderCode);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['photo'] = $row['photo'] ?: 'placeholder.jpg';
                $orderItems[] = $row;
                $logMessage("Fetched item after sync: " . $row['item_name'] . ", image: " . $row['photo']);
            }
            $stmt->close();
        }
    }
}

// Check if order allows feedback
$isOrderCompleted = false;
$hasFeedback = false;
if (!empty($orderCode)) {
    // Check order status
    $stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ? AND customer_id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $orderCode, $customerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result) {
            $rawStatus = $result['status'];
            $orderStatus = strtolower($rawStatus);
            $isOrderCompleted = $orderStatus === 'completed';
            $logMessage("Order $orderCode raw status: $rawStatus, normalized status: $orderStatus, feedback eligibility: " . ($isOrderCompleted ? 'eligible' : 'not eligible'));
        } else {
            $logMessage("No order found in orders table for order_id: $orderCode, customer_id: $customerId");
            // Attempt to re-sync from payment_history
            $stmt = $conn->prepare("SELECT amount, date FROM payment_history WHERE order_id = ? AND customer_id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $orderCode, $customerId);
                $stmt->execute();
                $phOrder = $stmt->get_result()->fetch_assoc();
                if ($phOrder) {
                    $stmt = $conn->prepare("
                        INSERT INTO orders (order_id, customer_id, total, status, created_at)
                        VALUES (?, ?, ?, 'pending', ?)
                    ");
                    if ($stmt) {
                        $total = $phOrder['amount'] ?? 0;
                        $createdAt = $phOrder['date'] ?? date('Y-m-d H:i:s');
                        $stmt->bind_param("sids", $orderCode, $customerId, $total, $createdAt);
                        if ($stmt->execute()) {
                            $logMessage("Re-synced order: $orderCode with status 'pending' for customer_id: $customerId");
                        } else {
                            $logMessage("Failed to re-sync order: $orderCode - " . $stmt->error);
                        }
                        $stmt->close();
                    }
                }
                $stmt->close();
            }
        }
        $stmt->close();
    } else {
        $logMessage("Prepare failed for order status check: " . $conn->error);
    }

    // Check if feedback already exists
    if ($isOrderCompleted) {
        $stmt = $conn->prepare("SELECT id FROM feedback WHERE order_id = ? AND customer_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $orderCode, $customerId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $hasFeedback = $result !== null;
            $logMessage("Feedback check for order $orderCode: " . ($hasFeedback ? 'Feedback already submitted' : 'No feedback found'));
            $stmt->close();
        } else {
            $logMessage("Prepare failed for feedback check: " . $conn->error);
        }
    }
}

// Log order details
$logMessage("Displaying confirmation for order: $orderCode, Customer ID: $customerId");
$logMessage("Order items count: " . count($orderItems));
$logMessage("Order items: " . json_encode($orderItems));
$logMessage("Delivery method: " . ($order['delivery_method'] ?? 'N/A'));
$logMessage("Delivery address: " . json_encode($order['delivery_address'] ?? null));
$logMessage("Customer email: $customerEmail");
$logMessage("Payment details: " . ($order['payment_details'] ?? 'N/A'));

// Base URL for images
$baseUrl = 'http://localhost/Online-Fast-Food/Admin/Manage_Menu_Item/uploads/';

// Function to get base64 image for PDF
function getBase64Image($filePath, $logMessage) {
    $absolutePath = realpath($_SERVER['DOCUMENT_ROOT'] . '/Online-Fast-Food/Admin/Manage_Menu_Item/uploads/' . basename($filePath));
    if ($absolutePath && file_exists($absolutePath)) {
        $type = pathinfo($absolutePath, PATHINFO_EXTENSION);
        $data = file_get_contents($absolutePath);
        if ($data === false) {
            $logMessage("Failed to read image file: $absolutePath");
            return null;
        }
        $logMessage("Loaded image file: $absolutePath for PDF");
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    $logMessage("Image file not found: $absolutePath");
    return null;
}

// Function to check if image URL is accessible
function checkImageUrl($url, $logMessage) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $logMessage("Checked image URL: $url, HTTP code: $httpCode");
    return $httpCode === 200;
}

// Handle PDF download
if (isset($_GET['download_invoice']) && !empty($orderCode)) {
    try {
        $dompdf = new Dompdf(['enable_remote' => true]);
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Invoice Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                h1 { color: #ff4757; }
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
                <h1>Invoice #<?php echo htmlspecialchars($orderCode); ?></h1>
                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($orderCode); ?></p>
                <p><strong>Date:</strong> <?php echo date('d M Y, H:i', strtotime($order['timestamp'] ?? date('Y-m-d H:i:s'))); ?></p>
                <p><strong>Total:</strong> RM <?php echo number_format($order['amount'] ?? 0, 2); ?></p>
                <p><strong>Delivery Method:</strong> <?php echo htmlspecialchars($order['delivery_method'] ?? 'pickup'); ?></p>
                <p><strong>Delivery Address:</strong> 
                    <?php
                    $deliveryAddress = $order['delivery_address'] ?? null;
                    if ($deliveryAddress && $order['delivery_method'] === 'delivery') {
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
                <p><strong>Customer Email:</strong> <?php echo htmlspecialchars($customerEmail); ?></p>
            </div>
            <h3>Items Ordered</h3>
            <?php if (!empty($orderItems)): ?>
                <table>
                    <tr><th>Image</th><th>Item</th><th>Quantity</th><th>Price</th><th>Total</th></tr>
                    <?php foreach ($orderItems as $item): ?>
                        <?php
                        $imagePath = basename($item['photo']) ?: 'placeholder.jpg';
                        $localPath = $_SERVER['DOCUMENT_ROOT'] . '/Online-Fast-Food/Admin/Manage_Menu_Item/uploads/' . $imagePath;
                        $imageSrc = getBase64Image($imagePath, $logMessage);
                        $imageUrl = $imageSrc ?: ($baseUrl . rawurlencode($imagePath));
                        if (!$imageSrc) {
                            checkImageUrl($baseUrl . $imagePath, $logMessage);
                        }
                        $logMessage("PDF image URL for {$item['item_name']}: $imageUrl (Local path: $localPath)");
                        ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" style="max-width: 60px; height: auto;"></td>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>RM <?php echo number_format($item['price'], 2); ?></td>
                            <td>RM <?php echo number_format($item['total'] ?? ($item['quantity'] * $item['price']), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No items found for this order.</p>
            <?php endif; ?>
            <div class="footer">
                <p>Thank you for choosing Brizo Fast Food!</p>
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
        $errorMessage = "Failed to generate PDF: " . htmlspecialchars($e->getMessage());
    }
}
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
        .card-hover { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .invoice-section { background-color: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        .invoice-table th { background-color: #f3f4f6; }
        .invoice-table img { max-width: 60px; height: auto; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; border-radius: 10px;
            font-weight: 500; font-size: 14px; color: white; text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            cursor: pointer; border: none;
        }
        .btn:hover { transform: scale(1.05); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); opacity: 0.9; }
        .btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.3); }
        .btn:active { transform: scale(1); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); }
        .btn i { margin-right: 6px; font-size: 14px; }
        .btn-download { background-color: #ff4757; }
        .btn-download:hover { background-color: #e63946; }
        .btn-email { background-color: #10b981; }
        .btn-email:hover { background-color: #059669; }
        .btn-history { background-color: #8b5cf6; }
        .btn-history:hover { background-color: #7c3aed; }
        .btn-cart { background-color: #3b82f6; }
        .btn-cart:hover { background-color: #2563eb; }
        .btn-feedback { background-color: #ef4444; }
        .btn-feedback:hover { background-color: #dc2626; }
        .text-primary { color: #ff4757; }
        .text-primary:hover { color: #e63946; }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="/Online-Fast-Food/customer/menu/menu.php" class="text-primary hover:text-primary flex items-center" aria-label="Return to menu page">
                <i class="fas fa-utensils mr-2" aria-hidden="true"></i> Go Back to Menu
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (isset($_GET['email_sent'])): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $_GET['email_sent'] === 'success' ? 'bg-green-50 border-l-4 border-green-500' : 'bg-red-50 border-l-4 border-red-500'; ?>">
                <p class="<?php echo $_GET['email_sent'] === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-lg flex items-center">
                    <i class="fas <?php echo $_GET['email_sent'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo $_GET['email_sent'] === 'success' ? 'Invoice successfully sent to your email!' : 'Failed to send invoice: ' . htmlspecialchars($_GET['error'] ?? 'Please try again later.'); ?>
                </p>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500">
                <p class="text-red-700 text-lg flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $errorMessage; ?>
                </p>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Order Confirmation</h2>
            <?php if (!empty($orderCode) && !empty($order)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                    <p class="text-green-700 text-lg flex items-center">
                        <i class="fas fa-check-circle mr-2"></i> Thank you! Your order has been successfully placed.
                    </p>
                </div>

                <!-- Invoice Section -->
                <div id="invoice-section" class="invoice-section" role="region" aria-labelledby="invoice-heading">
                    <h3 id="invoice-heading" class="text-xl font-medium text-gray-700 mb-4">Invoice #<?php echo htmlspecialchars($orderCode); ?></h3>
                    <div class="bg-gray-50 p-6 rounded-lg mb-4">
                        <p class="text-gray-600"><strong>Order ID:</strong> <?php echo htmlspecialchars($orderCode); ?></p>
                        <p class="text-gray-600"><strong>Date:</strong> <?php echo date('d M Y, H:i', strtotime($order['timestamp'] ?? date('Y-m-d H:i:s'))); ?></p>
                        <p class="text-gray-600"><strong>Total:</strong> RM <?php echo number_format($order['amount'] ?? 0, 2); ?></p>
                        <p class="text-gray-600"><strong>Payment Method:</strong> 
                            <i class="fas <?php echo ($order['method'] ?? 'unknown') === 'card' ? 'fa-credit-card' : (($order['method'] ?? 'unknown') === 'online_banking' ? 'fa-university' : 'fa-wallet'); ?> mr-1"></i>
                            <?php echo ucfirst(htmlspecialchars($order['method'] ?? 'unknown')); ?> (<?php echo htmlspecialchars($order['payment_details'] ?? 'N/A'); ?>)
                        </p>
                        <p class="text-gray-600"><strong>Delivery Method:</strong> 
                            <i class="fas <?php echo ($order['delivery_method'] ?? 'pickup') === 'delivery' ? 'fa-truck' : 'fa-store'; ?> mr-1"></i>
                            <?php echo ucfirst(htmlspecialchars($order['delivery_method'] ?? 'pickup')); ?>
                        </p>
                        <p class="text-gray-600"><strong>Delivery Address:</strong> 
                            <?php
                            $deliveryAddress = $order['delivery_address'] ?? null;
                            if ($deliveryAddress && ($order['delivery_method'] ?? 'pickup') === 'delivery') {
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
                        <p class="text-gray-600"><strong>Customer Email:</strong> <?php echo htmlspecialchars($customerEmail); ?></p>
                    </div>

                    <!-- Order Items -->
                    <h3 class="text-lg font-medium text-gray-700 mb-4">Items Ordered</h3>
                    <?php if (!empty($orderItems)): ?>
                        <table class="invoice-table" role="grid" aria-label="Order items">
                            <thead>
                                <tr>
                                    <th scope="col">Image</th>
                                    <th scope="col">Item Name</th>
                                    <th scope="col">Quantity</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <?php
                                    $imagePath = basename($item['photo']) ?: 'placeholder.jpg';
                                    $imageUrl = $baseUrl . rawurlencode($imagePath);
                                    checkImageUrl($imageUrl, $logMessage);
                                    $logMessage("Web image URL for {$item['item_name']}: $imageUrl");
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" onerror="this.src='<?php echo htmlspecialchars($baseUrl . 'placeholder.jpg'); ?>'; console.error('Image failed to load: $imageUrl')" style="max-width: 60px; height: auto;">
                                        </td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>RM <?php echo number_format($item['price'], 2); ?></td>
                                        <td>RM <?php echo number_format($item['total'] ?? ($item['quantity'] * $item['price']), 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-red-600">No items found for this order.</p>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex justify-end space-x-3 flex-wrap">
                    <a href="?download_invoice=1&order_id=<?php echo urlencode($orderCode); ?>&csrf_token=<?php echo $csrfParam; ?>" class="btn btn-download mr-2">
                        <i class="fas fa-download"></i> Download Invoice
                    </a>
                    <form action="/Online-Fast-Food/payment/brizo-fast-food-payment/send_invoice.php" method="POST" class="inline-flex">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderCode); ?>">
                        <input type="hidden" name="customer_email" value="<?php echo htmlspecialchars($customerEmail); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <button type="submit" class="btn btn-email">
                            <i class="fas fa-envelope"></i> Send Invoice via Email
                        </button>
                    </form>
                    <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/payment_history.php?csrf_token=<?php echo $csrfParam; ?>" class="btn btn-history">
                        <i class="fas fa-history"></i> View Payment History
                    </a>
                    <a href="/Online-Fast-Food/customer/menu/menu.php" class="btn btn-cart">
                        <i class="fas fa-utensils"></i> Go Back to Menu
                    </a>
                    <?php if ($isOrderCompleted && !$hasFeedback): ?>
                        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/feedback.php?order_id=<?php echo urlencode($orderCode); ?>&csrf_token=<?php echo $csrfParam; ?>" class="btn btn-feedback">
                            <i class="fas fa-star"></i> Provide Feedback
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mt-8 flex justify-end space-x-3 flex-wrap">
                    <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/payment_history.php?csrf_token=<?php echo $csrfParam; ?>" class="btn btn-history">
                        <i class="fas fa-history"></i> View Payment History
                    </a>
                </div>
                <p class="text-gray-600 mt-4">Please select an order from your payment history to view details.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php
// Clear last_order session if order is valid
if (!empty($orderCode)) {
    unset($_SESSION['last_order']);
}
ob_end_flush();
?>