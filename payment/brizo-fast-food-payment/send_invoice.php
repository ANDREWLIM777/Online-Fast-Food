<?php
session_start();
require '../db_connect.php';

// Debug mode
$debug = true;
$logFile = 'send_invoice_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    $logMessage("Session expired for customer_id: " . ($_SESSION['customer_id'] ?? 'unknown'));
    header("Location: /Online-Fast-Food/login.php?message=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Check for vendor/autoload.php
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $logMessage("Autoload file not found at: $autoloadPath");
    if ($debug) {
        die("Debug: Autoload file not found at: $autoloadPath. Run 'composer install' in the project root.");
    }
    header("Location: confirmation.php?email_sent=error&message=System+error:+Dependencies+missing");
    exit();
}
require $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Verify Dotenv class exists
if (!class_exists('Dotenv\Dotenv')) {
    $logMessage("Dotenv\Dotenv class not found. Ensure vlucas/phpdotenv is installed.");
    if ($debug) {
        die("Debug: Dotenv\Dotenv class not found. Run 'composer require vlucas/phpdotenv'.");
    }
    header("Location: confirmation.php?email_sent=error&message=System+error:+Dotenv+missing");
    exit();
}

// Load environment variables
try {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
    $envPath = dirname(__DIR__, 2) . '/.env';
    var_dump(file_exists($envPath)); // 文件是否存在
    var_dump(is_readable($envPath)); // 文件是否可读
    exit;
    $dotenv->load();
} catch (Exception $e) {
    $logMessage("Failed to load .env file: " . $e->getMessage());
    header("Location: confirmation.php?email_sent=error&message=System+configuration+error");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $logMessage("Invalid CSRF token");
    header("Location: confirmation.php?email_sent=error&message=Invalid+CSRF+token");
    // Regenerate CSRF token after failed attempt
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    exit();
}

$orderCode = filter_input(INPUT_POST, 'order_code', FILTER_SANITIZE_STRING) ?? '';
$customerEmail = filter_input(INPUT_POST, 'customer_email', FILTER_SANITIZE_EMAIL) ?? '';
$customerId = (int)($_SESSION['customer_id'] ?? 0);

// Validate inputs
if (empty($orderCode) || empty($customerEmail) || !$customerId || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $logMessage("Missing or invalid order_code, customer_email, or customer_id: order_code=$orderCode, email=$customerEmail, customer_id=$customerId");
    header("Location: confirmation.php?email_sent=error&message=Invalid+request");
    exit();
}

// Fetch order details
$stmt = $conn->prepare("
    SELECT ph.order_id, ph.date AS timestamp, ph.amount, ph.delivery_method, ph.delivery_address
    FROM payment_history ph
    WHERE ph.order_id = ? AND ph.customer_id = ?
");
if (!$stmt) {
    $logMessage("Prepare failed for payment_history: " . $conn->error);
    header("Location: confirmation.php?email_sent=error&message=Database+error");
    exit();
}
$stmt->bind_param("si", $orderCode, $customerId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $logMessage("Order not found: $orderCode for customer_id: $customerId");
    header("Location: confirmation.php?email_sent=error&message=Order+not+found");
    exit();
}

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.item_id, oi.quantity, oi.price, m.item_name
    FROM order_items oi
    JOIN menu_items m ON oi.item_id = m.id
    WHERE oi.order_id = ?
");
if (!$stmt) {
    $logMessage("Prepare failed for order items: " . $conn->error);
    header("Location: confirmation.php?email_sent=error&message=Database+error");
    exit();
}
$stmt->bind_param("s", $orderCode);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Send email
try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = $debug ? 2 : 0; // Enable verbose debug output only in debug mode
    $mail->Debugoutput = function($str, $level) use ($logMessage) {
        $logMessage("PHPMailer Debug [$level]: $str");
    };
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: '';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)getenv('SMTP_PORT') ?: 587;

    if (empty($mail->Username) || empty($mail->Password)) {
        $logMessage("SMTP credentials missing: Username=" . $mail->Username . ", Password=" . (empty($mail->Password) ? 'empty' : 'set'));
        header("Location: confirmation.php?email_sent=error&message=Email+configuration+error");
        exit();
    }

    $mail->setFrom('no-reply@brizofastfood.com', 'Brizo Fast Food Melaka');
    $mail->addAddress($customerEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Your Order Invoice #' . $orderCode;
    $body = '<h2>Order Confirmation</h2>';
    $body .= '<p><strong>Order ID:</strong> ' . htmlspecialchars($orderCode) . '</p>';
    $body .= '<p><strong>Date:</strong> ' . date('d M Y, H:i', strtotime($order['timestamp'])) . '</p>';
    $body .= '<p><strong>Total:</strong> RM ' . number_format($order['amount'], 2) . '</p>';
    $body .= '<h3>Items:</h3><ul>';
    foreach ($items as $item) {
        $body .= '<li>' . htmlspecialchars($item['item_name']) . ' x' . $item['quantity'] . ' - RM ' . number_format($item['quantity'] * $item['price'], 2) . '</li>';
    }
    $body .= '</ul>';
    if ($order['delivery_method'] === 'delivery' && $order['delivery_address']) {
        $addr = json_decode($order['delivery_address'], true);
        $addressParts = [];
        if (!empty($addr['street_address'])) $addressParts[] = htmlspecialchars($addr['street_address']);
        if (!empty($addr['city'])) $addressParts[] = htmlspecialchars($addr['city']);
        if (!empty($addr['postal_code'])) $addressParts[] = htmlspecialchars($addr['postal_code']);
        $body .= '<p><strong>Delivery Address:</strong> ' . implode(', ', $addressParts) . '</p>';
    } else {
        $body .= '<p><strong>Delivery Method:</strong> Pick Up</p>';
    }
    $mail->Body = $body;

    $mail->send();
    $logMessage("Invoice sent for order: $orderCode to $customerEmail");
    // Regenerate CSRF token after successful action
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: confirmation.php?email_sent=success");
} catch (Exception $e) {
    $logMessage("Failed to send invoice for order: $orderCode - " . $mail->ErrorInfo);
    header("Location: confirmation.php?email_sent=error&message=" . urlencode("Failed to send invoice: " . $e->getMessage()));
}
exit();
?>