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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $logMessage("Invalid CSRF token for send_invoice");
    header("Location: confirmation.php?email_sent=error&message=Invalid CSRF token");
    exit();
}

// Validate input
$orderCode = $_POST['order_code'] ?? '';
$customerEmail = $_POST['customer_email'] ?? '';
if (empty($orderCode) || empty($customerEmail)) {
    $logMessage("Missing order_code or customer_email");
    header("Location: confirmation.php?email_sent=error&message=Missing order details");
    exit();
}

// Fetch order details from database
$stmt = $conn->prepare("
    SELECT o.order_id, o.total, o.created_at, o.customer_id, o.items
    FROM orders o
    WHERE o.order_id = ?
");
$stmt->bind_param("s", $orderCode);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    $logMessage("Order not found: $orderCode");
    header("Location: confirmation.php?email_sent=error&message=Order not found");
    exit();
}

// Fetch delivery details from session
$deliveryMethod = $_SESSION['last_order']['delivery_method'] ?? 'pickup';
$deliveryAddress = $_SESSION['last_order']['delivery_address'] ?? null;
if (is_string($deliveryAddress)) {
    $deliveryAddress = json_decode($deliveryAddress, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logMessage("JSON decode error for delivery_address: " . json_last_error_msg());
        $deliveryAddress = null;
    }
} elseif (!is_array($deliveryAddress)) {
    $deliveryAddress = null;
}
$logMessage("Session delivery_method: $deliveryMethod, delivery_address: " . json_encode($deliveryAddress));

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
    $itemsJson = $order['items'];
    if ($itemsJson) {
        $orderItems = json_decode($itemsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logMessage("JSON decode error for orders.items: " . json_last_error_msg());
            $orderItems = [];
        }
    }
}

// Fallback to session items if still empty
if (empty($orderItems) && !empty($_SESSION['last_order']['items'])) {
    $logMessage("No order items in database, using session items for order: $orderCode");
    $orderItems = $_SESSION['last_order']['items'];
}

$logMessage("Order items for $orderCode: " . json_encode($orderItems));

// Generate PDF using Dompdf
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
            <p><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($order['created_at'])) ?></p>
            <p><strong>Total:</strong> RM <?= number_format($order['total'], 2) ?></p>
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
        </div>
        <h3>Items Ordered</h3>
        <?php if (!empty($orderItems)): ?>
            <table>
                <tr><th>Image</th><th>Item</th><th>Quantity</th><th>Price</th><th>Total</th></tr>
                <?php foreach ($orderItems as $item): ?>
                    <?php
                    $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/Online-Fast-Food/Admin/Manage_Menu_Item/' . ($item['photo'] ?: 'placeholder.jpg');
                    if (!file_exists($imagePath)) {
                        $logMessage("Image not found for item {$item['item_name']}: $imagePath");
                        $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/Online-Fast-Food/Admin/Manage_Menu_Item/placeholder.jpg';
                    }
                    ?>
                    <tr>
                        <td><img src="file://<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>"></td>
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
    $pdfOutput = $dompdf->output();
    $pdfFile = "invoice_$orderCode.pdf";
    file_put_contents($pdfFile, $pdfOutput);
} catch (Exception $e) {
    $logMessage("PDF generation failed for order: $orderCode - " . $e->getMessage());
    if ($debug) {
        die("PDF Error: " . htmlspecialchars($e->getMessage()));
    }
    header("Location: confirmation.php?email_sent=error&message=PDF generation failed");
    exit();
}

// Send email
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'your_email@gmail.com'; // Replace with your Gmail address
    $mail->Password = 'your_app_password'; // Replace with your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('no-reply@brizofastfood.com', 'Brizo Fast Food');
    $mail->addAddress($customerEmail);
    $mail->addAttachment($pdfFile);

    $mail->isHTML(true);
    $mail->Subject = 'Your Invoice - Brizo Fast Food Melaka';
    $mail->Body = "Dear Customer,<br><br>Thank you for your order! Please find your invoice attached.<br>Order ID: " . htmlspecialchars($orderCode) . "<br><br>Best regards,<br>Brizo Fast Food Melaka";

    $mail->send();
    $logMessage("Invoice email sent successfully for order: $orderCode to $customerEmail");
    unlink($pdfFile);
    header("Location: confirmation.php?email_sent=success");
} catch (Exception $e) {
    $logMessage("Invoice email failed for order: $orderCode - " . $mail->ErrorInfo);
    unlink($pdfFile);
    if ($debug) {
        die("Email Error: " . htmlspecialchars($mail->ErrorInfo));
    }
    header("Location: confirmation.php?email_sent=error&message=" . urlencode($mail->ErrorInfo));
}
exit();
?>