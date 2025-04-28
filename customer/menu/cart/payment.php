<?php
session_start();
require '../db_connect.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

// Base URL for images
$imageBaseUrl = '/Online-Fast-Food/Admin/Manage_Menu_Item/';

// Fetch cart items for display and total calculation
$total = 0;
$cartItems = [];
$stmt = $conn->prepare("
    SELECT c.item_id, c.quantity, m.price, m.item_name, m.photo, m.category
    FROM cart c
    JOIN menu_items m ON c.item_id = m.id
    WHERE c.customer_id = ?
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $cartItems[] = $row;
    $total += $row['quantity'] * $row['price'];
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $method = $_POST['method'];
    $amount = floatval($_POST['amount']);

    // Server-side validation
    if ($amount <= 0 || abs($amount - $total) > 0.01) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid payment amount']);
        exit();
    }

    if ($method === 'card') {
        $cardNumber = preg_replace('/\D/', '', $_POST['card_number']);
        $expiry = $_POST['expiry_date'];
        if (!preg_match('/^\d{16}$/', $cardNumber) || !luhnCheck($cardNumber)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid card number']);
            exit();
        }
        if (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid expiry date']);
            exit();
        }
    } elseif ($method === 'wallet') {
        $pin = $_POST['wallet_pin'];
        if (!preg_match('/^\d{6}$/', $pin)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid wallet PIN']);
            exit();
        }
        // Placeholder: Check wallet balance
        $walletBalance = 1000.00; // Example balance
        if ($walletBalance < $amount) {
            echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance']);
            exit();
        }
    }

    // Start a transaction to ensure data consistency
    $conn->begin_transaction();

    try {
        // Generate a unique order ID
        $orderId = 'ORD-' . strtoupper(uniqid());

        // Prepare items JSON for the orders table
        $itemsArray = [];
        foreach ($cartItems as $item) {
            $itemsArray[] = [
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ];
        }
        $itemsJson = json_encode($itemsArray);

        // Save to orders table with status 'pending'
        $stmt = $conn->prepare("
            INSERT INTO orders (order_id, customer_id, items, total, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("sisd", $orderId, $customerId, $itemsJson, $total);
        if (!$stmt->execute()) {
            throw new Exception('Failed to save to orders table');
        }

        // Save to payment_history table
        $stmt = $conn->prepare("
            INSERT INTO payment_history (order_id, date, amount, status, method, customer_id)
            VALUES (?, NOW(), ?, 'completed', ?, ?)
        ");
        $stmt->bind_param("sdsi", $orderId, $amount, $method, $customerId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment history');
        }

        // Clear cart after successful payment
        $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to clear cart');
        }

        // Commit the transaction
        $conn->commit();

        // Store order details in session for confirmation page
        $_SESSION['last_order'] = [
            'order_id' => $orderId,
            'amount' => $amount,
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        echo json_encode(['status' => 'success', 'message' => 'Payment Successful']);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Luhn Algorithm for card number validation
function luhnCheck($number) {
    $sum = 0;
    $isEven = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = (int)$number[$i];
        if ($isEven) {
            $digit *= 2;
            if ($digit > 9) $digit -= 9;
        }
        $sum += $digit;
        $isEven = !$isEven;
    }
    return $sum % 10 === 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment - Brizo Fast Food Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .payment-method {
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .payment-method.active {
            border-color: #3498db;
            background: #f0f8ff;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input.error {
            border-color: #e74c3c;
        }
        .error-message {
            color: #e74c3c;
            font-size: 0.8em;
            display: none;
        }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
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
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #2980b9;
        }
        .back-cart {
            display: inline-block;
            margin: 20px 0;
            color: #3498db;
            text-decoration: none;
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
            background-color: #f8f8f8;
        }
        .cart-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .category {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="cart.php" class="back-cart">⬅️ Back to Cart</a>
        <h2>Your Order</h2>

        <?php if (empty($cartItems)): ?>
            <p>Your cart is empty.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Item Details</th>
                        <th>Quantity</th>
                        <th>Price (RM)</th>
                        <th>Subtotal (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td class="cart-item">
                                <img src="<?= htmlspecialchars($imageBaseUrl . ($item['photo'] ?? 'uploads/default-food-image.jpg')) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong><br>
                                <span class="category"><?= htmlspecialchars(ucfirst($item['category'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars($item['quantity']) ?></td>
                            <td><?= number_format($item['price'], 2) ?></td>
                            <td><?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: bold;">Total:</td>
                        <td style="font-weight: bold;"><?= number_format($total, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Make Payment</h2>
        <div id="payment-alert"></div>
        <div class="payment-method active" data-method="card">
            <i class="fas fa-credit-card"></i> Credit/Debit Card
        </div>
        <div class="payment-method" data-method="wallet">
            <i class="fas fa-wallet"></i> Digital Wallet
        </div>

        <div id="card-payment">
            <div class="form-group">
                <label for="card-number">Card Number</label>
                <input type="text" id="card-number" placeholder="1234 5678 9012 3456" maxlength="19" onkeypress="return (event.charCode !=8 && event.charCode ==0 || (event.charCode >= 48 && event.charCode <= 57))">
                <div id="card-number-error" class="error-message"></div>
            </div>
            <div class="form-group">
                <label for="expiry-date">Expiry Date</label>
                <input type="text" id="expiry-date" placeholder="00/00" maxlength="5" onkeypress="return (event.charCode !=8 && event.charCode ==0 || (event.charCode >= 48 && event.charCode <= 57))">
                <div id="expiry-date-error" class="error-message"></div>
            </div>
            <div class="form-group">
                <label for="cvv">CVV</label>
                <input type="text" id="cvv" placeholder="123" maxlength="4" onkeypress="return (event.charCode !=8 && event.charCode ==0 || (event.charCode >= 48 && event.charCode <= 57))">
                <div id="cvv-error" class="error-message"></div>
            </div>
            <div class="form-group">
                <label for="card-name">Name on Card</label>
                <input type="text" id="card-name" placeholder="John Doe">
                <div id="card-name-error" class="error-message"></div>
            </div>
        </div>

        <div id="wallet-payment" style="display: none;">
            <div class="form-group">
                <label for="wallet-pin">Wallet PIN (6 digits)</label>
                <input type="password" id="wallet-pin" placeholder="Enter 6-digit PIN" maxlength="6" onkeypress="return (event.charCode !=8 && event.charCode ==0 || (event.charCode >= 48 && event.charCode <= 57))">
                <div id="wallet-pin-error" class="error-message"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="payment-amount">Payment Amount (RM)</label>
            <input type="text" id="payment-amount" value="<?= number_format($total, 2) ?>" readonly>
            <div id="payment-amount-error" class="error-message"></div>
        </div>

        <button onclick="makePayment()" <?php if (empty($cartItems)) echo 'disabled'; ?>>Make Payment</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Event listeners for input formatting and validation
            document.getElementById('card-number').addEventListener('input', formatCardNumber);
            document.getElementById('expiry-date').addEventListener('input', formatExpiryDate);
            document.getElementById('cvv').addEventListener('input', function() { validateNumberInput(this, 4); });
            document.getElementById('wallet-pin').addEventListener('input', function() { validateNumberInput(this, 6); });
            document.getElementById('payment-amount').addEventListener('input', validateAmount);

            // Payment method switching
            document.querySelectorAll('.payment-method').forEach(method => {
                method.addEventListener('click', function() {
                    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('active'));
                    this.classList.add('active');
                    selectPaymentMethod(this.dataset.method);
                });
            });

            // Initialize with card payment
            selectPaymentMethod('card');

            // Format card number (16 digits, spaces every 4 digits)
            function formatCardNumber(input) {
                let value = input.value.replace(/\D/g, ''); // Remove non-digits
                if (value.length > 16) value = value.slice(0, 16); // Limit to 16 digits
                let formatted = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) formatted += ' ';
                    formatted += value[i];
                }
                input.value = formatted.trim();
            }

            // Format expiry date (MM/YY, auto-insert /)
            function formatExpiryDate(input) {
                let value = input.value.replace(/\D/g, '');
                if (value.length > 4) value = value.slice(0, 4);
                if (value.length === 0) {
                    input.value = '';
                } else if (value.length <= 2) {
                    input.value = value.padStart(2, '0');
                } else {
                    let month = value.slice(0, 2).padStart(2, '0');
                    let year = value.slice(2, 4).padStart(2, '0');
                    input.value = month + '/' + year;
                }
            }

            // Validate numeric input (e.g., CVV, PIN)
            function validateNumberInput(input, maxLength) {
                input.value = input.value.replace(/\D/g, '').slice(0, maxLength);
            }

            // Validate amount (read-only, but included for completeness)
            function validateAmount(input) {
                input.value = input.value.replace(/[^0-9.]/g, '');
                let parts = input.value.split('.');
                if (parts.length > 1) {
                    input.value = parts[0] + '.' + parts[1].slice(0, 2);
                }
            }

            // Luhn algorithm for card number validation
            function luhnCheck(number) {
                let sum = 0;
                let isEven = false;
                for (let i = number.length - 1; i >= 0; i--) {
                    let digit = parseInt(number[i]);
                    if (isEven) {
                        digit *= 2;
                        if (digit > 9) digit -= 9;
                    }
                    sum += digit;
                    isEven = !isEven;
                }
                return sum % 10 === 0;
            }

            // Switch payment method display
            function selectPaymentMethod(method) {
                document.getElementById('card-payment').style.display = method === 'card' ? 'block' : 'none';
                document.getElementById('wallet-payment').style.display = method === 'wallet' ? 'block' : 'none';
            }

            // Handle payment submission
            window.makePayment = function() {
                // Reset error states
                document.querySelectorAll('.error-message').forEach(el => {
                    el.style.display = 'none';
                });
                document.querySelectorAll('#card-payment input, #wallet-payment input').forEach(el => {
                    el.classList.remove('error');
                });

                const method = document.querySelector('.payment-method.active').dataset.method;
                let isValid = true;
                let formData = new FormData();
                formData.append('make_payment', 'true');
                formData.append('method', method);
                formData.append('amount', document.getElementById('payment-amount').value);

                if (method === 'card') {
                    const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
                    const expiry = document.getElementById('expiry-date').value;
                    const cvv = document.getElementById('cvv').value;
                    const name = document.getElementById('card-name').value.trim();

                    // Card number: exactly 16 digits, Luhn check
                    if (!cardNumber) {
                        document.getElementById('card-number-error').textContent = 'Card number is required';
                        document.getElementById('card-number-error').style.display = 'block';
                        document.getElementById('card-number').classList.add('error');
                        isValid = false;
                    } else if (!/^\d{16}$/.test(cardNumber)) {
                        document.getElementById('card-number-error').textContent = 'Card number must be exactly 16 digits';
                        document.getElementById('card-number-error').style.display = 'block';
                        document.getElementById('card-number').classList.add('error');
                        isValid = false;
                    } else if (!luhnCheck(cardNumber)) {
                        document.getElementById('card-number-error').textContent = 'Invalid card number (fails Luhn check)';
                        document.getElementById('card-number-error').style.display = 'block';
                        document.getElementById('card-number').classList.add('error');
                        isValid = false;
                    } else {
                        formData.append('card_number', cardNumber);
                    }

                    // Expiry: MM/YY format, not expired
                    if (!expiry || expiry === '') {
                        document.getElementById('expiry-date-error').textContent = 'Expiry date is required';
                        document.getElementById('expiry-date-error').style.display = 'block';
                        document.getElementById('expiry-date').classList.add('error');
                        isValid = false;
                    } else if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                        document.getElementById('expiry-date-error').textContent = 'Please enter a valid expiry date (MM/YY)';
                        document.getElementById('expiry-date-error').style.display = 'block';
                        document.getElementById('expiry-date').classList.add('error');
                        isValid = false;
                    } else {
                        const [month, year] = expiry.split('/');
                        const monthNum = parseInt(month);
                        const yearNum = parseInt(year);
                        const currentYear = new Date().getFullYear() % 100;
                        const currentMonth = new Date().getMonth() + 1;
                        if (monthNum < 1 || monthNum > 12 || 
                            (yearNum < currentYear) || 
                            (yearNum === currentYear && monthNum < currentMonth)) {
                            document.getElementById('expiry-date-error').textContent = 'Invalid or expired date';
                            document.getElementById('expiry-date-error').style.display = 'block';
                            document.getElementById('expiry-date').classList.add('error');
                            isValid = false;
                        } else {
                            formData.append('expiry_date', expiry);
                        }
                    }

                    // CVV: 3 or 4 digits
                    if (!cvv || !/^\d{3,4}$/.test(cvv)) {
                        document.getElementById('cvv-error').textContent = 'CVV must be 3 or 4 digits';
                        document.getElementById('cvv-error').style.display = 'block';
                        document.getElementById('cvv').classList.add('error');
                        isValid = false;
                    }

                    // Name: not empty, no numbers
                    if (!name || /\d/.test(name)) {
                        document.getElementById('card-name-error').textContent = 'Please enter a valid name (no numbers)';
                        document.getElementById('card-name-error').style.display = 'block';
                        document.getElementById('card-name').classList.add('error');
                        isValid = false;
                    }
                } else {
                    const pin = document.getElementById('wallet-pin').value;
                    // PIN: exactly 6 digits
                    if (!pin || !/^\d{6}$/.test(pin)) {
                        document.getElementById('wallet-pin-error').textContent = 'PIN must be exactly 6 digits';
                        document.getElementById('wallet-pin-error').style.display = 'block';
                        document.getElementById('wallet-pin').classList.add('error');
                        isValid = false;
                    } else {
                        formData.append('wallet_pin', pin);
                    }
                }

                const amount = document.getElementById('payment-amount').value;
                if (!amount || parseFloat(amount) <= 0) {
                    document.getElementById('payment-amount-error').textContent = 'Invalid payment amount';
                    document.getElementById('payment-amount-error').style.display = 'block';
                    document.getElementById('payment-amount').classList.add('error');
                    isValid = false;
                }

                if (!isValid) {
                    return;
                }

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showAlert('payment-alert', data.message, 'success');
                        // Clear form
                        document.getElementById('card-number').value = '';
                        document.getElementById('expiry-date').value = '';
                        document.getElementById('cvv').value = '';
                        document.getElementById('card-name').value = '';
                        document.getElementById('wallet-pin').value = '';
                        // Reset to card payment
                        document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('active'));
                        document.querySelector('.payment-method[data-method="card"]').classList.add('active');
                        selectPaymentMethod('card');
                        // Redirect to confirmation page
                        setTimeout(() => {
                            window.location.href = 'confirmation.php';
                        }, 2000);
                    } else {
                        showAlert('payment-alert', data.message || 'Failed to process payment. Please try again.', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('payment-alert', 'An error occurred. Please try again.', 'danger');
                });
            };

            // Show alert message
            function showAlert(containerId, message, type) {
                const container = document.getElementById(containerId);
                container.innerHTML = `
                    <div class="alert alert-${type}">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                        ${message}
                    </div>
                `;
                container.classList.remove('hidden');
                setTimeout(() => {
                    container.classList.add('hidden');
                }, 5000);
            }
        });
    </script>
</body>
</html>