<?php
session_start();
require '../db_connect.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

// Handle payment method removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_payment_method'])) {
    $methodId = (int) $_POST['method_id'];
    
    // Delete the payment method for the customer
    $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $methodId, $customerId);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove payment method']);
    }
    exit();
}

// Handle new payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment_method'])) {
    $cardNumber = preg_replace('/\D/', '', $_POST['new_card_number']);
    $expiry = $_POST['new_expiry'];
    $cardName = $_POST['new_card_name'];
    
    // Extract last 4 digits of card number
    $cardLastFour = substr($cardNumber, -4);
    
    // Save to payment_methods table
    $stmt = $conn->prepare("
        INSERT INTO payment_methods (customer_id, method_type, card_last_four, card_name, expiry_date)
        VALUES (?, 'card', ?, ?, ?)
    ");
    $stmt->bind_param("isss", $customerId, $cardLastFour, $cardName, $expiry);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save payment method']);
    }
    exit();
}

// Fetch saved payment methods for the customer
$paymentMethods = [];
$stmt = $conn->prepare("
    SELECT id, method_type, card_last_four, card_name, expiry_date
    FROM payment_methods
    WHERE customer_id = ?
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $paymentMethods[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Methods - Brizo Fast Food Melaka</title>
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
        .payment-method-item {
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-cart {
            display: inline-block;
            margin: 20px 0;
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="cart.php" class="back-cart">⬅️ Back to Cart</a>
        <h2>Payment Methods</h2>
        <div id="methods-alert"></div>

        <h3>Saved Payment Methods</h3>
        <?php if (empty($paymentMethods)): ?>
            <p>No saved payment methods.</p>
        <?php else: ?>
            <?php foreach ($paymentMethods as $method): ?>
                <div class="payment-method-item">
                    <span>Card ending in <?= htmlspecialchars($method['card_last_four']) ?> (Expires: <?= htmlspecialchars($method['expiry_date']) ?>)</span>
                    <button onclick="removePaymentMethod(<?= $method['id'] ?>)">Remove</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3>Add New Payment Method</h3>
        <div class="form-group">
            <label for="new-card-number">Card Number</label>
            <input type="text" id="new-card-number" placeholder="1234 5678 9012 3456" maxlength="19">
            <div id="new-card-number-error" class="error-message"></div>
        </div>
        <div class="form-group">
            <label for="new-expiry">Expiry Date</label>
            <input type="text" id="new-expiry" placeholder="MM/YY" maxlength="5">
            <div id="new-expiry-error" class="error-message"></div>
        </div>
        <div class="form-group">
            <label for="new-cvv">CVV</label>
            <input type="text" id="new-cvv" placeholder="123" maxlength="4">
            <div id="new-cvv-error" class="error-message"></div>
        </div>
        <div class="form-group">
            <label for="new-card-name">Name on Card</label>
            <input type="text" id="new-card-name" placeholder="John Doe">
            <div id="new-card-name-error" class="error-message"></div>
        </div>
        <button onclick="savePaymentMethod()">Save Payment Method</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('new-card-number').addEventListener('input', formatCardNumber);
            document.getElementById('new-expiry').addEventListener('input', formatExpiryDate);
            document.getElementById('new-cvv').addEventListener('input', function() { validateNumberInput(this, 4); });

            function formatCardNumber(input) {
                let value = input.value.replace(/\D/g, '');
                let formatted = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) formatted += ' ';
                    formatted += value[i];
                }
                input.value = formatted.trim();
            }

            function formatExpiryDate(input) {
                let value = input.value.replace(/\D/g, '');
                if (value.length > 2) {
                    input.value = value.slice(0, 2) + '/' + value.slice(2, 4);
                } else {
                    input.value = value;
                }
            }

            function validateNumberInput(input, maxLength) {
                input.value = input.value.replace(/\D/g, '').slice(0, maxLength);
            }

            window.savePaymentMethod = function() {
                document.querySelectorAll('.error-message').forEach(el => {
                    el.style.display = 'none';
                });
                document.querySelectorAll('input').forEach(el => {
                    el.classList.remove('error');
                });

                const cardNumber = document.getElementById('new-card-number').value.replace(/\s/g, '');
                const expiry = document.getElementById('new-expiry').value.trim();
                const cvv = document.getElementById('new-cvv').value.trim();
                const name = document.getElementById('new-card-name').value.trim();

                let isValid = true;

                if (!cardNumber || cardNumber.length !== 16) {
                    document.getElementById('new-card-number-error').textContent = 'Please enter a valid 16-digit card number';
                    document.getElementById('new-card-number-error').style.display = 'block';
                    document.getElementById('new-card-number').classList.add('error');
                    isValid = false;
                }

                if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
                    document.getElementById('new-expiry-error').textContent = 'Please enter a valid expiry date (MM/YY)';
                    document.getElementById('new-expiry-error').style.display = 'block';
                    document.getElementById('new-expiry').classList.add('error');
                    isValid = false;
                }

                if (!cvv || !/^\d{3,4}$/.test(cvv)) {
                    document.getElementById('new-cvv-error').textContent = 'Please enter a valid CVV (3 or 4 digits)';
                    document.getElementById('new-cvv-error').style.display = 'block';
                    document.getElementById('new-cvv').classList.add('error');
                    isValid = false;
                }

                if (!name || /\d/.test(name)) {
                    document.getElementById('new-card-name-error').textContent = 'Please enter a valid name (no numbers)';
                    document.getElementById('new-card-name-error').style.display = 'block';
                    document.getElementById('new-card-name').classList.add('error');
                    isValid = false;
                }

                if (!isValid) {
                    return;
                }

                const formData = new FormData();
                formData.append('save_payment_method', 'true');
                formData.append('new_card_number', cardNumber);
                formData.append('new_expiry', expiry);
                formData.append('new_cvv', cvv);
                formData.append('new_card_name', name);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showAlert('methods-alert', 'Payment method added successfully', 'success');
                        document.getElementById('new-card-number').value = '';
                        document.getElementById('new-expiry').value = '';
                        document.getElementById('new-cvv').value = '';
                        document.getElementById('new-card-name').value = '';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showAlert('methods-alert', data.message || 'Failed to add payment method', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('methods-alert', 'An error occurred. Please try again.', 'danger');
                });
            };

            window.removePaymentMethod = function(id) {
                if (confirm('Are you sure you want to remove this payment method?')) {
                    const formData = new FormData();
                    formData.append('remove_payment_method', 'true');
                    formData.append('method_id', id);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            showAlert('methods-alert', 'Payment method removed successfully', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showAlert('methods-alert', data.message || 'Failed to remove payment method', 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('methods-alert', 'An error occurred. Please try again.', 'danger');
                    });
                }
            };

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