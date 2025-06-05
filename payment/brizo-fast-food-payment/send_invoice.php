<?php
ob_start();
session_start();
require '../../db_connect.php'; // Database connection

// Autoload Composer dependencies
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    file_put_contents(__DIR__ . '/send_invoice_errors.log', date('Y-m-d H:i:s') . ' - Composer autoloader not found at: ' . $autoloadPath . PHP_EOL, FILE_APPEND);
    die("Debug: Autoload file not found at: $autoloadPath");
}
require $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use Dotenv\Dotenv;

// Debug mode
$debug = true;
$logFile = __DIR__ . '/send_invoice_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Initialize PHPMailer after autoloader
$mail = new PHPMailer(true);

// Log POST data for debugging
$logMessage("Received POST data: " . json_encode($_POST));

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    $logMessage("Session expired for customer_id: " . ($_SESSION['customer_id'] ?? 'unknown'));
    header("Location: /Online-Fast-Food/login.php?message=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is authenticated
if (!isset($_SESSION['customer_id']) || (int)$_SESSION['customer_id'] <= 0) {
    $logMessage("User not authenticated: customer_id not set or invalid");
    header("Location: /Online-Fast-Food/login.php?message=" . urlencode("Please log in"));
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $logMessage("Invalid CSRF token for customer_id: " . $_SESSION['customer_id']);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Invalid CSRF token"));
    exit();
}

// Environment configuration
$envPath = dirname(__DIR__, 2);
try {
    $envFile = $envPath . '/.env';
    if (!file_exists($envFile)) {
        throw new Exception(".env file not found at: $envFile");
    }
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();

    $requiredVars = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME'];
    foreach ($requiredVars as $var) {
        if (empty($_ENV[$var])) {
            throw new Exception("Missing required environment variable: $var");
        }
    }
    $logMessage("Environment loaded successfully");
} catch (Exception $e) {
    $logMessage("CONFIGURATION ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("System configuration error. Please contact support."));
    exit();
}

// Order processing
$orderCode = filter_input(INPUT_POST, 'order_code', FILTER_SANITIZE_STRING) ?? '';
$customerEmail = filter_input(INPUT_POST, 'customer_email', FILTER_SANITIZE_EMAIL) ?? '';
$customerId = (int)$_SESSION['customer_id'];

// Validate input
$errors = [];
if (empty($orderCode)) {
    $errors[] = "Order code is required";
}
if (empty($customerEmail)) {
    $errors[] = "Customer email is required";
} elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}
if (!$customerId) {
    $errors[] = "User not authenticated";
}
if (!empty($errors)) {
    $logMessage("Invalid request: " . implode(", ", $errors) . " (order_code=$orderCode, customer_email=$customerEmail, customer_id=$customerId)");
    header("Location: confirmation.php?error=" . urlencode("No order selected. Please choose an order from your payment history.") . "&order_id=" . urlencode($orderCode) . "&csrf_token=" . urlencode($_SESSION['csrf_token']));
    exit();
}

// Fetch order details
try {
    $stmt = $conn->prepare("
        SELECT order_id, date AS timestamp, amount, status, method, payment_details, delivery_method, delivery_address
        FROM payment_history
        WHERE order_id = ? AND customer_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed for payment_history: " . $conn->error);
    }
    $stmt->bind_param("si", $orderCode, $customerId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $logMessage("Order not found: $orderCode for customer_id: $customerId");
        header("Location: confirmation.php?error=" . urlencode("Order not found. Please select a valid order.") . "&order_id=" . urlencode($orderCode) . "&csrf_token=" . urlencode($_SESSION['csrf_token']));
        exit();
    }

    $stmt = $conn->prepare("
        SELECT oi.item_id, oi.quantity, oi.price, m.item_name, m.photo
        FROM order_items oi
        JOIN menu_items m ON oi.item_id = m.id
        WHERE oi.order_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed for order_items: " . $conn->error);
    }
    $stmt->bind_param("s", $orderCode);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($items)) {
        $logMessage("No items found for order: $orderCode");
        header("Location: confirmation.php?error=" . urlencode("No items found for this order.") . "&order_id=" . urlencode($orderCode) . "&csrf_token=" . urlencode($_SESSION['csrf_token']));
        exit();
    }
} catch (Exception $e) {
    $logMessage("DATABASE ERROR: " . $e->getMessage());
    header("Location: confirmation.php?error=" . urlencode("Database error: " . $e->getMessage()) . "&order_id=" . urlencode($orderCode) . "&csrf_token=" . urlencode($_SESSION['csrf_token']));
    exit();
}

// Send email
try {
    $mail->SMTPDebug = $debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
    $mail->Debugoutput = function($str, $level) use ($logMessage) {
        $logMessage("PHPMailer [$level]: " . trim($str));
    };

    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'];
    $mail->Password = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)$_ENV['SMTP_PORT'];

    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress($customerEmail);
    $mail->Subject = 'Your Order Invoice #' . htmlspecialchars($orderCode);
    $mail->isHTML(true);

    // Embed logo
    $logoPath = dirname(__DIR__, 2) . '/assets/images/logo.png';
    $logoCid = '';
    if (file_exists($logoPath)) {
        $logoCid = 'logo_cid';
        $mail->addEmbeddedImage($logoPath, $logoCid);
        $logMessage("Logo embedded successfully: $logoPath");
    } else {
        $logMessage("Logo file not found: $logoPath");
    }

    // Embed item images
    $itemCids = [];
    $baseImagePath = dirname(__DIR__, 2) . '/Admin/Manage_Menu_Item/';
    foreach ($items as $item) {
        if (!empty($item['photo']) && file_exists($baseImagePath . $item['photo'])) {
            $itemCid = "item_{$item['item_id']}_cid";
            $mail->addEmbeddedImage($baseImagePath . $item['photo'], $itemCid);
            $itemCids[$item['item_id']] = $itemCid;
            $logMessage("Item image embedded successfully: " . $baseImagePath . $item['photo']);
        } else {
            $logMessage("Item image not found for item {$item['item_id']}: " . ($item['photo'] ?? 'No photo path'));
        }
    }

    $mail->Body = buildEmailContent($order, $items, $logoCid, $itemCids);
    $mail->AltBody = buildPlainTextContent($order, $items);

    $mail->send();
    $logMessage("Email sent successfully to: $customerEmail for order: $orderCode");
    header("Location: confirmation.php?email_sent=success&order_id=" . urlencode($orderCode) . "&csrf_token=" . urlencode($_SESSION['csrf_token']));
    exit();
} catch (Exception $e) {
    $logMessage("EMAIL SEND FAILED: " . $e->getMessage());
    header("Location: confirmation.php?error=" . urlencode("Failed to send email: " . $e->getMessage()) . "&order_id=" . urlencode($orderCode) . "&csrf_token=" . urlencode($_SESSION['csrf_token']));
    exit();
}

/**
 * Builds HTML email content
 */
function buildEmailContent($order, $items, $logoCid = '', $itemCids = []) {
    $orderId = htmlspecialchars($order['order_id']);
    $orderDate = htmlspecialchars($order['timestamp']);
    $orderTotal = number_format($order['amount'], 2);

    $deliveryInfo = '';
    if ($order['delivery_method'] === 'delivery' && !empty($order['delivery_address'])) {
        $addr = is_string($order['delivery_address']) ? json_decode($order['delivery_address'], true) : $order['delivery_address'];
        $addressParts = [];
        if (!empty($addr['street_address'])) $addressParts[] = htmlspecialchars($addr['street_address']);
        if (!empty($addr['city'])) $addressParts[] = htmlspecialchars($addr['city']);
        if (!empty($addr['postal_code'])) $addressParts[] = htmlspecialchars($addr['postal_code']);
        $deliveryInfo = '<p><strong>Delivery Address:</strong> ' . implode(', ', $addressParts) . '</p>';
    } else {
        $deliveryInfo = '<p><strong>Delivery Method:</strong> Pick Up</p>';
    }

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemName = htmlspecialchars($item['item_name']);
        $quantity = htmlspecialchars($item['quantity']);
        $price = number_format($item['price'], 2);
        $total = number_format($item['quantity'] * $item['price'], 2);
        $imageHtml = isset($itemCids[$item['item_id']]) ? "<td><img src='cid:{$itemCids[$item['item_id']]}' alt='{$itemName}' style='max-width: 100px;'></td>" : '<td>No image</td>';
        $itemsHtml .= "<tr>
            $imageHtml
            <td>$itemName</td>
            <td>$quantity</td>
            <td>RM $price</td>
            <td>RM $total</td>
        </tr>";
    }

    $logoHtml = !empty($logoCid) ? "<img src='cid:$logoCid' alt='Logo' style='max-width: 150px;'>" : '';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #ff4757; color: white; padding: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        img { max-width: 100px; height: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
            $logoHtml
        </div>
        <p><strong>Order ID:</strong> $orderId</p>
        <p><strong>Date:</strong> $orderDate</p>
        <p><strong>Total:</strong> RM $orderTotal</p>
        <h3>Delivery Information</h3>
        $deliveryInfo
        <h3>Order Items</h3>
        <table>
            <tr>
                <th>Image</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
            $itemsHtml
        </table>
        <p>Thank you for your order!</p>
    </div>
</body>
</html>
HTML;
}

/**
 * Builds plain text email content
 */
function buildPlainTextContent($order, $items) {
    $orderId = htmlspecialchars($order['order_id']);
    $orderDate = htmlspecialchars($order['timestamp']);
    $orderTotal = number_format($order['amount'], 2);

    $deliveryInfo = "Delivery Method: Pick Up\n";
    if ($order['delivery_method'] === 'delivery' && !empty($order['delivery_address'])) {
        $addr = is_string($order['delivery_address']) ? json_decode($order['delivery_address'], true) : $order['delivery_address'];
        $addressParts = [];
        if (!empty($addr['street_address'])) $addressParts[] = htmlspecialchars($addr['street_address']);
        if (!empty($addr['city'])) $addressParts[] = htmlspecialchars($addr['city']);
        if (!empty($addr['postal_code'])) $addressParts[] = htmlspecialchars($addr['postal_code']);
        $deliveryInfo = "Delivery Address: " . implode(', ', $addressParts) . "\n";
    }

    $itemsText = "Order Items:\n";
    foreach ($items as $item) {
        $itemName = htmlspecialchars($item['item_name']);
        $quantity = htmlspecialchars($item['quantity']);
        $price = number_format($item['price'], 2);
        $total = number_format($item['quantity'] * $item['price'], 2);
        $itemsText .= "$itemName - Quantity: $quantity, Price: RM $price, Total: RM $total\n";
    }

    return <<<TEXT
Order Confirmation

Order ID: $orderId
Date: $orderDate
Total: RM $orderTotal

Delivery Information:
$deliveryInfo

$itemsText

Thank you for your order!
TEXT;
}

ob_end_flush();
?>