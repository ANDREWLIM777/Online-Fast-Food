<?php
declare(strict_types=1);
session_start();
session_regenerate_id(true); // Prevent session fixation

// Log every request
file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Request received: ' . $_SERVER['REQUEST_METHOD'] . PHP_EOL, FILE_APPEND);

// Check for database connection
try {
    require '../db_connect.php';
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Connection failed: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    die('Error: Database connection failed');
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - No customer_id in session, redirecting to login.php' . PHP_EOL, FILE_APPEND);
    header('Location: /Online-Fast-Food/customer/login.php');
    exit();
}

// Get order_id from GET parameter
$orderId = $_GET['order_id'] ?? '';
if (empty($orderId)) {
    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - No order_id provided, redirecting to payment_history.php' . PHP_EOL, FILE_APPEND);
    header('Location: payment_history.php');
    exit();
}

// Validate order and customer
try {
    $stmt = $conn->prepare("
        SELECT ph.order_id, ph.date, ph.amount, ph.method, ph.payment_details, ph.delivery_method, ph.delivery_address, ph.status
        FROM payment_history ph
        WHERE ph.order_id = ? AND ph.customer_id = ? AND (ph.status = 'completed' OR ph.status IS NULL)
    ");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Invalid or ineligible order_id: ' . $orderId . ' for customer_id: ' . $customerId . PHP_EOL, FILE_APPEND);
        header('Location: payment_history.php');
        exit();
    }

    // Check for existing refund request
    $stmt = $conn->prepare("
        SELECT id, status
        FROM refund_requests
        WHERE order_id = ? AND customer_id = ? AND status IN ('pending', 'approved')
    ");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingRefund = $result->fetch_assoc();
    $stmt->close();

    if ($existingRefund) {
        file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Existing refund request for order_id: ' . $orderId . PHP_EOL, FILE_APPEND);
        header('Location: payment_history.php');
        exit();
    }

    // Fetch order items
    $items = [];
    $stmt = $conn->prepare("
        SELECT oi.item_id, oi.quantity, oi.price, m.item_name, m.photo
        FROM order_items oi
        LEFT JOIN menu_items m ON oi.item_id = m.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    // Log the fetched items
    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Fetched items for order_id: ' . $orderId . ' - ' . json_encode($items) . PHP_EOL, FILE_APPEND);
} catch (Exception $e) {
    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Database error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    die('Error: Unable to fetch order details');
}

// Handle refund request submission
$errors = [];
$success = false; // Use a boolean to track success
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - POST request received' . PHP_EOL, FILE_APPEND);
    if (isset($_POST['submit_refund'])) {
        file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Submit refund button clicked' . PHP_EOL, FILE_APPEND);
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid CSRF token';
            file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Invalid CSRF token' . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - CSRF token validated' . PHP_EOL, FILE_APPEND);
            $reason = $_POST['reason'] ?? '';
            $details = trim($_POST['details'] ?? '');

            // Log the submitted data
            file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Submitted data: reason=' . $reason . ', details=' . $details . PHP_EOL, FILE_APPEND);

            // Validate inputs
            if (!in_array($reason, ['wrong-item', 'poor-quality', 'late-delivery', 'other'])) {
                $errors[] = 'Invalid refund reason';
                file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Validation failed: Invalid refund reason' . PHP_EOL, FILE_APPEND);
            }
            if (empty($details) || strlen($details) < 10) {
                $errors[] = 'Please provide detailed information (at least 10 characters)';
                file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Validation failed: Details too short' . PHP_EOL, FILE_APPEND);
            }
            if (strlen($details) > 1000) {
                $errors[] = 'Details cannot exceed 1000 characters';
                file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Validation failed: Details too long' . PHP_EOL, FILE_APPEND);
            }

            if (empty($errors)) {
                file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Validation passed, attempting to insert' . PHP_EOL, FILE_APPEND);
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO refund_requests (customer_id, order_id, reason, details, status, created_at)
                        VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $stmt->bind_param("isss", $customerId, $orderId, $reason, $details);
                    if (!$stmt->execute()) {
                        throw new Exception('Execute failed: ' . $stmt->error);
                    }
                    $success = true; // Set success to true only after successful insertion
                    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Refund request submitted for order_id: ' . $orderId . PHP_EOL, FILE_APPEND);
                    $stmt->close();
                } catch (Exception $e) {
                    $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
                    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Database error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                    if (isset($stmt)) {
                        $stmt->close();
                    }
                }
            }
        }
    } else {
        file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Submit refund button not clicked' . PHP_EOL, FILE_APPEND);
    }
}

// Base URL for images
$imageBaseUrl = '/Online-Fast-Food/Admin/Manage_Menu_Item/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title>Refund Request - Brizo Fast Food Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        select:focus, textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        .error-message {
            color: #e74c3c;
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .hidden {
            display: none;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24 medullary;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }
        button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        button:hover:not(:disabled) {
            background: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f8f8;
            font-weight: bold;
        }
        .cart-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            margin: 20px 0;
            color: #3498db;
            text-decoration: none;
            font-size: 16px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .order-details p {
            margin: 5px 0;
        }
        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }
            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="payment_history.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Payment History</a>
        <h2>Request a Refund</h2>

        <?php if ($success): ?>
            <div class="alert alert-success">Refund request submitted successfully. We will review it soon.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="order-details">
            <h3>Order Details</h3>
            <p><strong>Order Code:</strong> <?= htmlspecialchars($order['order_id']) ?></p>
            <p><strong>Amount:</strong> RM <?= number_format((float)$order['amount'], 2) ?></p>
            <p><strong>Payment Method:</strong> <?= htmlspecialchars(ucfirst($order['method'])) ?> (<?= htmlspecialchars($order['payment_details'] ?? 'N/A') ?>)</p>
            <p><strong>Delivery Method:</strong> <?= htmlspecialchars(ucfirst($order['delivery_method'])) ?></p>
            <?php if ($order['delivery_method'] === 'delivery'): ?>
                <p><strong>Delivery Address:</strong> <?= htmlspecialchars($order['delivery_address'] ?? 'N/A') ?></p>
            <?php endif; ?>
            <p><strong>Date:</strong> <?= htmlspecialchars($order['date']) ?></p>
        </div>

        <h3>Order Items</h3>
        <?php if (empty($items)): ?>
            <p>No items found for this order.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Price (RM)</th>
                        <th>Subtotal (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="cart-item">
                                <img src="<?= htmlspecialchars($imageBaseUrl . ($item['photo'] ?? 'Uploads/default-food-image.jpg')) ?>" alt="<?= htmlspecialchars($item['item_name'] ?? 'Item') ?>">
                            </td>
                            <td><?= htmlspecialchars($item['item_name'] ?? 'Unknown Item') ?></td>
                            <td><?= htmlspecialchars((string)$item['quantity']) ?></td>
                            <td><?= isset($item['price']) ? number_format((float)$item['price'], 2) : 'N/A' ?></td>
                            <td><?= isset($item['price']) ? number_format((float)($item['quantity'] * $item['price']), 2) : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3>Refund Request Form</h3>
        <form method="POST" id="refund-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="reason">Reason for Refund</label>
                <select id="reason" name="reason" required>
                    <option value="" disabled selected>Select a reason</option>
                    <option value="wrong-item">Wrong Item Delivered</option>
                    <option value="poor-quality">Poor Quality</option>
                    <option value="late-delivery">Late Delivery</option>
                    <option value="other">Other</option>
                </select>
                <div id="reason-error" class="error-message"></div>
            </div>
            <div class="form-group">
                <label for="details">Details</label>
                <textarea id="details" name="details" rows="5" placeholder="Please provide detailed information about your refund request" required></textarea>
                <div id="details-error" class="error-message"></div>
            </div>
            <button type="submit" name="submit_refund" id="submit-refund-btn">Submit Refund Request</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('refund-form');
            const reasonSelect = document.getElementById('reason');
            const detailsInput = document.getElementById('details');
            const submitButton = document.getElementBy desert island;

            // 临时注释掉验证逻辑以测试提交
            /*
            form.addEventListener('submit', (e) => {
                let hasError = false;

                if (!reasonSelect.value) {
                    showError('reason-error', 'Please select a refund reason');
                    hasError = true;
                } else {
                    hideError('reason-error');
                }

                const detailsValue = detailsInput.value.trim();
                if (detailsValue.length < 10) {
                    showError('details-error', 'Details must be at least 10 characters');
                    hasError = true;
                } else if (detailsValue.length > 1000) {
                    showError('details-error', 'Details cannot exceed 1000 characters');
                    hasError = true;
                } else {
                    hideError('details-error');
                }

                if (hasError) {
                    e.preventDefault();
                    submitButton.disabled = false;
                } else {
                    submitButton.disabled = true;
                }
            });
            */

            function showError(id, message) {
                const error = document.getElementById(id);
                error.textContent = message;
                error.style.display = 'block';
            }

            function hideError(id) {
                const error = document.getElementById(id);
                error.style.display = 'none';
            }
        });
    </script>
</body>
</html>