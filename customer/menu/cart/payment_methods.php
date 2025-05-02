<?php
declare(strict_types=1);
session_start();
session_regenerate_id(true); // Prevent session fixation

// Check for database connection
try {
    require '../db_connect.php';
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header('Location: /Online-Fast-Food/customer/login.php');
    exit();
}

// Handle adding a new payment method
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_method'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $methodType = $_POST['method_type'] ?? '';
        $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $cardType = $_POST['card_type'] ?? '';
        $bankName = $_POST['bank_name'] ?? '';
        $walletType = $_POST['wallet_type'] ?? '';
        $phoneNumber = $_POST['wallet_phone'] ?? '';
        $expiry = $_POST['expiry_date'] ?? '';
        $cvv = $_POST['cvv'] ?? '';

        // Validate based on method type
        if (!in_array($methodType, ['card', 'online_banking', 'digital_wallet'])) {
            $errors[] = 'Invalid payment method type';
        }

        if ($methodType === 'card') {
            if (!preg_match('/^\d{16}$/', $cardNumber) || !luhnCheck($cardNumber)) {
                $errors[] = 'Invalid card number';
            }
            if (!in_array($cardType, ['visa', 'mastercard', 'jcb', 'amex', 'mydebit', 'unionpay'])) {
                $errors[] = 'Invalid card type';
            }
            if (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
                $errors[] = 'Invalid expiry date';
            } else {
                $expiryParts = explode('/', $expiry);
                $month = (int)$expiryParts[0];
                $year = (int)$expiryParts[1];
                $currentYear = (int)date('y');
                $currentMonth = (int)date('m');
                if ($month < 1 || $month > 12 || $year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
                    $errors[] = 'Card is expired';
                }
            }
            if (!preg_match('/^\d{3,4}$/', $cvv)) {
                $errors[] = 'Invalid CVV';
            }
        } elseif ($methodType === 'online_banking') {
            if (empty($bankName) || !in_array($bankName, [
                'maybank2u', 'cimbclicks', 'rhb', 'publicbank', 'hongleong',
                'ambank', 'mybsn', 'bankrakyat', 'uob', 'affinbank', 'bankislam',
                'hsbc', 'banknegaramalaysia', 'alliancebank', 'ocbc', 'bankmuamalat',
                'standardchartered', 'citibank', 'alrajhi', 'bankrakyatbaloyete'
            ])) {
                $errors[] = 'Invalid bank selection';
            }
        } elseif ($methodType === 'digital_wallet') {
            if (empty($walletType) || !in_array($walletType, ['shopeepay', 'tng', 'grabpay', 'boost', 'googlepay'])) {
                $errors[] = 'Invalid wallet selection';
            }
            if ($phoneNumber && !preg_match('/^01[0-9]{8,9}$/', $phoneNumber)) {
                $errors[] = 'Invalid phone number for wallet';
            }
        }

        if (empty($errors)) {
            try {
                if ($methodType === 'card') {
                    $cardLastFour = substr($cardNumber, -4);
                    $stmt = $conn->prepare('
                        INSERT INTO payment_methods (customer_id, method_type, card_type, card_last_four, expiry_date)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->bind_param('issss', $customerId, $methodType, $cardType, $cardLastFour, $expiry);
                    $success = "Card (ending in $cardLastFour) added successfully";
                } elseif ($methodType === 'online_banking') {
                    $stmt = $conn->prepare('
                        INSERT INTO payment_methods (customer_id, method_type, bank_name)
                        VALUES (?, ?, ?)
                    ');
                    $stmt->bind_param('iss', $customerId, $methodType, $bankName);
                    $success = "Online banking (" . ucfirst(str_replace(['maybank2u', 'cimbclicks'], ['Maybank2u', 'CIMB Clicks'], $bankName)) . ") added. You will be redirected to the bank during checkout.";
                } elseif ($methodType === 'digital_wallet') {
                    $stmt = $conn->prepare('
                        INSERT INTO payment_methods (customer_id, method_type, wallet_type, phone_number)
                        VALUES (?, ?, ?, ?)
                    ');
                    $stmt->bind_param('isss', $customerId, $methodType, $walletType, $phoneNumber);
                    $success = "Digital wallet (" . ucfirst(str_replace('tng', 'Touch \'n Go', $walletType)) . ") added. Use your wallet app during checkout.";
                }

                if ($stmt->execute()) {
                    // Success message already set above
                } else {
                    $errors[] = 'Failed to add payment method';
                }
                $stmt->close();
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Handle deleting a payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment_method'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $methodId = (int)($_POST['method_id'] ?? 0);
        try {
            $stmt = $conn->prepare('DELETE FROM payment_methods WHERE id = ? AND customer_id = ?');
            $stmt->bind_param('ii', $methodId, $customerId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $success = 'Payment method deleted successfully';
            } else {
                $errors[] = 'Failed to delete payment method';
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch saved payment methods
try {
    $stmt = $conn->prepare('
        SELECT id, method_type, card_type, bank_name, wallet_type, card_last_four, expiry_date, phone_number
        FROM payment_methods
        WHERE customer_id = ?
        ORDER BY created_at DESC
    ');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $paymentMethods = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
}

// Luhn Algorithm for card number validation
function luhnCheck(string $number): bool {
    $sum = 0;
    $length = strlen($number);
    $parity = $length % 2;
    for ($i = $length - 1; $i >= 0; $i--) {
        $digit = (int)$number[$i];
        if ($i % 2 === $parity) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    return $sum % 10 === 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title>Payment Methods - Brizo Fast Food Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }
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
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        input:focus, select:focus {
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
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }
        button:hover {
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
        .card-icon {
            margin-right: 8px;
        }
        .select-option {
            display: flex;
            align-items: center;
        }
        .select-option i {
            margin-right: 8px;
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
        <a href="cart.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Cart</a>
        <h2>Manage Payment Methods</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h3>Saved Payment Methods</h3>
        <?php if (empty($paymentMethods)): ?>
            <p>No payment methods saved.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Method Type</th>
                        <th>Details</th>
                        <th>Expiry Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentMethods as $method): ?>
                        <tr>
                            <td>
                                <?php
                                $iconClass = '';
                                $methodLabel = '';
                                if ($method['method_type'] === 'card') {
                                    switch ($method['card_type']) {
                                        case 'visa':
                                            $iconClass = 'fa-cc-visa';
                                            break;
                                        case 'mastercard':
                                            $iconClass = 'fa-cc-mastercard';
                                            break;
                                        case 'jcb':
                                            $iconClass = 'fa-cc-jcb';
                                            break;
                                        case 'amex':
                                            $iconClass = 'fa-cc-amex';
                                            break;
                                        case 'unionpay':
                                        case 'mydebit':
                                            $iconClass = 'fa-credit-card';
                                            break;
                                    }
                                    $methodLabel = ucfirst($method['card_type']);
                                } elseif ($method['method_type'] === 'online_banking') {
                                    $iconClass = 'fa-university';
                                    $methodLabel = match ($method['bank_name']) {
                                        'maybank2u' => 'Maybank2u',
                                        'cimbclicks' => 'CIMB Clicks',
                                        'rhb' => 'RHB',
                                        'publicbank' => 'Public Bank',
                                        'hongleong' => 'Hong Leong',
                                        'ambank' => 'AmBank',
                                        'mybsn' => 'MyBSN',
                                        'bankrakyat' => 'Bank Rakyat',
                                        'uob' => 'UOB',
                                        'affinbank' => 'Affin Bank',
                                        'bankislam' => 'Bank Islam',
                                        'hsbc' => 'HSBC Online',
                                        'banknegaramalaysia' => 'Bank Negara Malaysia',
                                        'alliancebank' => 'Alliance Bank',
                                        'ocbc' => 'OCBC Bank',
                                        'bankmuamalat' => 'Bank Muamalat',
                                        'standardchartered' => 'Standard Chartered',
                                        'citibank' => 'Citibank',
                                        'alrajhi' => 'Al-Rajhi Bank',
                                        'bankrakyatbaloyete' => 'Bank Rakyat Baloyete',
                                        default => ucfirst($method['bank_name']),
                                    };
                                } elseif ($method['method_type'] === 'digital_wallet') {
                                    $iconClass = 'fa-wallet';
                                    $methodLabel = ucfirst(str_replace('tng', 'Touch \'n Go', $method['wallet_type']));
                                }
                                ?>
                                <i class="fas <?= htmlspecialchars($iconClass) ?> card-icon"></i>
                                <?= htmlspecialchars($methodLabel) ?>
                            </td>
                            <td>
                                <?php if ($method['method_type'] === 'card'): ?>
                                    **** **** **** <?= htmlspecialchars($method['card_last_four']) ?>
                                <?php elseif ($method['method_type'] === 'digital_wallet' && $method['phone_number']): ?>
                                    Phone: <?= htmlspecialchars($method['phone_number']) ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $method['method_type'] === 'card' ? htmlspecialchars($method['expiry_date']) : 'N/A' ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="method_id" value="<?= (int)$method['id'] ?>">
                                    <button type="submit" name="delete_payment_method" onclick="return confirm('Are you sure you want to delete this payment method?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3>Add New Payment Method</h3>
        <form method="POST" id="payment-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="method-type">Payment Method Type</label>
                <select id="method-type" name="method_type" required>
                    <option value="" disabled selected>Select Payment Method Type</option>
                    <option value="card">Credit/Debit Card</option>
                    <option value="online_banking">Online Banking</option>
                    <option value="digital_wallet">Digital Wallet</option>
                </select>
                <div id="method-type-error" class="error-message"></div>
            </div>
            <div id="card-fields" class="hidden">
                <div class="form-group">
                    <label for="card-type">Card Type</label>
                    <select id="card-type" name="card_type">
                        <option value="" disabled selected>Select Card Type</option>
                        <option value="visa">Visa</option>
                        <option value="mastercard">MasterCard</option>
                        <option value="jcb">JCB</option>
                        <option value="amex">American Express</option>
                        <option value="mydebit">MyDebit</option>
                        <option value="unionpay">UnionPay</option>
                    </select>
                    <div id="card-type-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="card-number">Card Number</label>
                    <input type="text" id="card-number" name="card_number" maxlength="19" placeholder="e.g., 4556 7375 8689 9855" inputmode="numeric">
                    <div id="card-number-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="expiry-date">Expiry Date (MM/YY)</label>
                    <input type="text" id="expiry-date" name="expiry_date" maxlength="5" placeholder="e.g., 12/27" inputmode="numeric">
                    <div id="expiry-date-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="cvv">CVV</label>
                    <input type="text" id="cvv" name="cvv" maxlength="4" placeholder="e.g., 123" inputmode="numeric">
                    <div id="cvv-error" class="error-message"></div>
                </div>
            </div>
            <div id="online-banking-fields" class="hidden">
                <div class="form-group">
                    <label for="bank-name">Bank</label>
                    <select id="bank-name" name="bank_name" required>
                        <option value="" disabled selected>Select Bank</option>
                        <option value="maybank2u"><i class="fas fa-university"></i> Maybank2u</option>
                        <option value="cimbclicks"><i class="fas fa-university"></i> CIMB Clicks</option>
                        <option value="rhb"><i class="fas fa-university"></i> RHB</option>
                        <option value="publicbank"><i class="fas fa-university"></i> Public Bank</option>
                        <option value="hongleong"><i class="fas fa-university"></i> Hong Leong</option>
                        <option value="ambank"><i class="fas fa-university"></i> AmBank</option>
                        <option value="mybsn"><i class="fas fa-university"></i> MyBSN</option>
                        <option value="bankrakyat"><i class="fas fa-university"></i> Bank Rakyat</option>
                        <option value="uob"><i class="fas fa-university"></i> UOB</option>
                        <option value="affinbank"><i class="fas fa-university"></i> Affin Bank</option>
                        <option value="bankislam"><i class="fas fa-university"></i> Bank Islam</option>
                        <option value="hsbc"><i class="fas fa-university"></i> HSBC Online</option>
                        <option value="banknegaramalaysia"><i class="fas fa-university"></i> Bank Negara Malaysia</option>
                        <option value="alliancebank"><i class="fas fa-university"></i> Alliance Bank</option>
                        <option value="ocbc"><i class="fas fa-university"></i> OCBC Bank</option>
                        <option value="bankmuamalat"><i class="fas fa-university"></i> Bank Muamalat</option>
                        <option value="standardchartered"><i class="fas fa-university"></i> Standard Chartered</option>
                        <option value="citibank"><i class="fas fa-university"></i> Citibank</option>
                        <option value="alrajhi"><i class="fas fa-university"></i> Al-Rajhi Bank</option>
                        <option value="bankrakyatbaloyete"><i class="fas fa-university"></i> Bank Rakyat Baloyete</option>
                    </select>
                    <div id="bank-name-error" class="error-message"></div>
                </div>
            </div>
            <div id="digital-wallet-fields" class="hidden">
                <div class="form-group">
                    <label for="wallet-type">Wallet</label>
                    <select id="wallet-type" name="wallet_type" required>
                        <option value="" disabled selected>Select Wallet</option>
                        <option value="shopeepay"><i class="fas fa-wallet"></i> ShopeePay</option>
                        <option value="tng"><i class="fas fa-wallet"></i> Touch 'n Go eWallet</option>
                        <option value="grabpay"><i class="fas fa-wallet"></i> GrabPay</option>
                        <option value="boost"><i class="fas fa-wallet"></i> Boost</option>
                        <option value="googlepay"><i class="fas fa-wallet"></i> Google Pay</option>
                    </select>
                    <div id="wallet-type-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="wallet-phone">Phone Number (Optional)</label>
                    <input type="text" id="wallet-phone" name="wallet_phone" placeholder="e.g., 0123456789" inputmode="numeric">
                    <div id="wallet-phone-error" class="error-message"></div>
                </div>
            </div>
            <button type="submit" name="add_payment_method">Add Payment Method</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const methodTypeSelect = document.getElementById('method-type');
            const cardFields = document.getElementById('card-fields');
            const onlineBankingFields = document.getElementById('online-banking-fields');
            const digitalWalletFields = document.getElementById('digital-wallet-fields');
            const cardNumberInput = document.getElementById('card-number');
            const expiryDateInput = document.getElementById('expiry-date');
            const cvvInput = document.getElementById('cvv');
            const cardTypeSelect = document.getElementById('card-type');
            const bankNameSelect = document.getElementById('bank-name');
            const walletTypeSelect = document.getElementById('wallet-type');
            const walletPhoneInput = document.getElementById('wallet-phone');
            const form = document.getElementById('payment-form');

            // Toggle fields based on method type
            methodTypeSelect.addEventListener('change', () => {
                cardFields.classList.add('hidden');
                onlineBankingFields.classList.add('hidden');
                digitalWalletFields.classList.add('hidden');

                if (methodTypeSelect.value === 'card') {
                    cardFields.classList.remove('hidden');
                    cardTypeSelect.required = true;
                    cardNumberInput.required = true;
                    expiryDateInput.required = true;
                    cvvInput.required = true;
                    bankNameSelect.required = false;
                    walletTypeSelect.required = false;
                    walletPhoneInput.required = false;
                } else if (methodTypeSelect.value === 'online_banking') {
                    onlineBankingFields.classList.remove('hidden');
                    bankNameSelect.required = true;
                    cardTypeSelect.required = false;
                    cardNumberInput.required = false;
                    expiryDateInput.required = false;
                    cvvInput.required = false;
                    walletTypeSelect.required = false;
                    walletPhoneInput.required = false;
                } else if (methodTypeSelect.value === 'digital_wallet') {
                    digitalWalletFields.classList.remove('hidden');
                    walletTypeSelect.required = true;
                    cardTypeSelect.required = false;
                    cardNumberInput.required = false;
                    expiryDateInput.required = false;
                    cvvInput.required = false;
                    bankNameSelect.required = false;
                    walletPhoneInput.required = false;
                }
            });

            // Format card number
            cardNumberInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '').slice(0, 16);
                e.target.value = value.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
                validateCardNumber();
            });

            // Format expiry date
            expiryDateInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '').slice(0, 4);
                if (value.length > 2) {
                    e.target.value = value.slice(0, 2) + '/' + value.slice(2);
                } else {
                    e.target.value = value;
                }
                validateExpiryDate();
            });

            // Restrict CVV to numbers
            cvvInput.addEventListener('input', () => {
                cvvInput.value = cvvInput.value.replace(/\D/g, '').slice(0, 4);
                validateCvv();
            });

            // Validate phone number
            walletPhoneInput.addEventListener('input', () => {
                const value = walletPhoneInput.value;
                const error = document.getElementById('wallet-phone-error');
                if (value && !/^01[0-9]{8,9}$/.test(value)) {
                    showError('wallet-phone-error', 'Invalid Malaysian phone number');
                } else {
                    hideError('wallet-phone-error');
                }
            });

            // Validate card type
            cardTypeSelect.addEventListener('change', () => {
                validateCardType();
            });

            // Validate bank name
            bankNameSelect.addEventListener('change', () => {
                validateBankName();
            });

            // Validate wallet type
            walletTypeSelect.addEventListener('change', () => {
                validateWalletType();
            });

            // Form submission validation
            form.addEventListener('submit', (e) => {
                let hasError = false;

                if (methodTypeSelect.value === 'card') {
                    if (!validateCardType()) hasError = true;
                    if (!validateCardNumber()) hasError = true;
                    if (!validateExpiryDate()) hasError = true;
                    if (!validateCvv()) hasError = true;
                } else if (methodTypeSelect.value === 'online_banking') {
                    if (!validateBankName()) hasError = true;
                } else if (methodTypeSelect.value === 'digital_wallet') {
                    if (!validateWalletType()) hasError = true;
                    if (walletPhoneInput.value && !/^01[0-9]{8,9}$/.test(walletPhoneInput.value)) {
                        showError('wallet-phone-error', 'Invalid Malaysian phone number');
                        hasError = true;
                    }
                } else {
                    showError('method-type-error', 'Please select a payment method type');
                    hasError = true;
                }

                if (hasError) {
                    e.preventDefault();
                }
            });

            function validateCardType() {
                const error = document.getElementById('card-type-error');
                if (!cardTypeSelect.value) {
                    showError('card-type-error', 'Please select a card type');
                    return false;
                }
                hideError('card-type-error');
                return true;
            }

            function validateCardNumber() {
                const value = cardNumberInput.value.replace(/\D/g, '');
                const error = document.getElementById('card-number-error');
                if (value.length !== 16) {
                    showError('card-number-error', 'Card number must be 16 digits');
                    return false;
                }
                hideError('card-number-error');
                return true;
            }

            function validateExpiryDate() {
                const value = expiryDateInput.value;
                const error = document.getElementById('expiry-date-error');
                if (!/^\d{2}\/\d{2}$/.test(value)) {
                    showError('expiry-date-error', 'Invalid expiry date format (MM/YY)');
                    return false;
                }
                const [month, year] = value.split('/').map(Number);
                const currentYear = new Date().getFullYear() % 100;
                const currentMonth = new Date().getMonth() + 1;
                if (month < 1 || month > 12 || year < currentYear || (year === currentYear && month < currentMonth)) {
                    showError('expiry-date-error', 'Card is expired');
                    return false;
                }
                hideError('expiry-date-error');
                return true;
            }

            function validateCvv() {
                const value = cvvInput.value;
                const error = document.getElementById('cvv-error');
                if (!/^\d{3,4}$/.test(value)) {
                    showError('cvv-error', 'CVV must be 3 or 4 digits');
                    return false;
                }
                hideError('cvv-error');
                return true;
            }

            function validateBankName() {
                const error = document.getElementById('bank-name-error');
                if (!bankNameSelect.value) {
                    showError('bank-name-error', 'Please select a bank');
                    return false;
                }
                hideError('bank-name-error');
                return true;
            }

            function validateWalletType() {
                const error = document.getElementById('wallet-type-error');
                if (!walletTypeSelect.value) {
                    showError('wallet-type-error', 'Please select a wallet');
                    return false;
                }
                hideError('wallet-type-error');
                return true;
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
        });
    </script>
</body>
</html>