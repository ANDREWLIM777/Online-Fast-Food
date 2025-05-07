<?php
declare(strict_types=1);
session_start();
session_regenerate_id(true);

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
        WHERE ph.order_id = ? AND ph.customer_id = ?
    ");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Invalid order_id: ' . $orderId . ' for customer_id: ' . $customerId . PHP_EOL, FILE_APPEND);
        header('Location: payment_history.php');
        exit();
    }

    // Check for existing refund request
    $stmt = $conn->prepare("
        SELECT id, status, created_at
        FROM refund_requests
        WHERE order_id = ? AND customer_id = ? AND status IN ('pending', 'approved')
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingRefund = $result->fetch_assoc();
    $stmt->close();

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
        // Ensure price is a float
        $row['price'] = floatval($row['price']);
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
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund'])) {
    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - POST request received' . PHP_EOL, FILE_APPEND);
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
        file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Invalid CSRF token' . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - CSRF token validated' . PHP_EOL, FILE_APPEND);
        $reason = $_POST['reason'] ?? '';
        $details = trim($_POST['details'] ?? '');

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

        // Prepare items and total for refund request
        $itemsArray = [];
        $total = floatval($order['amount']);
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $total = 0;
            foreach ($_POST['items'] as $index => $itemData) {
                if (!empty($itemData['selected'])) {
                    $quantity = (int)($itemData['quantity'] ?? 0);
                    $index = (int)$index;
                    if ($index >= 0 && $index < count($items) && $quantity > 0 && $quantity <= ($items[$index]['quantity'] ?? 0)) {
                        $price = floatval($items[$index]['price'] ?? 0);
                        $itemsArray[] = [
                            'item_id' => (int)($items[$index]['item_id'] ?? 0),
                            'quantity' => $quantity,
                            'price' => $price,
                            'item_name' => $items[$index]['item_name'] ?? 'Unknown Item',
                            'photo' => $items[$index]['photo'] ?? ''
                        ];
                        $total += $quantity * $price;
                    }
                }
            }
            if (empty($itemsArray)) {
                $errors[] = 'Please select at least one item for refund';
                file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Validation failed: No items selected for refund' . PHP_EOL, FILE_APPEND);
            }
        } else {
            // Full refund
            foreach ($items as $item) {
                $itemsArray[] = [
                    'item_id' => (int)($item['item_id'] ?? 0),
                    'quantity' => (int)($item['quantity'] ?? 0),
                    'price' => floatval($item['price'] ?? 0),
                    'item_name' => $item['item_name'] ?? 'Unknown Item',
                    'photo' => $item['photo'] ?? ''
                ];
            }
        }
        $itemsJson = json_encode($itemsArray);

        if (empty($errors)) {
            file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Validation passed, attempting to insert' . PHP_EOL, FILE_APPEND);
            try {
                // Double-check for existing refund request to prevent race condition
                $stmt = $conn->prepare("
                    SELECT id FROM refund_requests
                    WHERE order_id = ? AND customer_id = ? AND status IN ('pending', 'approved')
                ");
                $stmt->bind_param("si", $orderId, $customerId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $errors[] = 'A pending or approved refund request already exists for this order.';
                    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Existing refund request found during submission for order_id: ' . $orderId . PHP_EOL, FILE_APPEND);
                    $stmt->close();
                } else {
                    $stmt->close();

                    // Insert refund request
                    $stmt = $conn->prepare("
                        INSERT INTO refund_requests (customer_id, order_id, reason, details, status, created_at, total, items)
                        VALUES (?, ?, ?, ?, 'pending', NOW(), ?, ?)
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $stmt->bind_param("isssds", $customerId, $orderId, $reason, $details, $total, $itemsJson);
                    if (!$stmt->execute()) {
                        throw new Exception('Execute failed: ' . $stmt->error);
                    }
                    $refundId = $conn->insert_id;
                    $stmt->close();

                    // Insert notification for customer
                    $stmt = $conn->prepare("
                        INSERT INTO customer_notifications (customer_id, title, message, type, order_id, created_at)
                        VALUES (?, 'Refund Request Submitted', 'Your refund request for order ? has been submitted and is pending review.', 'refund', ?, NOW())
                    ");
                    $stmt->bind_param("iss", $customerId, $orderId, $orderId);
                    $stmt->execute();
                    $stmt->close();

                    $success = true;
                    file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Refund request submitted for order_id: ' . $orderId . ' with total: ' . $total . ' and items: ' . $itemsJson . PHP_EOL, FILE_APPEND);
                }
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
                file_put_contents('refund_errors.log', date('Y-m-d H:i:s') . ' - Database error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                if (isset($stmt)) {
                    $stmt->close();
                }
            }
        }
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
        select, textarea, input[type="number"], input[type="checkbox"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        select:focus, textarea:focus, input:focus {
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
        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }
        .hidden {
            display: none;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
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
        input[type="number"] {
            width: 80px;
        }
        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }
            button {
                width: 100%;
            }
            input[type="number"] {
                width: 60px;
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

        <?php if ($existingRefund): ?>
            <div class="alert alert-warning">
                A refund request for this order is already in progress (Status: <?= htmlspecialchars(ucfirst($existingRefund['status'])) ?>, Submitted on: <?= htmlspecialchars($existingRefund['created_at']) ?>). You cannot submit a new request until the current one is resolved.
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
                            <td><?= number_format((float)($item['price'] ?? 0), 2) ?></td>
                            <td><?= number_format((float)($item['quantity'] * $item['price']), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!$existingRefund): ?>
            <h3>Refund Request Form</h3>
            <form method="POST" id="refund-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="form-group">
                    <h4>Select Items for Refund</h4>
                    <?php if (empty($items)): ?>
                        <p>No items available for refund.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Price (RM)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $index => $item): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="items[<?= $index ?>][selected]" value="1" onchange="updateTotal()">
                                            <input type="hidden" name="items[<?= $index ?>][item_id]" value="<?= $item['item_id'] ?>">
                                            <input type="hidden" name="items[<?= $index ?>][item_name]" value="<?= htmlspecialchars($item['item_name']) ?>">
                                            <input type="hidden" name="items[<?= $index ?>][price]" value="<?= $item['price'] ?>">
                                            <input type="hidden" name="items[<?= $index ?>][photo]" value="<?= htmlspecialchars($item['photo']) ?>">
                                        </td>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td>
                                            <input type="number" name="items[<?= $index ?>][quantity]" min="1" max="<?= $item['quantity'] ?>" value="<?= $item['quantity'] ?>" onchange="updateTotal()">
                                        </td>
                                        <td><?= number_format((float)($item['price'] ?? 0), 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p><strong>Total Refund Amount: RM <span id="refund-total">0.00</span></strong></p>
                    <?php endif; ?>
                </div>
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
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('refund-form');
            if (form) {
                const reasonSelect = document.getElementById('reason');
                const detailsInput = document.getElementById('details');
                const submitButton = document.getElementById('submit-refund-btn');

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

                    let itemsSelected = false;
                    const itemCheckboxes = document.querySelectorAll('input[name*="items"][type="checkbox"]');
                    itemCheckboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            itemsSelected = true;
                            const quantityInput = checkbox.closest('tr').querySelector('input[type="number"]');
                            const max = parseInt(quantityInput.max);
                            const value = parseInt(quantityInput.value);
                            if (value < 1 || value > max) {
                                showError('details-error', 'Invalid quantity for ' + checkbox.closest('tr').querySelector('td:nth-child(2)').textContent);
                                hasError = true;
                            }
                        }
                    });
                    if (!itemsSelected && itemCheckboxes.length > 0) {
                        showError('details-error', 'Please select at least one item for refund');
                        hasError = true;
                    }

                    if (hasError) {
                        e.preventDefault();
                        submitButton.disabled = false;
                    } else {
                        submitButton.disabled = true;
                    }
                });

                function updateTotal() {
                    let total = 0;
                    const itemCheckboxes = document.querySelectorAll('input[name*="items"][type="checkbox"]');
                    itemCheckboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            const row = checkbox.closest('tr');
                            const quantity = parseInt(row.querySelector('input[type="number"]').value);
                            const price = parseFloat(row.querySelector('input[name*="price"]').value);
                            if (quantity > 0) {
                                total += quantity * price;
                            }
                        }
                    });
                    document.getElementById('refund-total').textContent = total.toFixed(2);
                }

                function showError(id, message) {
                    const error = document.getElementById(id);
                    error.textContent = message;
                    error.style.display = 'block';
                }

                function hideError(id) {
                    const error = document.getElementById(id);
                    error.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>