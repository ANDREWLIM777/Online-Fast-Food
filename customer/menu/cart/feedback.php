<?php
session_start();
require '../db_connect.php';

// Debug mode
$debug = true;
$logFile = 'confirmation_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

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

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: ../../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];
$logMessage("Customer ID from session: $customerId");

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $logMessage("Invalid CSRF token for feedback submission");
        header("Location: feedback.php?error=Invalid CSRF token");
        exit();
    }

    $orderId = $_POST['order_id'] ?? '';
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comments = $_POST['comments'] ?? '';
    $evidence = $_FILES['evidence'] ?? null;

    // Validate input
    if (empty($orderId) || $rating < 1 || $rating > 5) {
        $logMessage("Invalid input: order_id=$orderId, rating=$rating");
        header("Location: feedback.php?error=Invalid order or rating&selected_order=$orderId");
        exit();
    }

    // Verify order belongs to customer and is completed
    $stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ? AND status = 'completed'");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $logMessage("Order not found or not completed: $orderId for customer: $customerId");
        header("Location: feedback.php?error=Order not found or not completed&selected_order=$orderId");
        exit();
    }
    $stmt->close();

    // Check for existing feedback
    $stmt = $conn->prepare("SELECT id FROM feedback WHERE order_id = ? AND customer_id = ?");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $logMessage("Feedback already exists for order: $orderId");
        header("Location: feedback.php?error=Feedback already submitted for this order&selected_order=$orderId");
        exit();
    }
    $stmt->close();

    // Handle file upload
    $evidencePath = null;
    if ($evidence && $evidence['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if (!in_array($evidence['type'], $allowedTypes) || $evidence['size'] > $maxSize) {
            $logMessage("Invalid file type or size for order: $orderId");
            header("Location: feedback.php?error=Invalid file type or size (JPG/PNG, max 5MB)&selected_order=$orderId");
            exit();
        }

        $uploadDir = 'Uploads/Feedback/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $evidencePath = $uploadDir . uniqid() . '_' . basename($evidence['name']);
        if (!move_uploaded_file($evidence['tmp_name'], $evidencePath)) {
            $logMessage("File upload failed for order: $orderId");
            header("Location: feedback.php?error=File upload failed&selected_order=$orderId");
            exit();
        }
    }

    // Save feedback
    $stmt = $conn->prepare("
        INSERT INTO feedback (order_id, customer_id, rating, comments, status, evidence_path, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())
    ");
    $stmt->bind_param("siiss", $orderId, $customerId, $rating, $comments, $evidencePath);
    if ($stmt->execute()) {
        $logMessage("Feedback saved for order: $orderId, rating: $rating");
    } else {
        $logMessage("Feedback save failed for order: $orderId - " . $stmt->error);
        header("Location: feedback.php?error=Failed to save feedback&selected_order=$orderId");
        exit();
    }
    $stmt->close();

    // Send email to admin
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
        $mail->addAddress('admin@brizofastfood.com');
        if ($evidencePath) {
            $mail->addAttachment($evidencePath);
        }

        $mail->isHTML(true);
        $mail->Subject = "New Feedback Received for Order $orderId";
        $mail->Body = "
            <h2>New Feedback Received</h2>
            <p><strong>Order ID:</strong> " . htmlspecialchars($orderId) . "</p>
            <p><strong>Customer ID:</strong> $customerId</p>
            <p><strong>Rating:</strong> $rating / 5</p>
            <p><strong>Comments:</strong> " . (empty($comments) ? 'None' : htmlspecialchars($comments)) . "</p>
            <p><strong>Evidence:</strong> " . ($evidencePath ? 'Attached' : 'None') . "</p>
        ";

        $mail->send();
        $logMessage("Feedback email sent for order: $orderId");
    } catch (Exception $e) {
        $logMessage("Feedback email failed for order: $orderId - " . $mail->ErrorInfo);
    }

    header("Location: feedback.php?success=Feedback submitted successfully&selected_order=$orderId");
    exit();
}

// Fetch all completed orders for dropdown
$stmt = $conn->prepare("
    SELECT order_id, created_at
    FROM orders
    WHERE customer_id = ? AND status = 'completed'
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$logMessage("Fetched " . count($orders) . " completed orders for customer_id: $customerId");
$stmt->close();

// Get selected order
$selectedOrderId = $_POST['selected_order'] ?? ($_GET['selected_order'] ?? '');
$selectedOrder = null;
if ($selectedOrderId) {
    $stmt = $conn->prepare("
        SELECT order_id, total, created_at, items
        FROM orders
        WHERE customer_id = ? AND order_id = ? AND status = 'completed'
    ");
    $stmt->bind_param("is", $customerId, $selectedOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedOrder = $result->fetch_assoc();
    $stmt->close();
    $logMessage("Selected order: " . ($selectedOrder ? $selectedOrderId : 'None'));
}

// Fetch existing feedback for selected order
$feedback = null;
if ($selectedOrderId) {
    $stmt = $conn->prepare("
        SELECT order_id, rating, comments, evidence_path
        FROM feedback
        WHERE customer_id = ? AND order_id = ?
    ");
    $stmt->bind_param("is", $customerId, $selectedOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $feedback = $result->fetch_assoc();
    $stmt->close();
    $logMessage("Fetched feedback for order $selectedOrderId: " . ($feedback ? 'Found' : 'Not found'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Brizo Fast Food Melaka</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn-green { background-color: #10b981; color: white; }
        .btn-green:hover { background-color: #059669; }
        .star-rating input { display: none; }
        .star-rating label { cursor: pointer; color: #d1d5db; font-size: 1.5rem; }
        .star-rating input:checked ~ label { color: #f59e0b; }
        .star-rating label:hover, .star-rating label:hover ~ label { color: #f59e0b; }
        .order-table { width: 100%; border-collapse: collapse; }
        .order-table th, .order-table td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        .order-table th { background-color: #f3f4f6; }
        .order-table img { max-width: 60px; height: auto; }
        select { width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px; }
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
        <?php if (isset($_GET['success'])): ?>
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500">
                <p class="text-green-700 text-lg flex items-center">
                    <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['success']) ?>
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
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Your Feedback</h2>
            <p class="text-gray-600 mb-4">Select an order to provide feedback.</p>
            <p class="text-gray-600 mb-4">Your Customer ID: <?= htmlspecialchars($customerId) ?></p>

            <!-- Order selection form -->
            <form action="feedback.php" method="POST" class="mb-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <label for="selected_order" class="block text-gray-700 font-medium mb-2">Select Order</label>
                <select name="selected_order" id="selected_order" onchange="this.form.submit()">
                    <option value="">-- Choose an order --</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?= htmlspecialchars($order['order_id']) ?>" <?= $selectedOrderId === $order['order_id'] ? 'selected' : '' ?>>
                            Order #<?= htmlspecialchars($order['order_id']) ?> (<?= date('d M Y, H:i', strtotime($order['created_at'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if (empty($orders)): ?>
                <p class="text-gray-600">No completed orders found. Only orders marked as 'completed' are eligible for feedback.</p>
            <?php elseif ($selectedOrder): ?>
                <?php
                $orderId = $selectedOrder['order_id'];
                $items = $selectedOrder['items'] ? json_decode($selectedOrder['items'], true) : [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $logMessage("JSON decode error for items in order: $orderId - " . json_last_error_msg());
                    $items = [];
                }
                $hasFeedback = !empty($feedback);
                ?>
                <div class="mb-8 border-b pb-6">
                    <h3 class="text-xl font-medium text-gray-700 mb-4">Order #<?= htmlspecialchars($orderId) ?></h3>
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <p class="text-gray-600"><strong>Order ID:</strong> <?= htmlspecialchars($orderId) ?></p>
                        <p class="text-gray-600"><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($selectedOrder['created_at'])) ?></p>
                        <p class="text-gray-600"><strong>Total:</strong> RM <?= number_format($selectedOrder['total'], 2) ?></p>
                        <h4 class="text-gray-700 font-medium mt-4">Items:</h4>
                        <?php if (!empty($items)): ?>
                            <table class="order-table mt-2">
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
                                    <?php foreach ($items as $item): ?>
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
                            <p class="text-red-600">No items found.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasFeedback): ?>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="text-lg font-medium text-green-700 mb-2">Your Feedback</h4>
                            <p class="text-gray-600"><strong>Rating:</strong> 
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $feedback['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </p>
                            <p class="text-gray-600"><strong>Comments:</strong> <?= htmlspecialchars($feedback['comments'] ?: 'None') ?></p>
                            <p class="text-gray-600"><strong>Evidence:</strong> 
                                <?php if ($feedback['evidence_path']): ?>
                                    <a href="<?= htmlspecialchars($feedback['evidence_path']) ?>" target="_blank">View Image</a>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <form action="feedback.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="submit_feedback" value="1">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Rating</label>
                                <div class="star-rating flex flex-row-reverse">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?= $i ?>_<?= $orderId ?>" name="rating" value="<?= $i ?>" required>
                                        <label for="star<?= $i ?>_<?= $orderId ?>" class="fas fa-star mx-1"></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <label for="comments_<?= $orderId ?>" class="block text-gray-700 font-medium mb-2">Comments</label>
                                <textarea id="comments_<?= $orderId ?>" name="comments" rows="4" class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Share your thoughts..."></textarea>
                            </div>
                            <div>
                                <label for="evidence_<?= $orderId ?>" class="block text-gray-700 font-medium mb-2">Upload Image (JPG/PNG, max 5MB)</label>
                                <input type="file" id="evidence_<?= $orderId ?>" name="evidence" accept="image/jpeg,image/png" class="w-full border rounded-lg p-2">
                            </div>
                            <button type="submit" class="px-4 py-2 btn-green rounded-lg flex items-center">
                                <i class="fas fa-star mr-2"></i> Submit Feedback
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>