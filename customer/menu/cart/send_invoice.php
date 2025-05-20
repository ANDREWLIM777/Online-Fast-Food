<?php
session_start();
require '../db_connect.php';

$debug = true;
$logFile = 'confirmation_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

$autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $logMessage("Autoload file not found at: $autoloadPath");
    die("System error: Required dependencies are missing.");
}
require $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;

// Validate session and CSRF
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: ../../login.php");
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $logMessage("Invalid CSRF token");
    header("Location: confirmation.php?email_sent=failed&message=Invalid CSRF token");
    exit();
}

$customerId = $_SESSION['customer_id'];
$orderCode = $_POST['order_code'] ?? '';
$customerEmail = $_POST['customer_email'] ?? '';

if (empty($orderCode) || empty($customerEmail)) {
    $logMessage("Missing order_code or customer_email");
    header("Location: confirmation.php?email_sent=failed&message=Missing order or email");
    exit();
}

// Fetch order details from orders
$stmt = $conn->prepare("
    SELECT order_id, total, created_at, items
    FROM orders
    WHERE order_id = ? AND customer_id = ?
");
$stmt->bind_param("si", $orderCode, $customerId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    $logMessage("Order not found: $orderCode for customer_id: $customerId");
    header("Location: confirmation.php?email_sent=failed&message=Order not found");
    exit();
}

$amount = $order['total'];
$timestamp = $order['created_at'];
$orderItems = $order['items'] ? json_decode($order['items'], true) : [];

// Fetch delivery_method and delivery_address from payment_history
$deliveryMethod = 'pickup';
$deliveryAddress = null;
$stmt = $conn->prepare("
    SELECT delivery_method, delivery_address
    FROM payment_history
    WHERE order_id = ? AND customer_id = ?
");
$stmt->bind_param("si", $orderCode, $customerId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $deliveryMethod = $row['delivery_method'] ?? 'pickup';
    $deliveryAddress = $row['delivery_address'] ? json_decode($row['delivery_address'], true) : null;
}
$stmt->close();

// Generate PDF
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
    $pdfContent = $dompdf->output();
    $pdfPath = __DIR__ . "/Uploads/Invoices/Invoice_$orderCode.pdf";
    if (!is_dir(dirname($pdfPath))) {
        mkdir(dirname($pdfPath), 0777, true);
    }
    file_put_contents($pdfPath, $pdfContent);
    $logMessage("Generated PDF for email: $pdfPath");

    // Send email
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 2; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) use ($logMessage) {
            $logMessage("SMTP Debug level $level: $str");
        };
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'andrew@gmail.com'; // Your Gmail address
        $mail->Password = 'your_app_password'; // Your Gmail App Password (16-character code)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@brizofastfood.com', 'Brizo Fast Food');
        $mail->addAddress($customerEmail);
        $mail->addAttachment($pdfPath, "Invoice_$orderCode.pdf");

        $mail->isHTML(true);
        $mail->Subject = "Your Invoice for Order #$orderCode";
        $mail->Body = "
            <h2>Thank You for Your Order!</h2>
            <p>Dear Customer,</p>
            <p>Your order #$orderCode has been successfully placed. Please find the invoice attached.</p>
            <p><strong>Order Details:</strong></p>
            <ul>
                <li><strong>Order ID:</strong> " . htmlspecialchars($orderCode) . "</li>
                <li><strong>Date:</strong> " . date('d M Y, H:i', strtotime($timestamp)) . "</li>
                <li><strong>Total:</strong> RM " . number_format($amount, 2) . "</li>
            </ul>
            <p>Thank you for choosing Brizo Fast Food Melaka!</p>
        ";

        $mail->send();
        $logMessage("Invoice email sent for order: $orderCode to $customerEmail");
        header("Location: confirmation.php?email_sent=success");
    } catch (Exception $e) {
        $logMessage("Invoice email failed for order: $orderCode - " . $mail->ErrorInfo);
        header("Location: confirmation.php?email_sent=failed&message=" . urlencode("Failed to send invoice: " . $mail->ErrorInfo));
    }
    unlink($pdfPath); // Clean up
    exit();
} catch (Exception $e) {
    $logMessage("PDF generation failed for order: $orderCode - " . $e->getMessage());
    header("Location: confirmation.php?email_sent=failed&message=" . urlencode("PDF generation failed: " . $e->getMessage()));
    exit();
}
?>