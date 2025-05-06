<?php
session_start();
require '../db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: ../../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Function to validate Luhn algorithm for card numbers
function luhnCheck($number) {
    $sum = 0;
    $isEven = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = (int)$number[$i];
        if ($isEven) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $isEven = !$isEven;
    }
    return $sum % 10 === 0;
}

// Fetch cart items
$cartItems = [];
$total = 0;
$stmt = $conn->prepare("
    SELECT c.item_id, c.quantity, m.price, m.item_name, m.photo, m.category
    FROM cart c
    JOIN menu_items m ON c.item_id = m.id
    WHERE c.customer_id = ? AND m.is_available = 1
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cartItems[] = $row;
    $total += $row['quantity'] * $row['price'];
}
$stmt->close();

// Log cart items for debugging
$logFile = 'payment_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};
$logMessage("Customer ID: $customerId");
foreach ($cartItems as $item) {
    $logMessage("Item: {$item['item_name']}, Photo Path: /Online-Fast-Food/Admin/Manage_Menu_Item/{$item['photo']}");
}
$logMessage("Cart items: " . json_encode($cartItems));
$logMessage("Calculated total: $total");

// Fetch saved payment methods
$paymentMethods = [];
$stmt = $conn->prepare("
    SELECT id, method_type, card_type, card_last_four, expiry_date, bank_name, wallet_type, phone_number
    FROM payment_methods
    WHERE customer_id = ?
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $paymentMethods[] = $row;
}
$stmt->close();

// Handle adding a new payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_method'])) {
    header('Content-Type: application/json');

    // Validate CSRF token (Fixed syntax error here)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed for add_payment_method");
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $methodType = $_POST['method_type'] ?? '';
    $logMessage("Attempting to add payment method: method_type=$methodType");

    if (!in_array($methodType, ['card', 'online_banking', 'digital_wallet'])) {
        $logMessage("Invalid payment method type: $methodType");
        echo json_encode(['status' => 'error', 'message' => 'Invalid payment method type']);
        exit();
    }

    try {
        // Prepare the insert statement with all possible fields
        $stmt = $conn->prepare("
            INSERT INTO payment_methods (customer_id, method_type, card_type, card_last_four, expiry_date, bank_name, wallet_type, phone_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for payment_methods insert: " . $conn->error);
            throw new Exception('Database error: Unable to prepare payment method insert statement');
        }

        $cardType = null;
        $cardLastFour = null;
        $cardExpiry = null;
        $bankName = null;
        $walletType = null;
        $phoneNumber = null;

        if ($methodType === 'card') {
            $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            $expiry = $_POST['expiry_date'] ?? '';
            $cvv = $_POST['cvv'] ?? '';
            $cardName = $_POST['card_name'] ?? '';
            $cardType = $_POST['card_type'] ?? '';

            $maskedCardNumber = '**** **** **** ' . substr($cardNumber, -4);
            $logMessage("Adding card - Number: $maskedCardNumber, Expiry: $expiry, Name: $cardName, Type: $cardType");

            if (empty($cardNumber) || !preg_match('/^\d{16}$/', $cardNumber)) {
                $logMessage("Invalid card number: $cardNumber");
                echo json_encode(['status' => 'error', 'message' => 'Card number must be 16 digits']);
                exit();
            }
            if (!luhnCheck($cardNumber)) {
                $logMessage("Card number failed Luhn check: $cardNumber");
                echo json_encode(['status' => 'error', 'message' => 'Invalid card number (failed validation check)']);
                exit();
            }
            if (empty($cardType) || !in_array($cardType, ['visa', 'mastercard', 'jcb', 'amex', 'mydebit', 'unionpay'])) {
                $logMessage("Invalid card type: $cardType");
                echo json_encode(['status' => 'error', 'message' => 'Invalid card type']);
                exit();
            }
            if (empty($expiry) || !preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
                $logMessage("Invalid expiry date format: $expiry");
                echo json_encode(['status' => 'error', 'message' => 'Invalid expiry date']);
                exit();
            }
            $expiryParts = explode('/', $expiry);
            $month = (int)$expiryParts[0];
            $year = (int)$expiryParts[1];
            $currentYear = (int)date('y');
            $currentMonth = (int)date('m');
            if ($month < 1 || $month > 12 || $year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
                $logMessage("Expired card: $month/$year");
                echo json_encode(['status' => 'error', 'message' => 'Card is expired']);
                exit();
            }
            if (empty($cvv) || !preg_match('/^\d{3,4}$/', $cvv)) {
                $logMessage("Invalid CVV: $cvv");
                echo json_encode(['status' => 'error', 'message' => 'CVV must be 3 or 4 digits']);
                exit();
            }
            if (empty($cardName) || preg_match('/\d/', $cardName)) {
                $logMessage("Invalid card name: $cardName");
                echo json_encode(['status' => 'error', 'message' => 'Invalid name on card']);
                exit();
            }

            $cardLastFour = substr($cardNumber, -4);
            $cardExpiry = $expiry;
        } elseif ($methodType === 'online_banking') {
            $bankName = $_POST['bank_name'] ?? '';
            $allowedBanks = ['maybank', 'cimb', 'public_bank', 'rhb', 'hong_leong', 'ambank', 'uob', 'ocbc', 'hsbc', 'standard_chartered'];
            if (empty($bankName) || !in_array($bankName, $allowedBanks)) {
                $logMessage("Invalid bank name: $bankName");
                echo json_encode(['status' => 'error', 'message' => 'Invalid bank name']);
                exit();
            }
        } elseif ($methodType === 'digital_wallet') {
            $walletType = $_POST['wallet_type'] ?? '';
            $phoneNumber = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');
            $allowedWallets = ['shopeepay', 'tng', 'grabpay', 'boost', 'googlepay'];
            if (empty($walletType) || !in_array($walletType, $allowedWallets)) {
                $logMessage("Invalid wallet type: $walletType");
                echo json_encode(['status' => 'error', 'message' => 'Invalid wallet type']);
                exit();
            }
            if (empty($phoneNumber) || !preg_match('/^\d{10,15}$/', $phoneNumber)) {
                $logMessage("Invalid phone number: $phoneNumber");
                echo json_encode(['status' => 'error', 'message' => 'Phone number must be 10-15 digits']);
                exit();
            }
            $logMessage("Adding digital wallet - Wallet Type: $walletType, Phone Number: $phoneNumber");
        }

        $stmt->bind_param("isssssss", $customerId, $methodType, $cardType, $cardLastFour, $cardExpiry, $bankName, $walletType, $phoneNumber);

        if ($stmt->execute()) {
            $newMethodId = $stmt->insert_id;
            $stmt->close();
            $logMessage("Payment method added successfully: ID $newMethodId");

            $stmt = $conn->prepare("
                SELECT id, method_type, card_type, card_last_four, expiry_date, bank_name, wallet_type, phone_number
                FROM payment_methods
                WHERE id = ?
            ");
            if (!$stmt) {
                $logMessage("Prepare failed for payment_methods select: " . $conn->error);
                throw new Exception('Database error: Unable to prepare payment method select statement');
            }
            $stmt->bind_param("i", $newMethodId);
            $stmt->execute();
            $newMethod = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $displayText = '';
            if ($methodType === 'card') {
                $displayText = ucfirst($newMethod['card_type']) . ' ending in ' . $newMethod['card_last_four'];
            } elseif ($methodType === 'online_banking') {
                $displayText = ucfirst(str_replace('_', ' ', $newMethod['bank_name']));
            } elseif ($methodType === 'digital_wallet') {
                $displayText = ucfirst($newMethod['wallet_type']) . ' (' . $newMethod['phone_number'] . ')';
            }

            echo json_encode([
                'status' => 'success',
                'message' => $displayText . ' added successfully',
                'payment_method' => $newMethod
            ]);
        } else {
            $logMessage("Failed to add payment method: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to add payment method']);
        }
        exit();
    } catch (Exception $e) {
        $logMessage("Exception while adding payment method: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    } catch (Throwable $t) {
        $logMessage("Unexpected error while adding payment method: " . $t->getMessage() . " in " . $t->getFile() . " at line " . $t->getLine());
        echo json_encode(['status' => 'error', 'message' => 'Unexpected error occurred. Please try again later.']);
        exit();
    }
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $logFile = 'payment_errors.log';
    $logMessage = function($message) use ($logFile) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    };
    header('Content-Type: application/json');

    try {
        $logMessage("Received make_payment request: " . json_encode($_POST));

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $logMessage("CSRF validation failed for make_payment");
            echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
            exit();
        }

        if (empty($cartItems)) {
            $logMessage("Validation failed: Cart is empty for customer_id=$customerId");
            echo json_encode(['status' => 'error', 'message' => 'Your cart is empty']);
            exit();
        }

        $method = $_POST['method'] ?? '';
        $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $deliveryMethod = $_POST['delivery_method'] ?? '';
        $deliveryAddress = $_POST['delivery_address'] ?? '';
        $deliveryAddress = ($deliveryMethod === 'delivery') ? $deliveryAddress : null;

        $logMessage("Received amount: $amount, Expected total: $total");
        $logMessage("Delivery method: $deliveryMethod, Delivery address: " . ($deliveryAddress ?: 'N/A'));
        $logMessage("Payment method: $method, Payment method ID: $paymentMethodId");

        if (!in_array($method, ['card', 'online_banking', 'digital_wallet'])) {
            $logMessage("Validation failed: Invalid payment method ($method)");
            echo json_encode(['status' => 'error', 'message' => 'Invalid payment method']);
            exit();
        }
        if ($paymentMethodId <= 0) {
            $logMessage("Validation failed: No payment method selected");
            echo json_encode(['status' => 'error', 'message' => 'Please select a payment method']);
            exit();
        }
        if ($amount <= 0 || abs($amount - $total) > 0.01) {
            $logMessage("Validation failed: Invalid payment amount ($amount, expected $total)");
            echo json_encode(['status' => 'error', 'message' => 'Invalid payment amount']);
            exit();
        }
        if (!in_array($deliveryMethod, ['pickup', 'delivery'])) {
            $logMessage("Validation failed: Invalid delivery method ($deliveryMethod)");
            echo json_encode(['status' => 'error', 'message' => 'Invalid delivery method']);
            exit();
        }
        if ($deliveryMethod === 'delivery' && (empty($deliveryAddress) || strlen(trim($deliveryAddress)) < 5)) {
            $logMessage("Validation failed: Delivery address too short or empty");
            echo json_encode(['status' => 'error', 'message' => 'Delivery address must be at least 5 characters']);
            exit();
        }

        $selectedMethod = null;
        foreach ($paymentMethods as $pm) {
            if ($pm['id'] === $paymentMethodId) {
                if ($pm['method_type'] !== $method) {
                    $logMessage("Validation failed: Method type mismatch for payment_method_id=$paymentMethodId. Expected method_type=$method, got method_type=" . $pm['method_type']);
                    echo json_encode(['status' => 'error', 'message' => 'Selected payment method type does not match']);
                    exit();
                }
                $selectedMethod = $pm;
                break;
            }
        }
        if (!$selectedMethod) {
            $logMessage("Validation failed: Invalid payment method ID ($paymentMethodId)");
            echo json_encode(['status' => 'error', 'message' => 'Invalid payment method selected']);
            exit();
        }

        $paymentDetails = '';
        if ($method === 'card') {
            $paymentDetails = $selectedMethod['card_last_four'];
        } elseif ($method === 'online_banking') {
            $paymentDetails = $selectedMethod['bank_name'];
        } elseif ($method === 'digital_wallet') {
            $paymentDetails = $selectedMethod['phone_number'] ?: $selectedMethod['wallet_type'];
        }

        $itemsArray = [];
        foreach ($cartItems as $item) {
            $itemsArray[] = [
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'item_name' => $item['item_name'],
                'photo' => $item['photo']
            ];
        }
        $itemsJson = json_encode($itemsArray);

        $conn->begin_transaction();
        $orderId = 'ORD-' . strtoupper(uniqid());
        $logMessage("Generated order_id: $orderId");

        // Debug: Log the database and table existence
        $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];
        $logMessage("Current database: $dbName");
        $tableExists = $conn->query("SHOW TABLES LIKE 'orders'");
        $logMessage("Table 'orders' exists: " . ($tableExists->num_rows > 0 ? 'Yes' : 'No'));

        // Debug: Verify the columns in the orders table
        $columns = $conn->query("DESCRIBE orders");
        $columnNames = [];
        while ($row = $columns->fetch_assoc()) {
            $columnNames[] = $row['Field'];
        }
        $logMessage("Columns in 'orders' table: " . json_encode($columnNames));

        // Save to orders table, including the items JSON
        $sql = "
            INSERT INTO orders (order_id, customer_id, items, total, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ";
        $logMessage("Executing SQL for orders insert: $sql");
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error = "Prepare failed for orders insert: " . $conn->error;
            $logMessage($error);
            throw new Exception('Database error: Unable to prepare orders insert statement');
        }
        $stmt->bind_param("sisd", $orderId, $customerId, $itemsJson, $amount);
        if (!$stmt->execute()) {
            $error = "Execute failed for orders insert: " . $stmt->error;
            $logMessage($error);
            throw new Exception('Failed to save order: ' . $stmt->error);
        }
        $stmt->close();

        // Save to order_items table
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, item_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmt) {
            $error = "Prepare failed for order_items insert: " . $conn->error;
            $logMessage($error);
            throw new Exception('Database error: Unable to prepare order_items insert statement');
        }
        foreach ($cartItems as $item) {
            $logMessage("Inserting order_item: order_id=$orderId, item_id={$item['item_id']}, quantity={$item['quantity']}, price={$item['price']}");
            $price = (float)$item['price'];
            $stmt->bind_param("siid", $orderId, $item['item_id'], $item['quantity'], $price);
            if (!$stmt->execute()) {
                $error = "Execute failed for order_items insert: " . $stmt->error;
                $logMessage($error);
                throw new Exception('Failed to save order items: ' . $stmt->error);
            }
        }
        $stmt->close();

        // Save to payment_history
        $deliveryAddress = $deliveryAddress ?? '';
        $stmt = $conn->prepare("
            INSERT INTO payment_history (order_id, date, amount, status, method, payment_details, payment_method_id, customer_id, delivery_method, delivery_address)
            VALUES (?, NOW(), ?, 'pending', ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $error = "Prepare failed for payment_history insert: " . $conn->error;
            $logMessage($error);
            throw new Exception('Database error: Unable to prepare payment insert statement');
        }
        $stmt->bind_param("sdssiiss", $orderId, $amount, $method, $paymentDetails, $paymentMethodId, $customerId, $deliveryMethod, $deliveryAddress);
        if (!$stmt->execute()) {
            $error = "Execute failed for payment_history insert: " . $stmt->error;
            $logMessage($error);
            throw new Exception('Failed to save payment: ' . $stmt->error);
        }
        $stmt->close();

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
        if (!$stmt) {
            $error = "Prepare failed for cart deletion: " . $conn->error;
            $logMessage($error);
            throw new Exception('Database error: Unable to prepare cart deletion statement');
        }
        $stmt->bind_param("i", $customerId);
        if (!$stmt->execute()) {
            $error = "Execute failed for cart deletion: " . $stmt->error;
            $logMessage($error);
            throw new Exception('Failed to clear cart: ' . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        $logMessage("Payment successful: order_id=$orderId");

        $_SESSION['last_order'] = [
            'order_code' => $orderId,
            'amount' => $amount,
            'method' => $method,
            'payment_details' => $paymentDetails,
            'delivery_method' => $deliveryMethod,
            'delivery_address' => $deliveryAddress,
            'timestamp' => date('Y-m-d H:i:s'),
            'items' => $itemsArray
        ];

        echo json_encode(['status' => 'success', 'message' => 'Payment Successful']);
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Exception in make_payment: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (Throwable $t) {
        $conn->rollback();
        $logMessage("Unexpected error in make_payment: " . $t->getMessage() . " in " . $t->getFile() . " at line " . $t->getLine());
        echo json_encode(['status' => 'error', 'message' => 'Unexpected error occurred. Please try again later.']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2, h3 {
            color: #333;
        }
        .cart-items ul {
            list-style: none;
            padding: 0;
        }
        .cart-items li {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #ddd;
        }
        .cart-items img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
        }
        .cart-items .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .cart-items .item-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .cart-items .item-quantity-price {
            font-size: 16px;
            color: #555;
        }
        .delivery-options, .payment-methods {
            margin: 20px 0;
        }
        .delivery-options label, .payment-methods label {
            display: block;
            margin: 10px 0;
        }
        .delivery-options input[type="radio"], .payment-methods input[type="radio"] {
            margin-right: 5px;
        }
        #deliveryAddress {
            width: 100%;
            padding: 8px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        #paymentMethodSelect {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .add-payment-method {
            cursor: pointer;
            color: #3498db;
            text-decoration: underline;
            margin-top: 10px;
            display: inline-block;
        }
        .payment-form {
            display: none;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .payment-form input, .payment-form select {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .payment-form button {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .payment-form button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        .payment-form button:hover:not(:disabled) {
            background: #2980b9;
        }
        .total {
            font-weight: bold;
            margin: 20px 0;
        }
        button[type="submit"] {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button[type="submit"]:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        button[type="submit"]:hover:not(:disabled) {
            background: #2980b9;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .hint {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Checkout</h2>

        <!-- Cart Items -->
        <div class="cart-items">
            <h3>Cart Items</h3>
            <?php if (empty($cartItems)): ?>
                <p>Your cart is empty. <a href="cart.php">Add items to your cart</a>.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($cartItems as $item): ?>
                        <li>
                            <img src="/Online-Fast-Food/Admin/Manage_Menu_Item/<?= htmlspecialchars($item['photo']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                            <div class="item-details">
                                <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                <span class="item-quantity-price">Quantity: <?= $item['quantity'] ?> | Price: RM <?= number_format($item['price'], 2) ?> each | Total: RM <?= number_format($item['quantity'] * $item['price'], 2) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Delivery Options -->
        <form id="paymentForm" action="payment.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="delivery-options">
                <h3>Delivery Options</h3>
                <label>
                    <input type="radio" name="delivery_method" value="pickup" checked>
                    <i class="fas fa-store"></i> Pick Up
                </label>
                <label>
                    <input type="radio" name="delivery_method" value="delivery">
                    <i class="fas fa-truck"></i> Delivery
                </label>
                <textarea id="deliveryAddress" name="delivery_address" placeholder="Enter delivery address" style="display: none;"></textarea>
            </div>

            <!-- Payment Section -->
            <div class="payment-methods">
                <h3>Make Payment</h3>
                <div id="message" class="message" style="display: none;"></div>

                <label for="paymentMethodSelect">Select Payment Method</label>
                <select id="paymentMethodSelect" name="payment_method_id">
                    <option value="">Select a payment method</option>
                    <?php foreach ($paymentMethods as $pm): ?>
                        <?php
                        $displayText = '';
                        $iconClass = 'fa-credit-card'; // Default icon
                        if ($pm['method_type'] === 'card') {
                            $displayText = ucfirst($pm['card_type']) . ' ending in ' . $pm['card_last_four'];
                            $iconClass = 'fa-credit-card';
                        } elseif ($pm['method_type'] === 'online_banking') {
                            $displayText = ucfirst(str_replace('_', ' ', $pm['bank_name']));
                            $iconClass = 'fa-university';
                        } elseif ($pm['method_type'] === 'digital_wallet') {
                            $displayText = ucfirst($pm['wallet_type']) . ($pm['phone_number'] ? ' (' . $pm['phone_number'] . ')' : '');
                            $iconClass = 'fa-wallet';
                        }
                        ?>
                        <option value="<?= $pm['id'] ?>" data-method-type="<?= $pm['method_type'] ?>">
                            <i class="fas <?= $iconClass ?>"></i> <?= htmlspecialchars($displayText) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="add-payment-method" onclick="showPaymentForm()">+ Add New Payment Method</div>

                <!-- New Payment Method Form -->
                <div id="newPaymentForm" class="payment-form">
                    <select id="methodTypeSelect" onchange="updatePaymentForm()">
                        <option value="">Select Method</option>
                        <option value="card">Card</option>
                        <option value="online_banking">Online Banking</option>
                        <option value="digital_wallet">Digital Wallet</option>
                    </select>

                    <!-- Card Payment Fields -->
                    <div id="cardFields" style="display: none;">
                        <select id="cardTypeSelect" onchange="validatePaymentForm()">
                            <option value="">Select Card Type</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="jcb">JCB</option>
                            <option value="amex">Amex</option>
                            <option value="mydebit">MyDebit</option>
                            <option value="unionpay">UnionPay</option>
                        </select>
                        <input type="text" id="cardNumber" placeholder="Card Number" maxlength="19" onkeyup="formatCardNumber(this); validatePaymentForm()">
                        <div class="hint">For testing, use Visa card: 4242424242424242</div>
                        <input type="text" id="expiryDate" placeholder="MM/YY" maxlength="5" onkeyup="formatExpiryDate(this); validatePaymentForm()">
                        <input type="text" id="cvv" placeholder="CVV" oninput="validatePaymentForm()">
                        <input type="text" id="cardName" placeholder="Name on Card" oninput="validatePaymentForm()">
                    </div>

                    <!-- Online Banking Fields -->
                    <div id="onlineBankingFields" style="display: none;">
                        <select id="bankNameSelect" onchange="validatePaymentForm()">
                            <option value="">Select Bank</option>
                            <option value="maybank">Maybank</option>
                            <option value="cimb">CIMB</option>
                            <option value="public_bank">Public Bank</option>
                            <option value="rhb">RHB</option>
                            <option value="hong_leong">Hong Leong</option>
                            <option value="ambank">AmBank</option>
                            <option value="uob">UOB</option>
                            <option value="ocbc">OCBC</option>
                            <option value="hsbc">HSBC</option>
                            <option value="standard_chartered">Standard Chartered</option>
                        </select>
                    </div>

                    <!-- Digital Wallet Fields -->
                    <div id="digitalWalletFields" style="display: none;">
                        <select id="walletTypeSelect" onchange="validatePaymentForm()">
                            <option value="">Select Wallet</option>
                            <option value="shopeepay">ShopeePay</option>
                            <option value="tng">Touch 'n Go</option>
                            <option value="grabpay">GrabPay</option>
                            <option value="boost">Boost</option>
                            <option value="googlepay">Google Pay</option>
                        </select>
                        <input type="text" id="phoneNumber" placeholder="Phone Number" oninput="validatePaymentForm()">
                    </div>

                    <button type="button" id="addPaymentButton" onclick="addPaymentMethod()" disabled>Add Payment Method</button>
                </div>

                <!-- Hidden fields for payment submission -->
                <input type="hidden" name="method" id="method">
                <input type="hidden" name="amount" value="<?= $total ?>">
                <input type="hidden" name="make_payment" value="1">
            </div>

            <div class="total">
                Payment Amount (RM): <?= number_format($total, 2) ?>
            </div>

            <button type="submit" id="submitButton" <?php if (empty($cartItems)) echo 'disabled'; ?>>Make Payment</button>
        </form>
    </div>

    <script>
        function formatCardNumber(input) {
            console.log('Formatting card number, raw value:', input.value);
            let value = input.value.replace(/\D/g, '');
            if (value.length > 16) {
                value = value.substring(0, 16);
            }
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            console.log('Formatted card number:', formatted);
            input.value = formatted;
        }

        function formatExpiryDate(input) {
            console.log('Formatting expiry date, raw value:', input.value);
            let value = input.value.replace(/\D/g, '');
            if (value.length > 4) {
                value = value.substring(0, 4);
            }
            let formatted = value;
            if (value.length > 2) {
                formatted = value.substring(0, 2) + '/' + value.substring(2);
            }
            console.log('Formatted expiry date:', formatted);
            input.value = formatted;
        }

        const deliveryRadios = document.querySelectorAll('input[name="delivery_method"]');
        const deliveryAddress = document.getElementById('deliveryAddress');
        const paymentMethodSelect = document.getElementById('paymentMethodSelect');
        const newPaymentForm = document.getElementById('newPaymentForm');
        const methodTypeSelect = document.getElementById('methodTypeSelect');
        const cardFields = document.getElementById('cardFields');
        const onlineBankingFields = document.getElementById('onlineBankingFields');
        const digitalWalletFields = document.getElementById('digitalWalletFields');
        const messageDiv = document.getElementById('message');
        const paymentForm = document.getElementById('paymentForm');
        const methodInput = document.getElementById('method');
        const addPaymentButton = document.getElementById('addPaymentButton');
        const submitButton = document.getElementById('submitButton');

        deliveryRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'delivery') {
                    deliveryAddress.style.display = 'block';
                    deliveryAddress.required = true;
                } else {
                    deliveryAddress.style.display = 'none';
                    deliveryAddress.required = false;
                    deliveryAddress.value = '';
                }
            });
        });

        function showPaymentForm() {
            newPaymentForm.style.display = 'block';
        }

        function updatePaymentForm() {
            cardFields.style.display = 'none';
            onlineBankingFields.style.display = 'none';
            digitalWalletFields.style.display = 'none';
            addPaymentButton.disabled = true;

            if (methodTypeSelect.value === 'card') {
                cardFields.style.display = 'block';
            } else if (methodTypeSelect.value === 'online_banking') {
                onlineBankingFields.style.display = 'block';
                validatePaymentForm();
            } else if (methodTypeSelect.value === 'digital_wallet') {
                digitalWalletFields.style.display = 'block';
                validatePaymentForm();
            }
        }

        function validatePaymentForm() {
            const method = methodTypeSelect.value;
            let isValid = false;

            if (method === 'card') {
                const cardType = document.getElementById('cardTypeSelect').value;
                const cardNumber = document.getElementById('cardNumber').value.replace(/\D/g, '');
                const expiryDate = document.getElementById('expiryDate').value;
                const cvv = document.getElementById('cvv').value;
                const cardName = document.getElementById('cardName').value;

                isValid = cardType !== '' &&
                          cardNumber.length === 16 &&
                          /^\d{2}\/\d{2}$/.test(expiryDate) &&
                          /^\d{3,4}$/.test(cvv) &&
                          cardName !== '' && !/\d/.test(cardName);
            } else if (method === 'online_banking') {
                const bankName = document.getElementById('bankNameSelect').value;
                isValid = bankName !== '';
            } else if (method === 'digital_wallet') {
                const walletType = document.getElementById('walletTypeSelect').value;
                const phoneNumber = document.getElementById('phoneNumber').value;
                isValid = walletType !== '' && /^\d{10,15}$/.test(phoneNumber);
            }

            addPaymentButton.disabled = !isValid;
        }

        function addPaymentMethod() {
            const method = methodTypeSelect.value;
            if (!method) {
                showMessage('error', 'Please select a payment method type');
                return;
            }

            const formData = new FormData();
            formData.append('add_payment_method', '1');
            formData.append('method_type', method);
            formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');

            if (method === 'card') {
                let cardNumber = document.getElementById('cardNumber').value.replace(/\D/g, '');
                const expiryDate = document.getElementById('expiryDate').value;
                const cvv = document.getElementById('cvv').value;
                const cardName = document.getElementById('cardName').value;
                const cardType = document.getElementById('cardTypeSelect').value;

                if (!cardType) {
                    showMessage('error', 'Please select a card type');
                    return;
                }
                if (!cardNumber || !/^\d{16}$/.test(cardNumber)) {
                    showMessage('error', 'Please enter a valid 16-digit card number');
                    return;
                }
                if (!expiryDate || !/^\d{2}\/\d{2}$/.test(expiryDate)) {
                    showMessage('error', 'Please enter a valid expiry date (MM/YY)');
                    return;
                }
                if (!cvv || !/^\d{3,4}$/.test(cvv)) {
                    showMessage('error', 'Please enter a valid CVV (3 or 4 digits)');
                    return;
                }
                if (!cardName || /\d/.test(cardName)) {
                    showMessage('error', 'Please enter a valid name on card (no numbers)');
                    return;
                }

                formData.append('card_number', document.getElementById('cardNumber').value);
                formData.append('expiry_date', expiryDate);
                formData.append('cvv', cvv);
                formData.append('card_name', cardName);
                formData.append('card_type', cardType);
            } else if (method === 'online_banking') {
                const bankName = document.getElementById('bankNameSelect').value;
                if (!bankName) {
                    showMessage('error', 'Please select a bank');
                    return;
                }
                formData.append('bank_name', bankName);
            } else if (method === 'digital_wallet') {
                const walletType = document.getElementById('walletTypeSelect').value;
                const phoneNumber = document.getElementById('phoneNumber').value;
                if (!walletType) {
                    showMessage('error', 'Please select a wallet type');
                    return;
                }
                if (!phoneNumber || !/^\d{10,15}$/.test(phoneNumber)) {
                    showMessage('error', 'Please enter a valid phone number (10-15 digits)');
                    return;
                }
                formData.append('wallet_type', walletType);
                formData.append('phone_number', phoneNumber);
            }

            fetch('payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    const option = new Option(data.message, data.payment_method.id, true, true);
                    option.setAttribute('data-method-type', method);
                    paymentMethodSelect.appendChild(option);
                    newPaymentForm.style.display = 'none';
                    methodTypeSelect.value = '';
                    updatePaymentForm();

                    if (method === 'card') {
                        document.getElementById('cardNumber').value = '';
                        document.getElementById('expiryDate').value = '';
                        document.getElementById('cvv').value = '';
                        document.getElementById('cardName').value = '';
                        document.getElementById('cardTypeSelect').value = '';
                    } else if (method === 'online_banking') {
                        document.getElementById('bankNameSelect').value = '';
                    } else if (method === 'digital_wallet') {
                        document.getElementById('walletTypeSelect').value = '';
                        document.getElementById('phoneNumber').value = '';
                    }
                } else {
                    showMessage('error', data.message);
                }
            })
            .catch(error => {
                showMessage('error', 'An error occurred while adding payment method');
                console.error(error);
            });
        }

        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const selectedOption = paymentMethodSelect.options[paymentMethodSelect.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                showMessage('error', 'Please select a payment method');
                return;
            }

            const methodType = selectedOption.getAttribute('data-method-type');
            methodInput.value = methodType;
            const formData = new FormData(paymentForm);

            const formDataEntries = {};
            for (let [key, value] of formData.entries()) {
                formDataEntries[key] = value;
            }
            console.log('Sending make_payment request:', formDataEntries);

            fetch('payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = 'confirmation.php';
                } else {
                    showMessage('error', data.message);
                }
            })
            .catch(error => {
                showMessage('error', 'An error occurred while processing payment');
                console.error(error);
            });
        });

        function showMessage(type, message) {
            messageDiv.className = 'message ' + type;
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html> 