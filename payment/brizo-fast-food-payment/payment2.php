<?php
ob_start();
session_start();
require '../../customer/menu/db_connect.php';

// Check database connection
if ($conn->connect_error) {
    $logMessage("Database connection failed: " . $conn->connect_error);
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

// Check if user is logged in
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

// Log function
$logFile = 'payment_errors.log';
$logMessage = function($message) use ($logFile) {
    $message = filter_var($message, FILTER_SANITIZE_STRING);
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Fetch cart items
$cartItems = [];
$total = 0;
$stmt = $conn->prepare("
    SELECT c.item_id, c.quantity, m.price, m.item_name, m.photo, m.category
    FROM cart c
    JOIN menu_items m ON c.item_id = m.id
    WHERE c.customer_id = ? AND m.is_available = 1
");
if (!$stmt) {
    $logMessage("Prepare failed for cart fetch: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cartItems[] = $row;
    $total += $row['quantity'] * $row['price'];
}
$stmt->close();

// Fetch saved delivery addresses
$deliveryAddresses = [];
$stmt = $conn->prepare("
    SELECT id, street_address, city, postal_code, is_default
    FROM delivery_addresses
    WHERE customer_id = ?
    ORDER BY is_default DESC, created_at DESC
");
if (!$stmt) {
    $logMessage("Prepare failed for delivery addresses fetch: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $deliveryAddresses[] = $row;
}
$stmt->close();

// Fetch saved payment methods
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

// Handle adding a new delivery address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_delivery_address'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed for add_delivery_address");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $streetAddress = filter_var(trim($_POST['street_address'] ?? ''), FILTER_SANITIZE_STRING);
    $city = filter_var(trim($_POST['city'] ?? ''), FILTER_SANITIZE_STRING);
    $postalCode = filter_var(trim($_POST['postal_code'] ?? ''), FILTER_SANITIZE_STRING);
    $setAsDefault = isset($_POST['set_as_default']) && $_POST['set_as_default'] === '1';

    $logMessage("Attempting to add delivery address: street=$streetAddress, city=$city, postal_code=$postalCode, default=$setAsDefault");

    // Validate address fields
    if (empty($streetAddress) || strlen($streetAddress) < 5) {
        $logMessage("Invalid street address: $streetAddress");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Street address must be at least 5 characters']);
        exit();
    }
    if (empty($city) || strlen($city) < 2) {
        $logMessage("Invalid city: $city");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'City must be at least 2 characters']);
        exit();
    }
    if (empty($postalCode) || !preg_match('/^\d{5}$/', $postalCode)) {
        $logMessage("Invalid postal code: $postalCode");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Postal code must be 5 digits']);
        exit();
    }

    try {
        // Check for duplicate address
        $stmt = $conn->prepare("
            SELECT id FROM delivery_addresses
            WHERE customer_id = ? AND street_address = ? AND city = ? AND postal_code = ?
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for duplicate address check: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("isss", $customerId, $streetAddress, $city, $postalCode);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $logMessage("Duplicate delivery address detected for customer_id=$customerId");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'This address is already saved']);
            $stmt->close();
            exit();
        }
        $stmt->close();

        $conn->begin_transaction();

        // If setting as default, unset other defaults
        if ($setAsDefault) {
            $stmt = $conn->prepare("UPDATE delivery_addresses SET is_default = 0 WHERE customer_id = ?");
            if (!$stmt) {
                $logMessage("Prepare failed for unset default addresses: " . $conn->error);
                throw new Exception('Database error');
            }
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $stmt->close();
        }

        // Insert new address
        $stmt = $conn->prepare("
            INSERT INTO delivery_addresses (customer_id, street_address, city, postal_code, is_default)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for address insert: " . $conn->error);
            throw new Exception('Database error');
        }
        $isDefault = $setAsDefault ? 1 : 0;
        $stmt->bind_param("isssi", $customerId, $streetAddress, $city, $postalCode, $isDefault);
        if ($stmt->execute()) {
            $newAddressId = $stmt->insert_id;
            $stmt->close();

            // Fetch the new address
            $stmt = $conn->prepare("
                SELECT id, street_address, city, postal_code, is_default
                FROM delivery_addresses
                WHERE id = ?
            ");
            if (!$stmt) {
                $logMessage("Prepare failed for new address fetch: " . $conn->error);
                throw new Exception('Database error');
            }
            $stmt->bind_param("i", $newAddressId);
            $stmt->execute();
            $newAddress = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $conn->commit();
            $displayText = htmlspecialchars("$streetAddress, $city, $postalCode");
            $logMessage("Delivery address added successfully: ID $newAddressId, $displayText");

            ob_end_clean();
            echo json_encode([
                'status' => 'success',
                'message' => 'Address added successfully',
                'delivery_address' => $newAddress
            ]);
        } else {
            $conn->rollback();
            $logMessage("Failed to add delivery address: " . $stmt->error);
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Failed to add delivery address']);
        }
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Exception while adding delivery address: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while adding address']);
        exit();
    }
}

// Handle adding a new payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_method'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed for add_payment_method");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $methodType = filter_var(trim($_POST['method_type'] ?? ''), FILTER_SANITIZE_STRING);
    $logMessage("Attempting to add payment method: method_type=$methodType");

    if (!in_array($methodType, ['card', 'online_banking', 'digital_wallet'])) {
        $logMessage("Invalid payment method type: $methodType");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid payment method type']);
        exit();
    }

    try {
        // Check for duplicate payment method
        $isDuplicate = false;
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
            if (!$duplicateCheckStmt) {
                $logMessage("Prepare failed for duplicate card check: " . $conn->error);
                throw new Exception('Database error');
            }
            $duplicateCheckStmt->bind_param("isss", $customerId, $cardLastFour, $cardType, $cardExpiry);
        } elseif ($methodType === 'online_banking') {
            $bankName = filter_var(trim($_POST['bank_name'] ?? ''), FILTER_SANITIZE_STRING);
            $duplicateCheckStmt = $conn->prepare("
                SELECT id FROM payment_methods
                WHERE customer_id = ? AND method_type = 'online_banking'
                AND bank_name = ?
            ");
            if (!$duplicateCheckStmt) {
                $logMessage("Prepare failed for duplicate bank check: " . $conn->error);
                throw new Exception('Database error');
            }
            $duplicateCheckStmt->bind_param("is", $customerId, $bankName);
        } elseif ($methodType === 'digital_wallet') {
            $walletType = filter_var(trim($_POST['wallet_type'] ?? ''), FILTER_SANITIZE_STRING);
            $phoneNumber = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');
            $duplicateCheckStmt = $conn->prepare("
                SELECT id FROM payment_methods
                WHERE customer_id = ? AND method_type = 'digital_wallet'
                AND wallet_type = ? AND phone_number = ?
            ");
            if (!$duplicateCheckStmt) {
                $logMessage("Prepare failed for duplicate wallet check: " . $conn->error);
                throw new Exception('Database error');
            }
            $duplicateCheckStmt->bind_param("iss", $customerId, $walletType, $phoneNumber);
        }

        if ($duplicateCheckStmt) {
            $duplicateCheckStmt->execute();
            $result = $duplicateCheckStmt->get_result();
            if ($result->num_rows > 0) {
                $isDuplicate = true;
                $logMessage("Duplicate payment method detected for customer_id=$customerId, method_type=$methodType");
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
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Card number must be 16 digits']);
                exit();
            }
            if (!luhnCheck($cardNumber)) {
                $logMessage("Card number failed Luhn check: $cardNumber");
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid card number (failed validation check)']);
                exit();
            }
            if (empty($cardType) || !in_array($cardType, ['visa', 'mastercard', 'jcb', 'amex', 'mydebit', 'unionpay'])) {
                $logMessage("Invalid card type: $cardType");
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid card type']);
                exit();
            }
            if (empty($expiry) || !preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
                $logMessage("Invalid expiry date format: $expiry");
                ob_end_clean();
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
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Card is expired']);
                exit();
            }
            if (empty($cvv) || !preg_match('/^\d{3,4}$/', $cvv)) {
                $logMessage("Invalid CVV: $cvv");
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'CVV must be 3 or 4 digits']);
                exit();
            }
            if (empty($cardName) || preg_match('/\d/', $cardName)) {
                $logMessage("Invalid card name: $cardName");
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid name on card (no numbers allowed)']);
                exit();
            }

            $cardLastFour = substr($cardNumber, -4);
            $cardExpiry = $expiry;
        } elseif ($methodType === 'online_banking') {
            $bankName = filter_var(trim($_POST['bank_name'] ?? ''), FILTER_SANITIZE_STRING);
            $allowedBanks = ['maybank2u', 'cimbclicks', 'rhb', 'publicbank', 'hongleong', 'ambank', 'mybsn', 'bankrakyat', 'uob', 'affinbank', 'bankislam', 'hsbc', 'banknegaramalaysia', 'alliancebank', 'ocbc', 'bankmuamalat', 'standardchartered', 'citibank', 'alrajhi', 'bankrakyatbaloyete'];
            if (empty($bankName) || !in_array($bankName, $allowedBanks)) {
                $logMessage("Invalid bank name: $bankName");
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid bank name']);
                exit();
            }
        } elseif ($methodType === 'digital_wallet') {
            $walletType = filter_var(trim($_POST['wallet_type'] ?? ''), FILTER_SANITIZE_STRING);
            $phoneNumber = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');
            $allowedWallets = ['shopeepay', 'tng', 'grabpay', 'boost', 'googlepay'];
            if (empty($walletType) || !in_array($walletType, $allowedWallets)) {
                $logMessage("Invalid wallet type: $walletType");
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid wallet type']);
                exit();
            }
            if (empty($phoneNumber) || !preg_match('/^\d{10,15}$/', $phoneNumber)) {
                $logMessage("Invalid phone number: $phoneNumber");
                ob_end_clean();
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
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Failed to add payment method']);
        }
        exit();
    } catch (Exception $e) {
        $logMessage("Exception while adding payment method: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while adding payment method']);
        exit();
    }
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    header('Content-Type: application/json');

    try {
        $logMessage("Received make_payment request: " . json_encode($_POST));

        // CSRF validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $logMessage("CSRF validation failed for make_payment");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
            exit();
        }

        // Validate cart
        if (empty($cartItems)) {
            $logMessage("Validation failed: Cart is empty for customer_id=$customerId");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Your cart is empty']);
            exit();
        }

        // Get and sanitize form data
        $method = filter_var(trim($_POST['method'] ?? ''), FILTER_SANITIZE_STRING);
        $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $deliveryMethod = filter_var(trim($_POST['delivery_method'] ?? ''), FILTER_SANITIZE_STRING);
        $deliveryAddressId = (int)($_POST['delivery_address_id'] ?? 0);
        $deliveryAddress = null;

        $logMessage("Received amount: $amount, Expected total: $total");
        $logMessage("Delivery method: $deliveryMethod, Delivery address ID: $deliveryAddressId");
        $logMessage("Payment method: $method, Payment method ID: $paymentMethodId");

        // Validate payment method
        if (!in_array($method, ['card', 'online_banking', 'digital_wallet'])) {
            $logMessage("Validation failed: Invalid payment method ($method)");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Invalid payment method']);
            exit();
        }
        if ($paymentMethodId <= 0) {
            $logMessage("Validation failed: No payment method selected");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Please select a payment method']);
            exit();
        }
        if ($amount <= 0 || abs($amount - $total) > 0.01) {
            $logMessage("Validation failed: Invalid payment amount ($amount, expected $total)");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Invalid payment amount']);
            exit();
        }
        if (!in_array($deliveryMethod, ['pickup', 'delivery'])) {
            $logMessage("Validation failed: Invalid delivery method ($deliveryMethod)");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Invalid delivery method']);
            exit();
        }

        // Validate delivery address if delivery is selected
        if ($deliveryMethod === 'delivery') {
            if ($deliveryAddressId > 0) {
                $stmt = $conn->prepare("
                    SELECT street_address, city, postal_code
                    FROM delivery_addresses
                    WHERE id = ? AND customer_id = ?
                ");
                if (!$stmt) {
                    $logMessage("Prepare failed for delivery address fetch: " . $conn->error);
                    throw new Exception('Database error');
                }
                $stmt->bind_param("ii", $deliveryAddressId, $customerId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $deliveryAddress = [
                        'street_address' => $row['street_address'],
                        'city' => $row['city'],
                        'postal_code' => $row['postal_code']
                    ];
                }
                $stmt->close();
            } else {
                // New address from form
                $deliveryAddress = [
                    'street_address' => filter_var(trim($_POST['delivery_street_address'] ?? ''), FILTER_SANITIZE_STRING),
                    'city' => filter_var(trim($_POST['delivery_city'] ?? ''), FILTER_SANITIZE_STRING),
                    'postal_code' => filter_var(trim($_POST['delivery_postal_code'] ?? ''), FILTER_SANITIZE_STRING)
                ];
                $saveAddress = isset($_POST['save_address']) && $_POST['save_address'] === '1';
                $setAsDefault = isset($_POST['set_as_default']) && $_POST['set_as_default'] === '1';

                if (empty($deliveryAddress['street_address']) || strlen($deliveryAddress['street_address']) < 5) {
                    $logMessage("Validation failed: Street address too short or empty");
                    ob_end_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Street address must be at least 5 characters']);
                    exit();
                }
                if (empty($deliveryAddress['city']) || strlen($deliveryAddress['city']) < 2) {
                    $logMessage("Validation failed: City is empty or too short");
                    ob_end_clean();
                    echo json_encode(['status' => 'error', 'message' => 'City is required and must be at least 2 characters']);
                    exit();
                }
                if (empty($deliveryAddress['postal_code']) || !preg_match('/^\d{5}$/', $deliveryAddress['postal_code'])) {
                    $logMessage("Validation failed: Invalid postal code");
                    ob_end_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Postal code must be 5 digits']);
                    exit();
                }

                // Save new address if requested
                if ($saveAddress) {
                    $conn->begin_transaction();
                    if ($setAsDefault) {
                        $stmt = $conn->prepare("UPDATE delivery_addresses SET is_default = 0 WHERE customer_id = ?");
                        if (!$stmt) {
                            $logMessage("Prepare failed for unset default addresses: " . $conn->error);
                            throw new Exception('Database error');
                        }
                        $stmt->bind_param("i", $customerId);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO delivery_addresses (customer_id, street_address, city, postal_code, is_default)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    if (!$stmt) {
                        $logMessage("Prepare failed for address insert: " . $conn->error);
                        throw new Exception('Database error');
                    }
                    $isDefault = $setAsDefault ? 1 : 0;
                    $stmt->bind_param("isssi", $customerId, $deliveryAddress['street_address'], $deliveryAddress['city'], $deliveryAddress['postal_code'], $isDefault);
                    if (!$stmt->execute()) {
                        $conn->rollback();
                        $logMessage("Failed to save new delivery address: " . $stmt->error);
                        throw new Exception('Failed to save delivery address');
                    }
                    $deliveryAddressId = $stmt->insert_id;
                    $stmt->close();
                    $conn->commit();
                    $logMessage("Saved new delivery address for customer_id=$customerId: ID $deliveryAddressId");
                }
            }

            if (!$deliveryAddress) {
                $logMessage("Validation failed: No valid delivery address provided");
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Please select or enter a valid delivery address']);
                exit();
            }
        } else {
            $deliveryAddress = null;
        }

        // Validate selected payment method
        $selectedMethod = null;
        foreach ($paymentMethods as $pm) {
            if ($pm['id'] === $paymentMethodId) {
                if ($pm['method_type'] !== $method) {
                    $logMessage("Validation failed: Method type mismatch for payment_method_id=$paymentMethodId");
                    ob_end_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Selected payment method type does not match']);
                    exit();
                }
                $selectedMethod = $pm;
                break;
            }
        }
        if (!$selectedMethod) {
            $logMessage("Validation failed: Invalid payment method ID ($paymentMethodId)");
            ob_end_clean();
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

        $conn->begin_transaction();
        $orderId = 'ORD-' . strtoupper(uniqid());
        $logMessage("Generated order_id: $orderId");

        // Save to orders table
        $stmt = $conn->prepare("
            INSERT INTO orders (order_id, customer_id, total, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for orders insert: " . $conn->error);
            throw new Exception('Database error: Unable to prepare orders insert statement');
        }
        $stmt->bind_param("sid", $orderId, $customerId, $amount);
        if (!$stmt->execute()) {
            $logMessage("Execute failed for orders insert: " . $stmt->error);
            throw new Exception('Failed to save order: ' . $stmt->error);
        }
        $stmt->close();

        // Save to order_items table
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, item_id, quantity, price, total)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for order_items insert: " . $conn->error);
            throw new Exception('Database error: Unable to prepare order_items insert statement');
        }
        foreach ($cartItems as $item) {
            $price = (float)$item['price'];
            $itemTotal = $price * $item['quantity'];
            $stmt->bind_param("siidd", $orderId, $item['item_id'], $item['quantity'], $price, $itemTotal);
            if (!$stmt->execute()) {
                $logMessage("Execute failed for order_items insert: " . $stmt->error);
                throw new Exception('Failed to save order items: ' . $stmt->error);
            }
        }
        $stmt->close();

        // Save to payment_history
        $deliveryAddressJson = $deliveryAddress ? json_encode($deliveryAddress) : '';
        $stmt = $conn->prepare("
            INSERT INTO payment_history (order_id, date, amount, status, method, payment_details, payment_method_id, customer_id, delivery_method, delivery_address)
            VALUES (?, NOW(), ?, 'pending', ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $logMessage("Prepare failed for payment_history insert: " . $conn->error);
            throw new Exception('Database error: Unable to prepare payment insert statement');
        }
        $stmt->bind_param("sdssiiss", $orderId, $amount, $method, $paymentDetails, $paymentMethodId, $customerId, $deliveryMethod, $deliveryAddressJson);
        if (!$stmt->execute()) {
            $logMessage("Execute failed for payment_history insert: " . $stmt->error);
            throw new Exception('Failed to save payment: ' . $stmt->error);
        }
        $stmt->close();

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
        if (!$stmt) {
            $logMessage("Prepare failed for cart deletion: " . $conn->error);
            throw new Exception('Database error: Unable to prepare cart deletion statement');
        }
        $stmt->bind_param("i", $customerId);
        if (!$stmt->execute()) {
            $logMessage("Execute failed for cart deletion: " . $stmt->error);
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
            'timestamp' => date('Y-m-d H:i:s', strtotime('2025-06-05 21:17:00 +08:00')),
            'items' => $itemsArray
        ];

        $logMessage("Redirecting to confirmation.php with order_id=$orderId");
        ob_end_clean();
        header("Location: confirmation.php?order_id=" . urlencode($orderId));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Exception in make_payment: " . $e->getMessage());
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
    <title>Checkout - Brizo Fast Food Melaka</title>
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
        .invalid {
            border-color: #ff4757 !important;
            background-color: #fff5f5;
        }
        .message {
            transition: opacity 0.3s ease-in-out;
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
        .btn-cancel {
            background-color: #6b7280;
            color: white;
        }
        .btn-cancel:hover:not(:disabled) {
            background-color: #4b5563;
        }
        .text-primary {
            color: #ff4757;
        }
        .text-primary:hover {
            color: #e63e4d;
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
        <div class="container">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h2>
            <a href="/Online-Fooding/customer/menu/cart/cart.php" class="text-primary hover:text-primary flex items-center" aria-label="Return to cart page">
                <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Back to Cart
            </div>
        </header>

    <main class="container">
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Checkout</h3>
            <!-- Cart Items -->
            <section class="mb-8">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Cart Items</h3>
                <?php if (empty($cartItems)): ?>
                    <p class="text-gray-600">Your cart is empty. <a href="/Online-Fast-Food/customer/menu/cart.php" class="text-primary hover:text-primary">Add items to your cart</a>.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                                <img src="/Online-Food-Food/Admin/Manage_image-item/<?php echo htmlspecialchars($item['photo']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="w-20 h-20 h-object-cover rounded-lg mr-4 lazy" loading="lazy" onerror="this.src='/images/placeholder.jpg'">
                                <div class="text-flex-1">
                                    <h4 class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($item['item_name']); ?></h4>
                                    <p class="text-gray-600">Quantity: <?php echo $item['quantity']; ?> | Price: RM <?php echo number_format($item['price'], 2); ?> each | Total: RM <?php echo number_format($item['quantity'] * $item['price'], 2); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>

            <!-- Payment Form -->
            <form id="paymentForm" method="post" action="payment.php" method="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <!-- Delivery Options -->
            <section class="bg-gray-50 p-6 rounded-lg">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Delivery Options</h3>
                <div id="message" class="message hidden p-4 rounded-lg"></div>
                <div class="space-y-4">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="radio" name="delivery_method" value="pickup" checked class="form-radio text-primary">
                        <span class="text-gray-700"><i class="fas fa-store mr-2"></i>Pick Up</span></label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="radio" name="delivery_method" value="delivery" class="form-radio text-primary">
                        <span class="text-gray-700"><i class="fas fa-truck mr-2"></i>Delivery</span></label>
                    <div id="deliveryAddressSection" class="hidden space-y-4">
                        <label for="block-deliveryAddressSelect" class="block text-gray-700 font-medium">Select Delivery Address</label>
                        <select id="deliveryAddressSelect" name="delivery_address_id" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Delivery Address">
                            <option value="">Select an address</option>
                            <?php foreach ($deliveryAddresses as $row): ?>
                                <?php
                                    $displayText = htmlspecialchars("{$row['street_address']}, {$row['city']}, {$row['postal_code']}"); // Fixed variable name
                                    if ($row['is_default']) {
                                        $displayText .= ' (Default)';
                                    }
                                ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $row['is_default'] ? 'selected' : ''; ?>>
                                    <?php echo $displayText; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="add-delivery-address text-primary hover:text-primary" onclick="showAddressForm()">+ Add New Delivery Address</button>

                        <!-- New Delivery Address Form -->
                        <div id="newAddressForm" class="form-hidden mt-4 p-4 bg-white border border-gray-200 rounded-lg space-y-4">
                            <input type="text" id="newStreetAddress" name="delivery_street_address" placeholder="Street Address" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Street Address">
                            <input type="text" id="newCity" name="delivery_city" placeholder="City" class="w-full p-3 border border-gray-300 rounded-lg w-full" aria-label="City">
                            <input type="text" id="newPostalCode" name="postal_code" placeholder="Postal Code (e.g., 123456)" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Postal Code">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="setAsDefault" name="set_as_default" value="1" class="form-checkbox text-primary">
                                <span class="text-gray-700">Set as default address</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="saveAddress" name="save_address" value="1" checked class="form-checkbox text-primary">
                                <span class="text-gray-700">Save this address for the future orders</span>
                            </label>
                            <div class="flex space-x-4">
                                <button type="button" id="addAddressButton" onclick="addDeliveryAddress()" disabled class="w-full p-3 btn-primary rounded-lg disabled:bg-gray-400 disabled:cursor-not-allowed">Add Address</button>
                                <button type="button" onclick="cancelAddressForm()" class="w-full p-3 btn-cancel rounded-lg">Cancel</button>
                            </div>
                            </div>
                        </div>
                    </select>

                <!-- Payment Methods -->
                <section class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-xl font-medium text-gray-700 mb-4">Payment Method</h3>
                    <div class="space-y-4">
                        <label for="paymentMethodSelect" class="block text-gray-700 font-medium">Select Payment Method</label>
                        <select id="paymentMethodSelect" name="payment_method_id" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Payment Method">
                            <option value="">Select a payment method</option>
                            <?php foreach ($paymentMethods as $row): ?>
                                <?php
                                $displayText = '';
                                $iconClass = 'fa-credit-card';
                                if ($row['method_type'] === 'card') {
                                    $displayText = ucfirst($row['card_type']) . ' ending in ' . $row['card_last_four'];
                                    $iconClass = 'fa-credit-card';
                                } elseif ($row['method_type'] === 'online_banking') {
                                    $displayText = ucfirst(str_replace($_POST['$row'], ' ', '_') ?: 'Unknown Bank');
                                    $iconClass = 'fa-university';
                                } elseif ($row['method_type'] === 'digital_wallet') {
                                    $displayText = ucfirst($row['phone_number']) . ($row['phone_number'] ? ' (' . $row['phone_number'] . ')' : '');
                                    $iconClass = 'fa-wallet';
                                }
                                ?>
                                <option value="<?php echo $row['id']; ?>" data-method-type="<?php echo $row['method_type']; ?>">
                                    <?php echo htmlspecialchars($displayText); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="add-payment-method text-primary hover:text-primary" onclick="showPaymentForm()">+ Add New Payment Method</button>

                        <!-- New Payment Method Form -->
                        <div id="newPaymentForm" class="hidden mt-4 p-4 bg-white border border-gray-200 rounded-lg">
                            <select id="methodTypeSelect" onchange="updatePaymentForm()" class="w-full p-3 border border-gray-300 rounded-lg mb-4" aria-label="Payment Method Type">
                                <option value="none">Select Method</option>
                                <option value="card">Card</option>
                                <option value="online_banking">Online Banking</option>
                                <option value="digital_wallet">Digital Wallet</option>
                            </select>

                            <!-- Card Payment Fields -->
                            <div id="cardFields" class="hidden space-y-4">
                                <select id="cardTypeSelect" onchange="validatePaymentForm(this)" class="w-full p-3 border rounded-lg" aria-label="Card Type">
                                    <option value="">Select Card Type</option>
                                    <option value="visa">Visa</option>
                                    <option value="mastercard">Mastercard</option>
                                    <option value="jcb">JCB</option>
                                    <option value="amex">Amex</option>
                                    <option value="mydebit">MyDebit</option>
                                    <option value="unionpay">UnionPay</option>
                                </select>
                                <input type="text" id="cardNumber" name="card_number" placeholder="Card Number" maxlength="19" oninput="formatCard(this); validateCardNumber(this)" class="w-full p-2 border rounded-lg" aria-label="Card Number">
                                <p class="text-muted text-sm">For testing, use Visa card: 4567891234567891</p>
                                <input type="text" id="expiryDate" name="expiry_date" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this); validateExpiryDate(this)" class="w-full p-2 border rounded-lg" aria-label="Expiry Date">
                                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4" oninput="validateCVV(this)" class="w-full p-2 border rounded-lg" aria-label="CVV">
                                <input type="text" id="name" name="card_name" placeholder="Name on Card" oninput="validateCardName(this)" class="w-full p-2 border rounded-lg" aria-label="Name on Card">
                            </div>

                            <!-- Online Banking Fields -->
                            <div id="onlineBankingFields" class="hidden space-y-4">
                                <select id="bankNameSelect" onchange="validateBankName(this)" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Bank Name">
                                    <option value="">Select a bank</option>
                                    <option value="maybank">Maybank</option>
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
                                    <option value="banknegaramal">Bank Negara Malaysia</option>
                                    <option value="alliancebank">Alliance Bank</option>
                                    <option value="ocbc">OCBC</option>
                                    <option value="bankmuamalat">Bank Muamalat</option>
                                    <option value="standardchartered">Standard Chartered</option>
                                    <option value="citibank">Citibank</option>
                                    <option value="alrajhi">Al Rajhi</option>
                                    <option value="bankrakyatbaloyete">Debit Rakyat</option>
                                </select>
                            </div>

                            <!-- Digital Wallet Fields -->
                            <div id="digitalWalletFields" class="hidden space-y-2">
                                <select id="walletTypeSelect" onchange="validateWalletType(this)" class="w-full p-2 border rounded-lg" aria-label="Wallet Type">
                                    <option value="none">Select Wallet</option>
                                    <option value="shopeepay">ShopeePay</option>
                                    <option value="tng">TNG e-Wallet</option>
                                    <option value="grabpay">GrabPay</option>
                                    <option value="boost">Boost</option>
                                    <option value="googlepay">Google Pay</option>
                                </select>
                                <input type="text" id="phoneNumber" name="phone_number" placeholder="Phone Number" maxlength="15" oninput="formatPhoneNumber(this); validatePhoneNumber(this)" class="w-full p-2 border rounded-lg" aria-label="Phone Number">
                            </div>
                            <div class="flex space-x-2">
                                <button type="button" id="addPaymentMethod" onclick="addPaymentMethod()" disabled class="w-full btn btn-primary p-3 rounded-lg" aria-label="Add payment method">
                                    Add Payment Method
                                    <span class="spinner" id="paymentSpinner"></span>
                                </button>
                                <button type="button" onclick="cancelPaymentForm()" class="w-full btn btn-cancel p-3 rounded-lg" aria-label="Cancel payment method">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="method" id="method">
                    <input type="hidden" name="amount" value="<?php echo $total; ?>">
                    <input type="hidden" name="make_payment" value="1">
                </section>

                <!-- Total and Submit -->
                <div class="flex justify-content-between py-4">
                    <p class="text-lg font-semibold text-gray-700">Total: $<?php echo number_format($total, 2); ?></p>
                    <button type="submit" id="submitPayment" class="btn btn-primary p-3 rounded-lg disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center" <?php if (empty($cartItems) || empty($paymentMethods)) echo ' disabled'; ?>>
                        Make Payment
                        <span class="spinner" id="submitSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const deliveryRadios = document.querySelectorAll('input[name="delivery_method"]');
        const deliveryAddressSection = document.getElementById('deliveryAddressSection');
        const deliveryAddressSelect = document.getElementById('deliveryAddressSelect');
        const newAddressForm = document.getElementById('newAddressForm');
        const newStreetAddress = document.getElementById('newStreetAddress');
        const newCity = document.getElementById('newCity');
        const newPostalCode = document.getElementById('newPostalCode');
        const setAsDefault = document.getElementById('setAsDefault');
        const saveAddress = document.getElementById('saveAddress');
        const addAddressButton = document.getElementById('addAddressButton');
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
        const paymentSpinner = document.getElementById('paymentSpinner');
        const submitSpinner = document.getElementById('submitSpinner');

        // Show message
        function showMessage(type, message) {
            messageDiv.classList.remove('hidden', 'bg-error', 'bg-success');
            messageDiv.classList.add(type === 'success' ? 'bg-success' : 'bg-error');
            messageDiv.textContent = message;
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 3000);
        }

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
            if (value.length > 2) {
                formatted = value.substring(0, 2) + '/' + value.substring(2);
            }
            input.value = formatted;
        }

        // Format phone number
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 15) value = value.substring(0, 15);
            input.value = value;
        }

        // Validate address fields only when submitting
        function validateAddressFields(showErrors = false) {
            const streetValid = newStreetAddress.value.trim().length >= 5;
            const cityValid = newCity.value.trim().length >= 2;
            const postalValid = /^\d{5}$/.test(newPostalCode.value.trim());

            if (showErrors) {
                newStreetAddress.classList.toggle('invalid', !streetValid);
                newCity.classList.toggle('invalid', !cityValid);
                newPostalCode.classList.toggle('invalid', !postalValid);
            }

            return streetValid && cityValid && postalValid;
        }

        // Validate new address form
        function validateNewAddressForm() {
            const isValid = validateAddressFields();
            addAddressButton.disabled = !isValid;
        }

        // Show address form
        function showAddressForm() {
            newAddressForm.classList.remove('hidden');
            newStreetAddress.focus();
            newStreetAddress.classList.remove('invalid');
            newCity.classList.remove('invalid');
            newPostalCode.classList.remove('invalid');
            validateNewAddressForm();
        }

        // Cancel address form
        function cancelAddressForm() {
            newAddressForm.classList.add('hidden');
            newStreetAddress.value = '';
            newCity.value = '';
            newPostalCode.value = '';
            setAsDefault.checked = false;
            saveAddress.checked = true;
            newStreetAddress.classList.remove('invalid');
            newCity.classList.remove('invalid');
            newPostalCode.classList.remove('invalid');
            addAddressButton.disabled = true;
            validateForm();
        }

        // Add new delivery address
        function addDeliveryAddress() {
            if (!validateAddressFields(true)) {
                showMessage('error', 'Please fill in valid address details');
                return;
            }

            addAddressButton.disabled = true;
            const formData = new FormData();
            formData.append('add_delivery_address', '1');
            formData.append('street_address', newStreetAddress.value.trim());
            formData.append('city', newCity.value.trim());
            formData.append('postal_code', newPostalCode.value.trim());
            formData.append('set_as_default', setAsDefault.checked ? '1' : '0');
            formData.append('csrf_token', '<?php echo htmlspecialchars($csrfToken); ?>');

            fetch('payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    const addr = data.delivery_address;
                    const displayText = `${addr.street_address}, ${addr.city}, ${addr.postal_code}${addr.is_default ? ' (Default)' : ''}`;
                    const option = new Option(displayText, addr.id, true, true);
                    deliveryAddressSelect.appendChild(option);
                    cancelAddressForm();
                } else {
                    showMessage('error', data.message);
                }
            })
            .catch(() => {
                showMessage('error', 'An error occurred while adding address');
            })
            .finally(() => {
                addAddressButton.disabled = false;
            });
        }

        // Show payment form
        function showPaymentForm() {
            newPaymentForm.classList.remove('hidden');
            methodTypeSelect.focus();
            resetPaymentFields();
        }

        // Reset payment fields
        function resetPaymentFields() {
            methodTypeSelect.value = '';
            cardFields.classList.add('hidden');
            onlineBankingFields.classList.add('hidden');
            digitalWalletFields.classList.add('hidden');
            document.getElementById('cardTypeSelect').value = '';
            document.getElementById('cardNumber').value = '';
            document.getElementById('expiryDate').value = '';
            document.getElementById('cvv').value = '';
            document.getElementById('cardName').value = '';
            document.getElementById('bankName').value = '';
            document.getElementById('walletTypeSelect').value = '';
            document.getElementById('phoneNumber').value = '';
            clearPaymentFieldErrors();
            addPaymentButton.disabled = true;
        }

        // Clear payment field errors
        function clearPaymentFieldErrors() {
            document.getElementById('cardTypeSelect').classList.remove('invalid');
            document.getElementById('cardNumber').classList.remove('invalid');
            document.getElementById('expiryDate').classList.remove('invalid');
            document.getElementById('cvv').classList.remove('invalid');
            document.getElementById('cardName').classList.remove('invalid');
            document.getElementById('bankNameSelect').classList.remove('invalid');
            document.getElementById('walletTypeSelect').classList.remove('invalid');
            document.getElementById('phoneNumber').classList.remove('invalid');
        }

        // Cancel payment form
        function cancelPaymentForm() {
            newPaymentForm.classList.add('hidden');
            resetPaymentFields();
            validateForm();
        }

        // Update payment form
        function updatePaymentForm() {
            cardFields.classList.add('hidden');
            onlineBankingFields.classList.add('hidden');
            digitalWalletFields.classList.add('hidden');
            clearPaymentFieldErrors();
            addPaymentButton.disabled = true;

            const method = methodTypeSelect.value;
            if (method === 'card') {
                cardFields.classList.remove('hidden');
            } else if (method === 'online_banking') {
                onlineBankingFields.classList.remove('hidden');
            } else if (method === 'digital_wallet') {
                digitalWalletFields.classList.remove('hidden');
            }
            validatePaymentForm();
        }

        // Validate payment form
        function validatePaymentForm(showErrors = false) {
            const method = methodTypeSelect.value;
            let isValid = false;

            if (method === 'card') {
                const cardType = document.getElementById('cardTypeSelect').value;
                const cardNumber = document.getElementById('cardNumber').value.replace(/\D/g, '');
                const expiryDate = document.getElementById('expiryDate').value;
                const cvv = document.getElementById('cvv').value;
                const cardName = document.getElementById('cardName').value;

                if (showErrors) {
                    document.getElementById('cardTypeSelect').classList.toggle('invalid', !cardType);
                    document.getElementById('cardNumber').classList.toggle('invalid', !/^\d{16}$/.test(cardNumber));
                    document.getElementById('expiryDate').classList.toggle('invalid', !/^\d{2}\/\d{2}$/.test(expiryDate));
                    document.getElementById('cvv').classList.toggle('invalid', !/^\d{3,4}$/.test(cvv));
                    document.getElementById('cardName').classList.toggle('invalid', !cardName || /\d/.test(cardName));
                }

                isValid = cardType !== '' &&
                          cardNumber.length === 16 &&
                          /^\d{2}\/\d{2}$/.test(expiryDate) &&
                          /^\\d{3,4}$/.test(cvv) &&
                          cardName !== '' && !/\d/.test(cardName);
            } else if (method === 'online_banking') {
                const bankName = document.getElementById('bankNameSelect').value;
                if (showErrors) {
                    document.getElementById('bankNameSelect').classList.toggle('invalid', !bankName);
                }
                isValid = bankName !== '';
            } else if (method === 'digital_wallet') {
                const walletType = document.getElementById('walletTypeSelect').value;
                const phoneNumber = document.getElementById('phoneNumber').value;
                if (showErrors) {
                    document.getElementById('walletTypeSelect').classList.toggle('invalid', !walletType);
                    document.getElementById('phoneNumber').classList.toggle('invalid', !/^\d{10,15}$/.test(phoneNumber));
                }
                isValid = walletType !== '' && /^\d{10,15}$/.test(phoneNumber);
            }

            addPaymentButton.disabled = !isValid;
            return isValid;
        }

        // Add payment method
        function addPaymentMethod() {
            if (!validatePaymentForm(true)) {
                showMessage('error', 'Please fill in valid payment details');
                return;
            }

            addPaymentButton.disabled = true;
            paymentSpinner.style.display = 'inline-block';

            const method = methodTypeSelect.value;
            const formData = new FormData();
            formData.append('add_payment_method', '1');
            formData.append('method_type', method);
            formData.append('csrf_token', '<?php echo htmlspecialchars($csrfToken); ?>');

            if (method === 'card') {
                formData.append('card_type', document.getElementById('cardTypeSelect').value);
                formData.append('card_number', document.getElementById('cardNumber').value.replace(/\D/g, ''));
                formData.append('expiry_date', document.getElementById('expiryDate').value);
                formData.append('cvv', document.getElementById('cvv').value);
                formData.append('card_name', document.getElementById('cardName').value);
            } else if (method === 'online_banking') {
                formData.append('bank_name', document.getElementById('bankNameSelect').value);
            } else if (method === 'digital_wallet') {
                formData.append('wallet_type', document.getElementById('walletTypeSelect').value);
                formData.append('phone_number', document.getElementById('phoneNumber').value);
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
                    paymentMethodSelect.append(option);
                    cancelPaymentForm();
                } else {
                    showMessage('error', data.message);
                }
            })
            .catch(() => {
                showMessage('error', 'An error occurred while adding payment method');
            })
            .finally(() => {
                addPaymentButton.disabled = false;
                paymentSpinner.style.display = 'none';
            });
        }

        // Handle form submission
        paymentForm.addEventListener('submit', function(e) {
            const methodType = paymentMethodSelect.options[paymentMethodSelect.selectedIndex]?.getAttribute('data-method-type');
            if (!methodType) {
                e.preventDefault();
                showMessage('error', 'Please select a payment method');
                return;
            }
            methodInput.value = methodType;
            submitButton.disabled = true;
            submitSpinner.style.display = 'inline-block';
        });

        // Validate main form
        function validateForm() {
            const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked').value;
            const paymentMethodId = paymentMethodSelect.value;
            const addressValid = deliveryMethod !== 'delivery' || deliveryAddressSelect.value || validateAddressFields();
            submitButton.disabled = !addressValid || !paymentMethodId;
        }

        // Event listeners
        deliveryRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                deliveryAddressSection.classList.toggle('hidden', radio.value !== 'delivery');
                if (radio.value !== 'delivery') {
                    deliveryAddressSelect.value = '';
                    cancelAddressForm();
                }
                validateForm();
            });
        });
        deliveryAddressSelect.addEventListener('change', validateForm);
        paymentMethodSelect.addEventListener('change', validateForm);
        newStreetAddress.addEventListener('input', validateNewAddressForm);
        newCity.addEventListener('input', validateNewAddressForm);
        newPostalCode.addEventListener('input', validateNewAddressForm);
        // Payment form listeners
        methodTypeSelect.addEventListener('change', updatePaymentForm);
        document.getElementById('cardTypeSelect').addEventListener('change', () => validatePaymentForm());
        document.getElementById('cardNumber').addEventListener('input', () => validatePaymentForm());
        document.getElementById('expiryDate').addEventListener('input', () => validatePaymentForm());
        document.getElementById('cvv').addEventListener('input', () => validatePaymentForm());
        document.getElementById('cardName').addEventListener('input', () => validatePaymentForm());
        document.getElementById('bankNameSelect').addEventListener('change', () => validatePaymentForm());
        document.getElementById('walletTypeSelect').addEventListener('change', () => validatePaymentForm());
        document.getElementById('phoneNumber').addEventListener('input', () => validatePaymentForm());

        // Initial validation
        validateForm();
    </script>
</body>
</html>