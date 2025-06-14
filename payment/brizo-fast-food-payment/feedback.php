<?php
ob_start();
session_start();
require '../db_connect.php';

// Debug mode
$debug = true;
$logFile = 'feedback_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    $logMessage("Session expired for customer_id: " . ($_SESSION['customer_id'] ?? 'unknown') . " at " . date('Y-m-d H:i:s', time()));
    header("Location: /Online-Fast-Food/customer/login.php?message=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Check database connection
if ($conn->connect_error) {
    $logMessage("Database connection failed: " . $conn->connect_error . " at " . date('Y-m-d H:i:s', time()));
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database connection failed."));
    exit();
}

// Check for vendor/autoload.php
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $logMessage("Autoload file not found at: $autoloadPath at " . date('Y-m-d H:i:s', time()));
    if ($debug) {
        die("Debug: Autoload file not found at: $autoloadPath");
    }
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Required dependencies missing"));
    exit();
}
require $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session at " . date('Y-m-d H:i:s', time()));
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

$customerId = (int)$_SESSION['customer_id'];
$logMessage("Customer ID: $customerId at " . date('Y-m-d H:i:s', time()));

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$csrfParam = urlencode($csrfToken);

// Validate CSRF token
if (isset($_GET['csrf_token']) && $_GET['csrf_token'] !== $csrfToken) {
    $logMessage("Invalid CSRF token for order_id: " . ($_GET['order_id'] ?? 'unknown') . " at " . date('Y-m-d H:i:s', time()));
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Invalid CSRF token"));
    exit();
}

// Fetch admin email
$adminEmail = 'admin@brizofastfood.com';
$stmt = $conn->prepare("SELECT email FROM admin WHERE role IN ('admin', 'superadmin') LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $adminEmail = $result['email'];
    }
    $stmt->close();
}
$logMessage("Admin email for notifications: " . htmlspecialchars($adminEmail) . " at " . date('Y-m-d H:i:s', time()));

// Fetch completed orders
$completedOrders = [];
$stmt = $conn->prepare("
    SELECT order_id, total, created_at
    FROM orders
    WHERE customer_id = ? AND LOWER(status) = 'completed'
    ORDER BY created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $completedOrders[] = $row;
    }
    $stmt->close();
    $logMessage("Fetched " . count($completedOrders) . " completed orders for customer_id: $customerId at " . date('Y-m-d H:i:s', time()));
} else {
    $logMessage("Prepare failed for completed orders: " . $conn->error . " at " . date('Y-m-d H:i:s', time()));
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $logMessage("Invalid CSRF token for feedback submission at " . date('Y-m-d H:i:s', time()));
        header("Location: feedback.php?error=Invalid+CSRF+token");
        exit();
    }

    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_SANITIZE_STRING) ?? '';
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING) ?? '';
    $evidence = $_FILES['evidence'] ?? null;

    // Validate input
    if (empty($orderId) || $rating < 1 || $rating > 5) {
        $logMessage("Invalid input: order_id=$orderId, rating=$rating at " . date('Y-m-d H:i:s', time()));
        header("Location: feedback.php?error=Invalid+order+or+rating&order_id=" . urlencode($orderId));
        exit();
    }

    // Verify order
    $stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ? AND LOWER(status) = 'completed'");
    if (!$stmt) {
        $logMessage("Prepare failed for order validation: " . $conn->error . " at " . date('Y-m-d H:i:s', time()));
        header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
        exit();
    }
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt = $conn->prepare("SELECT order_id, customer_id, status FROM orders WHERE order_id = ?");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $orderDetails = $stmt->get_result()->fetch_assoc();
        $logMessage("Order validation failed: order_id=$orderId, customer_id=$customerId, found=" . json_encode($orderDetails) . " at " . date('Y-m-d H:i:s', time()));
        $stmt->close();
        header("Location: feedback.php?error=Order+not+found+or+not+completed&order_id=" . urlencode($orderId));
        exit();
    }
    $stmt->close();

    // Check for existing feedback
    $stmt = $conn->prepare("SELECT id FROM feedback WHERE order_id = ? AND customer_id = ?");
    if (!$stmt) {
        $logMessage("Prepare failed for feedback check: " . $conn->error . " at " . date('Y-m-d H:i:s', time()));
        header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
        exit();
    }
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $logMessage("Feedback already exists for order: $orderId at " . date('Y-m-d H:i:s', time()));
        header("Location: feedback.php?error=Feedback+already+submitted+for+this+order&order_id=" . urlencode($orderId));
        exit();
    }
    $stmt->close();

    // Handle file upload
    $evidencePath = null;
    if ($evidence && $evidence['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if (!in_array($evidence['type'], $allowedTypes) || $evidence['size'] > $maxSize) {
            $logMessage("Invalid file type or size for order: $orderId at " . date('Y-m-d H:i:s', time()));
            header("Location: feedback.php?error=Invalid+file+type+or+size+(JPG/PNG,+max+5MB)&order_id=" . urlencode($orderId));
            exit();
        }

        if (!getimagesize($evidence['tmp_name'])) {
            $logMessage("Invalid image content for order: $orderId at " . date('Y-m-d H:i:s', time()));
            header("Location: feedback.php?error=Invalid+image+file&order_id=" . urlencode($orderId));
            exit();
        }

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Online-Fast-Food/Uploads/Feedback/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $evidencePath = $uploadDir . uniqid() . '_' . basename($evidence['name']);
        if (!move_uploaded_file($evidence['tmp_name'], $evidencePath)) {
            $logMessage("File upload failed for order: $orderId at " . date('Y-m-d H:i:s', time()));
            header("Location: feedback.php?error=File+upload+failed&order_id=" . urlencode($orderId));
            exit();
        }
        $evidencePath = '/Online-Fast-Food/Uploads/Feedback/' . basename($evidencePath);
    }

    // Save feedback
    $stmt = $conn->prepare("
        INSERT INTO feedback (order_id, customer_id, rating, comments, status, evidence_path, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())
    ");
    if (!$stmt) {
        $logMessage("Prepare failed for feedback insert: " . $conn->error . " at " . date('Y-m-d H:i:s', time()));
        header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
        exit();
    }
    $stmt->bind_param("siiss", $orderId, $customerId, $rating, $comments, $evidencePath);
    if ($stmt->execute()) {
        $logMessage("Feedback saved for order: $orderId, rating: $rating at " . date('Y-m-d H:i:s', time()));
    } else {
        $logMessage("Feedback save failed for order: $orderId - " . $stmt->error . " at " . date('Y-m-d H:i:s', time()));
        header("Location: feedback.php?error=Failed+to+save+feedback&order_id=" . urlencode($orderId));
        exit();
    }
    $stmt->close();

    // Send email to admin
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME') ?: 'your_email@gmail.com';
        $mail->Password = getenv('SMTP_PASSWORD') ?: 'your_app_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = getenv('SMTP_PORT') ?: 587;

        $mail->setFrom('no-reply@brizofastfood.com', 'Brizo Fast Food');
        $mail->addAddress($adminEmail);
        if ($evidencePath) {
            $mail->addAttachment($_SERVER['DOCUMENT_ROOT'] . $evidencePath);
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
        $logMessage("Feedback email sent for order: $orderId to $adminEmail at " . date('Y-m-d H:i:s', time()));
    } catch (Exception $e) {
        $logMessage("Feedback email failed for order: $orderId - " . $mail->ErrorInfo . " at " . date('Y-m-d H:i:s', time()));
    }

    header("Location: feedback.php?success=Feedback+submitted+successfully");
    exit();
}

// Get selected order_id from GET
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING) ?? '';
$selectedOrder = null;
$feedback = null;
$items = [];

if ($orderId) {
    // Validate order
    $stmt = $conn->prepare("
        SELECT order_id, total, created_at, customer_id, status
        FROM orders
        WHERE customer_id = ? AND order_id = ? AND LOWER(status) = 'completed'
    ");
    if (!$stmt) {
        $logMessage("Prepare failed for selected order: " . $conn->error . " at " . date('Y-m-d H:i:s', time()));
        header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
        exit();
    }
    $stmt->bind_param("is", $customerId, $orderId);
    $stmt->execute();
    $selectedOrder = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $logMessage("Selected order: order_id=$orderId, found=" . ($selectedOrder ? json_encode($selectedOrder) : 'None') . " at " . date('Y-m-d H:i:s', time()));

    // Fetch existing feedback
    if ($selectedOrder) {
        $stmt = $conn->prepare("
            SELECT order_id, rating, comments, evidence_path
            FROM feedback
            WHERE customer_id = ? AND order_id = ?
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for feedback fetch: " . $conn->error . " at " . date('Y-m-d H:i:s', time()));
            header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
            exit();
        }
        $stmt->bind_param("is", $customerId, $orderId);
        $stmt->execute();
        $feedback = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $logMessage("Fetched feedback for order $orderId: " . ($feedback ? 'Found' : 'Not found') . " at " . date('Y-m-d H:i:s', time()));

        // Fetch items
        $stmt = $conn->prepare("
            SELECT oi.item_id, oi.quantity, oi.price, m.item_name, m.photo
            FROM order_items oi
            JOIN menu_items m ON oi.item_id = m.id
            WHERE oi.order_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("s", $orderId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// Base URL for images
$baseUrl = 'http://localhost/Online-Fast-Food/Admin/Manage_Menu_Item/';
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
        .btn-submit { background-color: #ff4757; }
        .btn-submit:hover { background-color: #e63946; }
        .btn-history { background-color: #ff4757; }
        .btn-history:hover { background-color: #e63946; }
        .text-primary { color: #ff4757; }
        .text-primary:hover { color: #e63946; }
        .star-filled { color: #f59e0b; }
        .star-rating input { display: none; }
        .star-rating label { cursor: pointer; color: #d1d5db; font-size: 1.2rem; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #f59e0b; }
        .feedback-section { display: none; }
        .feedback-section.active { display: block; }
        select {
            appearance: none;
            background-color: #fff;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            width: 100%;
        }
        select:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.2);
        }
        select option:checked {
            background-color: #4B5563;
            color: white;
        }
    </style>
    <script>
        function showFeedback(orderId) {
            const sections = document.querySelectorAll('.feedback-section');
            sections.forEach(section => {
                section.classList.remove('active');
                if (section.id === 'feedback-' + orderId) {
                    section.classList.add('active');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const orderSelect = document.getElementById('orderSelect');
            if (orderSelect) {
                orderSelect.addEventListener('change', (e) => {
                    const orderId = e.target.value;
                    showFeedback(orderId);
                    if (orderId) {
                        window.history.pushState({}, '', `?order_id=${encodeURIComponent(orderId)}`);
                    } else {
                        window.history.pushState({}, '', window.location.pathname);
                    }
                });

                const urlParams = new URLSearchParams(window.location.search);
                const orderId = urlParams.get('order_id');
                if (orderId && orderSelect.querySelector(`option[value="${orderId}"]`)) {
                    orderSelect.value = orderId;
                    showFeedback(orderId);
                }
            }
        });
    </script>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="http://localhost/Online-Fast-Food/customer/menu/menu.php" class="text-primary hover:text-primary flex items-center" aria-label="Return to menu page">
                <i class="fas fa-utensils mr-2"></i> Back to Menu
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (isset($_GET['success'])): ?>
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500">
                <p class="text-green-700 text-lg flex items-center"><i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['success']) ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500">
                <p class="text-red-700 text-lg flex items-center"><i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Provide Feedback</h2>
            <p class="text-gray-600 mb-4">Customer ID: <?= htmlspecialchars($customerId) ?></p>

            <!-- Select an Order -->
            <div class="mb-8" role="region" aria-labelledby="orders-heading">
                <h3 id="orders-heading" class="text-xl font-medium text-gray-700 mb-4">Select an Order</h3>
                <?php if (empty($completedOrders)): ?>
                    <p class="text-gray-600">No completed orders available for feedback.</p>
                <?php else: ?>
                    <div>
                        <label for="orderSelect" class="block text-gray-700 font-medium mb-2">Select an Order</label>
                        <select id="orderSelect" name="order_id" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Select an order">
                            <option value="">Select an Order</option>
                            <?php foreach ($completedOrders as $order): ?>
                                <option value="<?= htmlspecialchars($order['order_id']) ?>" <?= $orderId === $order['order_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($order['order_id']) ?> - RM <?= number_format($order['total'], 2) ?> (<?= date('d M Y, H:i:s', strtotime($order['created_at'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Feedback Forms -->
            <?php foreach ($completedOrders as $order): ?>
                <?php
                $orderId = $order['order_id'];
                $stmt = $conn->prepare("
                    SELECT order_id, total, created_at, customer_id, status
                    FROM orders
                    WHERE customer_id = ? AND order_id = ? AND LOWER(status) = 'completed'
                ");
                $stmt->bind_param("is", $customerId, $orderId);
                $stmt->execute();
                $selectedOrder = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $conn->prepare("
                    SELECT order_id, rating, comments, evidence_path
                    FROM feedback
                    WHERE customer_id = ? AND order_id = ?
                ");
                $stmt->bind_param("is", $customerId, $orderId);
                $stmt->execute();
                $feedback = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $conn->prepare("
                    SELECT oi.item_id, oi.quantity, oi.price, m.item_name, m.photo
                    FROM order_items oi
                    JOIN menu_items m ON oi.item_id = m.id
                    WHERE oi.order_id = ?
                ");
                $stmt->bind_param("s", $orderId);
                $stmt->execute();
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                ?>
                <div id="feedback-<?= htmlspecialchars($orderId) ?>" class="feedback-section mb-8" role="region" aria-labelledby="order-heading-<?= htmlspecialchars($orderId) ?>">
                    <h3 id="order-heading-<?= htmlspecialchars($orderId) ?>" class="text-xl font-medium text-gray-700 mb-4">Order #<?= htmlspecialchars($orderId) ?></h3>
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <p class="text-gray-600"><strong>Order ID:</strong> <?= htmlspecialchars($orderId) ?></p>
                        <p class="text-gray-600"><strong>Date:</strong> <?= date('d M Y, H:i:s', strtotime($selectedOrder['created_at'])) ?></p>
                        <p class="text-gray-600"><strong>Total:</strong> RM <?= number_format($selectedOrder['total'], 2) ?></p>
                        <h4 class="text-gray-700 font-medium mt-4">Items:</h4>
                        <?php if (!empty($items)): ?>
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
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><img src="<?= htmlspecialchars($baseUrl . ($item['photo'] ?: 'placeholder.jpg')) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" onerror="this.src='<?= htmlspecialchars($baseUrl . 'placeholder.jpg') ?>'" style="max-width: 60px; height: auto;"></td>
                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td>RM <?= number_format($item['price'], 2) ?></td>
                                            <td>RM <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-gray-600">No items found.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($feedback): ?>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="text-lg font-medium text-green-700 mb-2">Your Feedback</h4>
                            <p class="text-gray-600"><strong>Rating:</strong>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $feedback['rating'] ? 'star-filled' : 'text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </p>
                            <p class="text-gray-600"><strong>Comments:</strong> <?= htmlspecialchars($feedback['comments'] ?: 'None') ?></p>
                            <p class="text-gray-600"><strong>Evidence:</strong>
                                <?php if ($feedback['evidence_path']): ?>
                                    <a href="<?= htmlspecialchars($feedback['evidence_path']) ?>" target="_blank" class="text-primary hover:text-primary">View Image</a>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <form action="feedback.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <div>
                                <label for="rating-<?= htmlspecialchars($orderId) ?>" class="block text-gray-700 font-medium mb-2">Rating</label>
                                <div class="star-rating flex flex-row-reverse justify-end">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?= $i ?>-<?= htmlspecialchars($orderId) ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?> required>
                                        <label for="star<?= $i ?>-<?= htmlspecialchars($orderId) ?>" class="fas fa-star mx-1"></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <label for="comments-<?= htmlspecialchars($orderId) ?>" class="block text-gray-700 font-medium mb-2">Comments</label>
                                <textarea id="comments-<?= htmlspecialchars($orderId) ?>" name="comments" rows="4" class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Share your feedback..."></textarea>
                            </div>
                            <div>
                                <label for="evidence-<?= htmlspecialchars($orderId) ?>" class="block text-gray-700 font-medium mb-2">Upload Evidence (JPG/PNG, max 5MB)</label>
                                <input type="file" id="evidence-<?= htmlspecialchars($orderId) ?>" name="evidence" accept="image/jpeg,image/png" class="w-full border rounded-lg p-2">
                            </div>
                            <button type="submit" name="submit_feedback" class="btn btn-submit">
                                <i class="fas fa-paper-plane"></i> Submit
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Navigation -->
            <div class="mt-8 flex justify-end space-x-3 flex-wrap">
                <a href="feedback_history.php?csrf_token=<?= $csrfParam ?>" class="btn btn-history">
                    <i class="fas fa-history"></i> Feedback History
                </a>
                <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/payment_history.php?csrf_token=<?= $csrfParam ?>" class="btn btn-history">
                    <i class="fas fa-history"></i> Payment History
                </a>
            </div>
        </div>
    </main>
</body>
</html>
<?php
ob_end_flush();
?>