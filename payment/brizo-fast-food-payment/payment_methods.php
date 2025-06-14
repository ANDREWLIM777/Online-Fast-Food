<?php
ob_start();
session_start();
require '../../customer/menu/db_connect.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database connection failed"));
    exit();
}

// Check session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: /Online-Fast-Food/login.php?message=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: /Online-Fast-Food/login.php");
    exit();
}

$customerId = (int)$_SESSION['customer_id'];

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Log file for debugging
$logFile = 'payment_methods_errors.log';
$logMessage = function($message) use ($logFile) {
    $message = filter_var($message, FILTER_SANITIZE_STRING);
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 3, $logFile);
};

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

// Fetch payment methods
$paymentMethods = [];
$stmt = $conn->prepare("
    SELECT id, method_type, card_type, card_last_four, expiry_date, bank_name, wallet_type, phone_number
    FROM payment_methods
    WHERE customer_id = ?
");
if (!$stmt) {
    $logMessage("Prepare failed for payment methods fetch: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
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

    // Log POST data for debugging
    $logMessage("Add payment method request: " . json_encode($_POST));

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed. Sent: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: " . $_SESSION['csrf_token']);
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $methodType = filter_var(trim($_POST['method_type'] ?? ''), FILTER_SANITIZE_STRING);
    if (!in_array($methodType, ['card', 'online_banking', 'digital_wallet'])) {
        $logMessage("Invalid payment method type: $methodType");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid payment method type']);
        exit();
    }

    try {
        $conn->begin_transaction();

        // Check for duplicate payment method
        $duplicateCheckStmt = null;
        if ($methodType === 'card') {
            $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            $cardLastFour = substr($cardNumber, -4);
            $cardType = filter_var(trim($_POST['card_type'] ?? ''), FILTER_SANITIZE_STRING);
            $cardExpiry = filter_var(trim($_POST['expiry_date'] ?? ''), FILTER_SANITIZE_STRING);
            $duplicateCheckStmt = $conn->prepare("
                SELECT id FROM payment_methods
                WHERE customer_id = ? AND method_type = 'card'
                AND card_last_four = ? AND card_type = ? AND expiry_date = ?
            ");
            $duplicateCheckStmt->bind_param("isss", $customerId, $cardLastFour, $cardType, $cardExpiry);
        } elseif ($methodType === 'online_banking') {
            $bankName = filter_var(trim($_POST['bank_name'] ?? ''), FILTER_SANITIZE_STRING);
            $duplicateCheckStmt = $conn->prepare("
                SELECT id FROM payment_methods
                WHERE customer_id = ? AND method_type = 'online_banking'
                AND bank_name = ?
            ");
            $duplicateCheckStmt->bind_param("is", $customerId, $bankName);
        } elseif ($methodType === 'digital_wallet') {
            $walletType = filter_var(trim($_POST['wallet_type'] ?? ''), FILTER_SANITIZE_STRING);
            $phoneNumber = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');
            $duplicateCheckStmt = $conn->prepare("
                SELECT id FROM payment_methods
                WHERE customer_id = ? AND method_type = 'digital_wallet'
                AND wallet_type = ? AND phone_number = ?
            ");
            $duplicateCheckStmt->bind_param("iss", $customerId, $walletType, $phoneNumber);
        }

        if ($duplicateCheckStmt) {
            $duplicateCheckStmt->execute();
            $result = $duplicateCheckStmt->get_result();
            if ($result->num_rows > 0) {
                $logMessage("Duplicate payment method detected for customer_id=$customerId, method_type=$methodType");
                $conn->rollback();
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'This payment method is already saved']);
                $duplicateCheckStmt->close();
                exit();
            }
            $duplicateCheckStmt->close();
        }

        // Prepare the insert statement
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
            $expiry = filter_var(trim($_POST['expiry_date'] ?? ''), FILTER_SANITIZE_STRING);
            $cvv = filter_var(trim($_POST['cvv'] ?? ''), FILTER_SANITIZE_STRING);
            $cardName = filter_var(trim($_POST['card_name'] ?? ''), FILTER_SANITIZE_STRING);
            $cardType = filter_var(trim($_POST['card_type'] ?? ''), FILTER_SANITIZE_STRING);

            $maskedCardNumber = '**** **** **** ' . substr($cardNumber, -4);
            $logMessage("Adding card - Number: $maskedCardNumber, Expiry: $expiry, Name: $cardName, Type: $cardType");

            if (empty($cardNumber) || !preg_match('/^\d{16}$/', $cardNumber)) {
                $logMessage("Invalid card number: $cardNumber");
                throw new Exception('Card number must be 16 digits');
            }
            if (!luhnCheck($cardNumber)) {
                $logMessage("Card number failed Luhn check: $cardNumber");
                throw new Exception('Invalid card number (failed validation check)');
            }
            if (empty($cardType) || !in_array($cardType, ['visa', 'mastercard', 'jcb', 'amex', 'mydebit', 'unionpay'])) {
                $logMessage("Invalid card type: $cardType");
                throw new Exception('Invalid card type');
            }
            if (empty($expiry) || !preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
                $logMessage("Invalid expiry date format: $expiry");
                throw new Exception('Invalid expiry date format (MM/YY)');
            }
            $expiryParts = explode('/', $expiry);
            $month = (int)$expiryParts[0];
            $year = (int)$expiryParts[1];
            $currentYear = (int)date('y');
            $currentMonth = (int)date('m');
            if ($month < 1 || $month > 12 || $year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
                $logMessage("Expired card: $month/$year");
                throw new Exception('Card is expired');
            }
            if (empty($cvv) || !preg_match('/^\d{3,4}$/', $cvv)) {
                $logMessage("Invalid CVV: $cvv");
                throw new Exception('CVV must be 3 or 4 digits');
            }
            if (empty($cardName) || preg_match('/\d/', $cardName)) {
                $logMessage("Invalid card name: $cardName");
                throw new Exception('Invalid name on card (no numbers allowed)');
            }

            $cardLastFour = substr($cardNumber, -4);
            $cardExpiry = $expiry;
        } elseif ($methodType === 'online_banking') {
            $bankName = filter_var(trim($_POST['bank_name'] ?? ''), FILTER_SANITIZE_STRING);
            $allowedBanks = ['maybank2u', 'cimbclicks', 'rhb', 'publicbank', 'hongleong', 'ambank', 'mybsn', 'bankrakyat', 'uob', 'affinbank', 'bankislam', 'hsbc', 'banknegaramalaysia', 'alliancebank', 'ocbc', 'bankmuamalat', 'standardchartered', 'citibank', 'alrajhi', 'bankrakyatbaloyete'];
            if (empty($bankName) || !in_array($bankName, $allowedBanks)) {
                $logMessage("Invalid bank name: $bankName");
                throw new Exception('Invalid bank name');
            }
        } elseif ($methodType === 'digital_wallet') {
            $walletType = filter_var(trim($_POST['wallet_type'] ?? ''), FILTER_SANITIZE_STRING);
            $phoneNumber = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');
            $allowedWallets = ['shopeepay', 'tng', 'grabpay', 'boost', 'googlepay'];
            if (empty($walletType) || !in_array($walletType, $allowedWallets)) {
                $logMessage("Invalid wallet type: $walletType");
                throw new Exception('Invalid wallet type');
            }
            if (empty($phoneNumber) || !preg_match('/^\d{10,15}$/', $phoneNumber)) {
                $logMessage("Invalid phone number: $phoneNumber");
                throw new Exception('Phone number must be 10-15 digits');
            }
            $logMessage("Adding digital wallet - Wallet Type: $walletType, Phone Number: $phoneNumber");
        }

        $stmt->bind_param("isssssss", $customerId, $methodType, $cardType, $cardLastFour, $cardExpiry, $bankName, $walletType, $phoneNumber);

        if ($stmt->execute()) {
            $newMethodId = $stmt->insert_id;
            $stmt->close();
            $conn->commit();
            $logMessage("Payment method added successfully: ID $newMethodId, method_type=$methodType");

            $stmt = $conn->prepare("
                SELECT id, method_type, card_type, card_last_four, expiry_date, bank_name, wallet_type, phone_number
                FROM payment_methods
                WHERE id = ?
            ");
            if (!$stmt) {
                $logMessage("Prepare failed for payment_methods select: " . $conn->error);
                throw new Exception('Database error: Unable to retrieve new payment method');
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
                $displayText = ucfirst($newMethod['wallet_type']) . ($newMethod['phone_number'] ? ' (' . $newMethod['phone_number'] . ')' : '');
            }

            ob_end_clean();
            echo json_encode([
                'status' => 'success',
                'message' => $displayText . ' added successfully',
                'payment_method' => $newMethod
            ]);
        } else {
            $logMessage("Failed to add payment method: " . $stmt->error);
            $conn->rollback();
            throw new Exception('Failed to add payment method');
        }
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Exception while adding payment method: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// Handle deleting a payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment_method'])) {
    header('Content-Type: application/json');

    // Log POST data for debugging
    $logMessage("Delete payment method request: " . json_encode($_POST));

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed. Sent: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: " . $_SESSION['csrf_token']);
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
    if ($paymentMethodId <= 0) {
        $logMessage("Invalid payment method ID: $paymentMethodId");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid payment method ID']);
        exit();
    }

    try {
        $conn->begin_transaction();

        // Check if payment method exists and belongs to the customer
        $stmt = $conn->prepare("
            SELECT id FROM payment_methods
            WHERE id = ? AND customer_id = ?
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for payment method check: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("ii", $paymentMethodId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $logMessage("Payment method ID $paymentMethodId not found or does not belong to customer $customerId");
            $conn->rollback();
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Payment method not found']);
            exit();
        }
        $stmt->close();

        // Delete payment method
        $stmt = $conn->prepare("
            DELETE FROM payment_methods
            WHERE id = ? AND customer_id = ?
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for payment method delete: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("ii", $paymentMethodId, $customerId);
        if ($stmt->execute()) {
            $conn->commit();
            $logMessage("Payment method ID $paymentMethodId deleted successfully");
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Payment method deleted successfully']);
        } else {
            $logMessage("Failed to delete payment method ID $paymentMethodId: " . $stmt->error);
            $conn->rollback();
            throw new Exception('Failed to delete payment method');
        }
        $stmt->close();
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Exception while deleting payment method: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods - Brizo Fast Food Melaka</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .message {
            transition: opacity 0.3s ease-in-out;
        }
        .invalid {
            border-color: #ff4757 !important;
            background-color: #fff5f5;
        }
        .spinner {
            display: none;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ff4757;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .btn-primary {
            background-color: #ff4757;
            color: white;
        }
        .btn-primary:hover:not(:disabled) {
            background-color: #e63e4d;
        }
        .btn-primary:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
        }
        .text-primary {
            color: #ff4757;
        }
        .text-primary:hover {
            color: #e63946;
        }
        .bg-error {
            background-color: #fff5f5;
            color: #ff4757;
        }
        .bg-success {
            background-color: #f0fdf4;
            color: #15803d;
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="http://localhost/Online-Fast-Food/customer/menu/menu.php" class="text-primary hover:text-primary flex items-center" aria-label="Return to menu page">
                <i class="fas fa-utensils mr-2" aria-hidden="true"></i> Back to Menu
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Manage Payment Methods</h2>

            <!-- Message Display -->
            <div id="message" class="message hidden p-4 rounded-lg mb-6"></div>

            <!-- Saved Payment Methods -->
            <section class="mb-8">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Saved Payment Methods</h3>
                <?php if (empty($paymentMethods)): ?>
                    <p class="text-gray-600">No payment methods saved.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($paymentMethods as $pm): ?>
                            <?php
                            $displayText = '';
                            $iconClass = 'fa-credit-card';
                            if ($pm['method_type'] === 'card') {
                                $displayText = ucfirst($pm['card_type']) . ' ending in ' . $pm['card_last_four'] . ' (Expires ' . $pm['expiry_date'] . ')';
                                $iconClass = 'fa-credit-card';
                            } elseif ($pm['method_type'] === 'online_banking') {
                                $displayText = ucfirst(str_replace('_', ' ', $pm['bank_name']));
                                $iconClass = 'fa-university';
                            } elseif ($pm['method_type'] === 'digital_wallet') {
                                $displayText = ucfirst($pm['wallet_type']) . ($pm['phone_number'] ? ' (' . $pm['phone_number'] . ')' : '');
                                $iconClass = 'fa-wallet';
                            }
                            ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg card-hover">
                                <div class="flex items-center">
                                    <i class="fas <?= $iconClass ?> text-primary mr-3"></i>
                                    <span class="text-gray-700"><?= htmlspecialchars($displayText) ?></span>
                                </div>
                                <button onclick="deletePaymentMethod(<?= $pm['id'] ?>)" class="text-red-600 hover:text-red-800" aria-label="Delete Payment Method">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Add New Payment Method -->
            <section class="bg-gray-50 p-6 rounded-lg">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Add New Payment Method</h3>
                <form id="addPaymentForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <select id="methodTypeSelect" name="method_type" onchange="updatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Payment Method Type">
                        <option value="">Select Method</option>
                        <option value="card">Card</option>
                        <option value="online_banking">Online Banking</option>
                        <option value="digital_wallet">Digital Wallet</option>
                    </select>

                    <!-- Card Payment Fields -->
                    <div id="cardFields" class="hidden space-y-4">
                        <select id="cardTypeSelect" name="card_type" onchange="validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Card Type">
                            <option value="">Select Card Type</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="jcb">JCB</option>
                            <option value="amex">Amex</option>
                            <option value="mydebit">MyDebit</option>
                            <option value="unionpay">UnionPay</option>
                        </select>
                        <input type="text" id="cardNumber" name="card_number" placeholder="Card Number" maxlength="19" onkeyup="formatCardNumber(this); validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Card Number">
                        <p class="text-sm text-gray-500">For testing, use Visa card: 4242424242424242</p>
                        <input type="text" id="expiryDate" name="expiry_date" placeholder="MM/YY" maxlength="5" onkeyup="formatExpiryDate(this); validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Expiry Date">
                        <input type="text" id="cvv" name="cvv" placeholder="CVV" maxlength="4" oninput="validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="CVV">
                        <input type="text" id="cardName" name="card_name" placeholder="Name on Card" oninput="validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Cardholder Name">
                    </div>

                    <!-- Online Banking Fields -->
                    <div id="onlineBankingFields" class="hidden space-y-4">
                        <select id="bankNameSelect" name="bank_name" onchange="validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Bank Name">
                            <option value="">Select a Bank</option>
                            <option value="maybank2u">Maybank2U</option>
                            <option value="cimbclicks">CIMB Clicks</option>
                            <option value="publicbank">Public Bank</option>
                            <option value="rhb">RHB</option>
                            <option value="hongleong">Hong Leong</option>
                            <option value="ambank">AmBank</option>
                            <option value="mybsn">MyBSN</option>
                            <option value="bankrakyat">Bank Rakyat</option>
                            <option value="uob">UOB</option>
                            <option value="affinbank">Affin Bank</option>
                            <option value="bankislam">Bank Islam</option>
                            <option value="hsbc">HSBC</option>
                            <option value="banknegaramalaysia">Bank Negara Malaysia</option>
                            <option value="alliancebank">Alliance Bank</option>
                            <option value="ocbc">OCBC</option>
                            <option value="bankmuamalat">Bank Muamalat</option>
                            <option value="standardchartered">Standard Chartered</option>
                            <option value="citibank">Citibank</option>
                            <option value="alrajhi">Al Rajhi</option>
                            <option value="bankrakyatbaloyete">Bank Rakyat Baloyete</option>
                        </select>
                    </div>

                    <!-- Digital Wallet Fields -->
                    <div id="digitalWalletFields" class="hidden space-y-4">
                        <select id="walletTypeSelect" name="wallet_type" onchange="validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Wallet Type">
                            <option value="">Select Wallet Type</option>
                            <option value="shopeepay">ShopeePay</option>
                            <option value="tng">Touch 'n Go</option>
                            <option value="grabpay">GrabPay</option>
                            <option value="boost">Boost</option>
                            <option value="googlepay">Google Pay</option>
                        </select>
                        <input type="text" id="phoneNumber" name="phone_number" placeholder="Phone Number" maxlength="15" oninput="formatPhoneNumber(this); validatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Phone Number">
                    </div>

                    <button type="button" id="addPaymentButton" onclick="addPaymentMethod()" class="w-full p-3 rounded-lg btn-primary disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Add Payment Method
                        <span class="spinner" id="addPaymentSpinner"></span>
                    </button>
                </form>
            </section>
        </div>
    </main>

    <script>
        // Initialize elements
        const addPaymentForm = document.getElementById('addPaymentForm');
        const methodTypeSelect = document.getElementById('methodTypeSelect');
        const cardFields = document.getElementById('cardFields');
        const onlineBankingFields = document.getElementById('onlineBankingFields');
        const digitalWalletFields = document.getElementById('digitalWalletFields');
        const messageDiv = document.getElementById('message');
        const addPaymentButton = document.getElementById('addPaymentButton');
        const addPaymentSpinner = document.getElementById('addPaymentSpinner');

        // Format card number
        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 16) value = value.substring(0, 16);
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            input.value = formatted;
        }

        // Format expiry date
        function formatExpiryDate(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 4) value = value.substring(0, 4);
            let formatted = value;
            if (value.length > 2) formatted = value.substring(0, 2) + '/' + value.substring(2);
            input.value = formatted;
        }

        // Format phone number
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 15) value = value.substring(0, 15);
            input.value = value;
        }

        // Update payment form based on method type
        function updatePaymentForm() {
            cardFields.classList.add('hidden');
            onlineBankingFields.classList.add('hidden');
            digitalWalletFields.classList.add('hidden');
            addPaymentButton.disabled = true;
            document.querySelectorAll('#cardFields input, #cardTypeSelect, #onlineBankingFields select, #digitalWalletFields input, #digitalWalletFields select').forEach(element => element.classList.remove('invalid'));

            if (methodTypeSelect.value === 'card') {
                cardFields.classList.remove('hidden');
            } else if (methodTypeSelect.value === 'online_banking') {
                onlineBankingFields.classList.remove('hidden');
                validatePaymentForm();
            } else if (methodTypeSelect.value === 'digital_wallet') {
                digitalWalletFields.classList.remove('hidden');
                validatePaymentForm();
            }
        }

        // Validate payment form inputs
        function validatePaymentForm() {
            const method = methodTypeSelect.value;
            let isValid = false;

            if (method === 'card') {
                const cardType = document.getElementById('cardTypeSelect').value;
                const cardNumber = document.getElementById('cardNumber').value.replace(/\D/g, '');
                const expiryDate = document.getElementById('expiryDate').value;
                const cvv = document.getElementById('cvv').value;
                const cardName = document.getElementById('cardName').value;

                document.getElementById('cardTypeSelect').classList.toggle('invalid', !cardType);
                document.getElementById('cardNumber').classList.toggle('invalid', !/^\d{16}$/.test(cardNumber));
                document.getElementById('expiryDate').classList.toggle('invalid', !/^\d{2}\/\d{2}$/.test(expiryDate));
                document.getElementById('cvv').classList.toggle('invalid', !/^\d{3,4}$/.test(cvv));
                document.getElementById('cardName').classList.toggle('invalid', !cardName || /\d/.test(cardName));

                isValid = cardType && /^\d{16}$/.test(cardNumber) && /^\d{2}\/\d{2}$/.test(expiryDate) && /^\d{3,4}$/.test(cvv) && cardName && !/\d/.test(cardName);
            } else if (method === 'online_banking') {
                const bankName = document.getElementById('bankNameSelect').value;
                document.getElementById('bankNameSelect').classList.toggle('invalid', !bankName);
                isValid = !!bankName;
            } else if (method === 'digital_wallet') {
                const walletType = document.getElementById('walletTypeSelect').value;
                const phoneNumber = document.getElementById('phoneNumber').value;
                document.getElementById('walletTypeSelect').classList.toggle('invalid', !walletType);
                document.getElementById('phoneNumber').classList.toggle('invalid', !/^\d{10,15}$/.test(phoneNumber));
                isValid = walletType && /^\d{10,15}$/.test(phoneNumber);
            }

            addPaymentButton.disabled = !isValid;
            return isValid;
        }

        // Show message to user
        function showMessage(type, message) {
            messageDiv.classList.remove('hidden', 'bg-error', 'bg-success');
            messageDiv.classList.add(type === 'success' ? 'bg-success' : 'bg-error');
            messageDiv.textContent = message;
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 5000);
        }

        // Add new payment method
        function addPaymentMethod() {
            if (!methodTypeSelect.value) {
                showMessage('error', 'Please select a payment method type');
                return;
            }

            if (!validatePaymentForm()) {
                showMessage('error', 'Please fill in all payment fields correctly');
                return;
            }

            addPaymentButton.disabled = true;
            addPaymentSpinner.style.display = 'inline-block';

            const formData = new FormData(addPaymentForm);
            formData.append('add_payment_method', '1');

            fetch('payment_methods.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    const newMethod = data.payment_method;
                    let displayText = '';
                    let iconClass = 'fa-credit-card';
                    if (newMethod.method_type === 'card') {
                        displayText = `${newMethod.card_type.charAt(0).toUpperCase() + newMethod.card_type.slice(1)} ending in ${newMethod.card_last_four} (Expires ${newMethod.expiry_date})`;
                        iconClass = 'fa-credit-card';
                    } else if (newMethod.method_type === 'online_banking') {
                        displayText = newMethod.bank_name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                        iconClass = 'fa-university';
                    } else if (newMethod.method_type === 'digital_wallet') {
                        displayText = `${newMethod.wallet_type.charAt(0).toUpperCase() + newMethod.wallet_type.slice(1)} (${newMethod.phone_number})`;
                        iconClass = 'fa-wallet';
                    }

                    const container = document.querySelector('.space-y-4') || document.createElement('div');
                    if (!container.classList.contains('space-y-4')) {
                        container.classList.add('space-y-4');
                        document.querySelector('section.mb-8').appendChild(container);
                    }

                    const methodDiv = document.createElement('div');
                    methodDiv.className = 'flex items-center justify-between p-4 bg-gray-50 rounded-lg card-hover';
                    methodDiv.innerHTML = `
                        <div class="flex items-center">
                            <i class="fas ${iconClass} text-primary mr-3"></i>
                            <span class="text-gray-700">${displayText}</span>
                        </div>
                        <button onclick="deletePaymentMethod(${newMethod.id})" class="text-red-600 hover:text-red-800" aria-label="Delete Payment Method">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    container.appendChild(methodDiv);

                    addPaymentForm.reset();
                    methodTypeSelect.value = '';
                    updatePaymentForm();
                } else {
                    showMessage('error', data.message || 'Failed to add payment method');
                }
            })
            .catch(error => {
                console.error('Add payment method error:', error);
                showMessage('error', 'An error occurred while adding payment method');
            })
            .finally(() => {
                addPaymentButton.disabled = false;
                addPaymentSpinner.style.display = 'none';
            });
        }

        // Delete payment method
        function deletePaymentMethod(methodId) {
            if (!confirm('Are you sure you want to delete this payment method?')) return;

            const formData = new FormData();
            formData.append('delete_payment_method', '1');
            formData.append('payment_method_id', methodId);
            formData.append('csrf_token', '<?php echo addslashes(htmlspecialchars($csrfToken)); ?>');

            fetch('payment_methods.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    const methodDiv = document.querySelector(`button[onclick="deletePaymentMethod(${methodId})"]`).parentElement;
                    methodDiv.remove();
                    if (!document.querySelector('.space-y-4')?.children.length) {
                        document.querySelector('section.mb-8').innerHTML = '<p class="text-gray-600">No payment methods saved.</p>';
                    }
                } else {
                    showMessage('error', data.message || 'Failed to delete payment method');
                }
            })
            .catch(error => {
                console.error('Delete payment method error:', error);
                showMessage('error', 'An error occurred while deleting payment method');
            });
        }
    </script>
</body>
</html>