<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php
// Database configuration
$servername = "localhost";
$username = "brizo_user";  // Changed to brizo_user (you may need to create this user)
$password = "secure_password"; // Use your actual password
$dbname = "brizo";  // Changed to brizo database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
$sql = "CREATE TABLE IF NOT EXISTS payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    date DATETIME NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    method VARCHAR(100) NOT NULL
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    last_four VARCHAR(4) NOT NULL,
    card_type VARCHAR(20) NOT NULL,
    expiry VARCHAR(5) NOT NULL,
    name VARCHAR(100) NOT NULL
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    details TEXT NOT NULL,
    date DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
)";
$conn->query($sql);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payment'])) {
        // Process payment
        $order_id = $_POST['order_id'];
        $amount = $_POST['amount'];
        $method = $_POST['payment_method'];
        $status = 'completed'; // In a real app, this would depend on payment success
        
        $stmt = $conn->prepare("INSERT INTO payment_history (order_id, date, amount, status, method) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->bind_param("sdss", $order_id, $amount, $status, $method);
        $stmt->execute();
        $stmt->close();
        
        // Return success response for AJAX
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
        exit;
    } elseif (isset($_POST['submit_refund'])) {
        // Process refund request
        $order_id = $_POST['refund_order'];
        $reason = $_POST['refund_reason'];
        $details = $_POST['refund_details'];
        
        $stmt = $conn->prepare("INSERT INTO refund_requests (order_id, reason, details, date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $order_id, $reason, $details);
        $stmt->execute();
        $stmt->close();
        
        // Return success response for AJAX
        echo json_encode(['status' => 'success', 'message' => 'Refund request submitted successfully']);
        exit;
    } elseif (isset($_POST['save_payment_method'])) {
        // Save new payment method
        $card_number = str_replace(' ', '', $_POST['new_card_number']);
        $expiry = $_POST['new_expiry'];
        $cvv = $_POST['new_cvv'];
        $name = $_POST['new_card_name'];
        
        // Determine card type
        $first_digit = substr($card_number, 0, 1);
        $card_type = $first_digit === '4' ? 'VISA' : 
                     ($first_digit === '5' ? 'Mastercard' : 
                     ($first_digit === '3' ? 'American Express' : 'Card'));
        
        $last_four = substr($card_number, -4);
        
        $stmt = $conn->prepare("INSERT INTO payment_methods (type, last_four, card_type, expiry, name) VALUES ('card', ?, ?, ?, ?)");
        $stmt->bind_param("ssss", $last_four, $card_type, $expiry, $name);
        $stmt->execute();
        $stmt->close();
        
        // Return success response for AJAX
        echo json_encode(['status' => 'success', 'message' => 'Payment method saved successfully']);
        exit;
    } elseif (isset($_POST['remove_payment_method'])) {
        // Remove payment method
        $id = $_POST['method_id'];
        
        $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Return success response for AJAX
        echo json_encode(['status' => 'success', 'message' => 'Payment method removed successfully']);
        exit;
    }
}

// Fetch data from database
$payment_history = [];
$result = $conn->query("SELECT * FROM payment_history ORDER BY date DESC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $payment_history[] = $row;
    }
}

$payment_methods = [];
$result = $conn->query("SELECT * FROM payment_methods");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $payment_methods[] = $row;
    }
}

$refund_requests = [];
$result = $conn->query("SELECT * FROM refund_requests ORDER BY date DESC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $refund_requests[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fast Food Payment Module</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #0066cc;
      --primary-dark: #004c99;
      --secondary: #ff6b00;
      --success: #28a745;
      --danger: #dc3545;
      --light: #f8f9fa;
      --dark: #343a40;
      --gray: #6c757d;
      --light-gray: #e9ecef;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
      margin: 0;
      padding: 0;
      color: var(--dark);
      line-height: 1.6;
    }
    
    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 20px;
    }
    
    header {
      text-align: center;
      margin-bottom: 30px;
      padding: 20px 0;
      background: linear-gradient(to right, var(--primary), var(--primary-dark));
      color: white;
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      position: relative;
      overflow: hidden;
    }
    
    header::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="rgba(255,255,255,0.1)" d="M0,0 L100,0 L100,100 L0,100 Z" /></svg>');
      opacity: 0.1;
    }
    
    h1 {
      margin: 0;
      font-size: 2.2rem;
      position: relative;
      z-index: 1;
      text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    h2 {
      color: var(--primary);
      margin-top: 0;
      font-size: 1.5rem;
      border-bottom: 2px solid var(--primary);
      padding-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .card-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }
    
    .card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(0,0,0,0.05);
    }
    
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 5px;
      height: 100%;
      background: var(--primary);
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
      font-size: 1rem;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .btn-primary {
      background-color: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background-color: var(--primary-dark);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-secondary {
      background-color: var(--secondary);
      color: white;
    }
    
    .btn-secondary:hover {
      background-color: #e05d00;
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-outline {
      background: transparent;
      border: 2px solid var(--primary);
      color: var(--primary);
    }
    
    .btn-outline:hover {
      background-color: var(--primary);
      color: white;
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-danger {
      background-color: var(--danger);
      color: white;
    }
    
    .btn-danger:hover {
      background-color: #c82333;
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .form-group {
      margin-bottom: 20px;
      position: relative;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--dark);
    }
    
    input, select, textarea {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
      transition: all 0.3s;
      background-color: white;
    }
    
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(0, 119, 204, 0.2);
    }
    
    input.error {
      border-color: var(--danger);
      background-color: rgba(220, 53, 69, 0.05);
    }
    
    .error-message {
      color: var(--danger);
      font-size: 0.8rem;
      margin-top: 5px;
      display: none;
    }
    
    .payment-methods {
      display: flex;
      gap: 15px;
      margin: 20px 0;
    }
    
    .payment-method {
      flex: 1;
      text-align: center;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s;
      background-color: white;
    }
    
    .payment-method:hover {
      border-color: var(--primary);
      transform: translateY(-2px);
    }
    
    .payment-method.active {
      border-color: var(--primary);
      background-color: rgba(0, 119, 204, 0.05);
      box-shadow: 0 0 0 2px rgba(0, 119, 204, 0.2);
    }
    
    .payment-method i {
      font-size: 2rem;
      margin-bottom: 10px;
      color: var(--primary);
    }
    
    .confetti {
      position: fixed;
      width: 10px;
      height: 10px;
      background-color: var(--secondary);
      opacity: 0;
      z-index: 1000;
      animation: confetti 3s ease-in-out;
    }
    
    @keyframes confetti {
      0% {
        transform: translateY(0) rotate(0deg);
        opacity: 1;
      }
      100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
      }
    }
    
    .receipt {
      background-color: #f9f9f9;
      padding: 20px;
      border-radius: 8px;
      border: 1px dashed var(--gray);
      margin-bottom: 20px;
      background: linear-gradient(to bottom, white, var(--light-gray));
    }
    
    .receipt-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
    }
    
    .receipt-total {
      font-weight: bold;
      border-top: 1px solid var(--gray);
      padding-top: 10px;
      margin-top: 10px;
      font-size: 1.1rem;
      color: var(--primary);
    }
    
    .history-item {
      padding: 15px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background-color 0.3s;
    }
    
    .history-item:hover {
      background-color: rgba(0,0,0,0.02);
    }
    
    .history-item:last-child {
      border-bottom: none;
    }
    
    .status {
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: bold;
    }
    
    .status-completed {
      background-color: rgba(40, 167, 69, 0.2);
      color: var(--success);
    }
    
    .status-pending {
      background-color: rgba(255, 193, 7, 0.2);
      color: #ffc107;
    }
    
    .status-failed {
      background-color: rgba(220, 53, 69, 0.2);
      color: var(--danger);
    }
    
    .tab-container {
      margin-bottom: 20px;
    }
    
    .tabs {
      display: flex;
      border-bottom: 1px solid #ddd;
    }
    
    .tab {
      padding: 12px 20px;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      transition: all 0.3s;
      font-weight: 500;
      color: var(--gray);
      position: relative;
    }
    
    .tab:hover {
      color: var(--primary);
    }
    
    .tab.active {
      border-bottom-color: var(--primary);
      color: var(--primary);
      font-weight: bold;
    }
    
    .tab-content {
      display: none;
      padding: 20px 0;
      animation: fadeIn 0.3s ease-in-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .tab-content.active {
      display: block;
    }
    
    .hidden {
      display: none;
    }
    
    .loader {
      border: 4px solid rgba(0, 0, 0, 0.1);
      border-radius: 50%;
      border-top: 4px solid var(--primary);
      width: 30px;
      height: 30px;
      animation: spin 1s linear infinite;
      margin: 20px auto;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .alert {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
      from { transform: translateY(-20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .alert-success {
      background-color: rgba(40, 167, 69, 0.2);
      color: var(--success);
      border: 1px solid rgba(40, 167, 69, 0.3);
    }
    
    .alert-danger {
      background-color: rgba(220, 53, 69, 0.2);
      color: var(--danger);
      border: 1px solid rgba(220, 53, 69, 0.3);
    }
    
    .card-icon {
      position: absolute;
      right: 15px;
      top: 35px;
      color: var(--gray);
    }
    
    .card-logo {
      height: 24px;
      margin-right: 10px;
      vertical-align: middle;
    }
    
    .bank-logo, .wallet-logo {
      height: 24px;
      vertical-align: middle;
      margin-right: 8px;
    }
    
    .bank-select-container, .wallet-select-container {
      position: relative;
    }
    
    .bank-select-container::after, .wallet-select-container::after {
      content: '';
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      width: 20px;
      height: 20px;
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;
    }
    
    .bank-select-container::after {
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%236c757d"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm4.59-12.42L10 14.17l-2.59-2.58L6 13l4 4 8-8z"/></svg>');
    }
    
    .wallet-select-container::after {
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%236c757d"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm4.59-12.42L10 14.17l-2.59-2.58L6 13l4 4 8-8z"/></svg>');
    }
    
    .bank-login-container, .wallet-login-container {
      background-color: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin-top: 15px;
      border: 1px solid #e9ecef;
    }
    
    .bank-login-container h4, .wallet-login-container h4 {
      margin-top: 0;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .bank-login-container h4 i, .wallet-login-container h4 i {
      color: var(--primary);
    }
    
    .secure-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background-color: rgba(40, 167, 69, 0.2);
      color: var(--success);
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.8rem;
      margin-left: 10px;
    }
    
    @media (max-width: 768px) {
      .card-container {
        grid-template-columns: 1fr;
      }
      
      .payment-methods {
        flex-direction: column;
      }
      
      .tabs {
        overflow-x: auto;
        white-space: nowrap;
      }
      
      .tab {
        padding: 12px 15px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1><i class="fas fa-hamburger"></i> Fast Food Payment System</h1>
      <p>Secure and convenient payment solutions for your orders</p>
    </header>

    <div class="tab-container">
      <div class="tabs">
        <div class="tab active" onclick="openTab('payment')">Make Payment</div>
        <div class="tab" onclick="openTab('history')">Payment History</div>
        <div class="tab" onclick="openTab('refund')">Refund Request</div>
        <div class="tab" onclick="openTab('methods')">Payment Methods</div>
      </div>
    </div>

    <!-- Payment Gateway Tab -->
    <div id="payment" class="tab-content active">
      <div class="card">
        <h2><i class="fas fa-credit-card"></i> Payment Gateway</h2>
        <p>Complete your payment securely using one of our supported methods.</p>
        
        <div class="form-group">
          <label for="order-id">Order ID</label>
          <input type="text" id="order-id" placeholder="Enter your order ID">
          <div class="error-message" id="order-id-error"></div>
        </div>
        
        <div class="form-group">
          <label for="payment-amount">Amount (RM)</label>
          <input type="text" id="payment-amount" placeholder="Enter amount" inputmode="decimal">
          <div class="error-message" id="amount-error"></div>
        </div>
        
        <h3>Select Payment Method</h3>
        <div class="payment-methods">
          <div class="payment-method active" onclick="selectPaymentMethod('card')">
            <i class="far fa-credit-card"></i>
            <div>Credit/Debit Card</div>
          </div>
          <div class="payment-method" onclick="selectPaymentMethod('bank')">
            <i class="fas fa-university"></i>
            <div>Online Banking</div>
          </div>
          <div class="payment-method" onclick="selectPaymentMethod('wallet')">
            <i class="fas fa-wallet"></i>
            <div>e-Wallet</div>
          </div>
        </div>
        
        <div id="card-details" class="form-group">
          <label for="card-number">Card Number</label>
          <div style="position: relative;">
            <input type="text" id="card-number" placeholder="1234 5678 9012 3456" maxlength="19" inputmode="numeric">
            <i class="fab fa-cc-visa card-icon" id="card-type-icon"></i>
          </div>
          <div class="error-message" id="card-number-error"></div>
          
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
              <label for="expiry-date">Expiry Date</label>
              <input type="text" id="expiry-date" placeholder="MM/YY" maxlength="5" inputmode="numeric">
              <div class="error-message" id="expiry-error"></div>
            </div>
            <div class="form-group">
              <label for="cvv">CVV</label>
              <input type="text" id="cvv" placeholder="123" maxlength="4" inputmode="numeric">
              <div class="error-message" id="cvv-error"></div>
            </div>
          </div>
          
          <label for="card-name">Name on Card</label>
          <input type="text" id="card-name" placeholder="John Doe">
          <div class="error-message" id="card-name-error"></div>
        </div>
        
        <div id="bank-details" class="form-group hidden">
          <label for="bank-select">Select Bank</label>
          <div class="bank-select-container">
            <select id="bank-select" onchange="showBankLogin()">
              <option value="">-- Select Bank --</option>
              <option value="maybank">Maybank</option>
              <option value="cimb">CIMB Bank</option>
              <option value="public">Public Bank</option>
              <option value="rhb">RHB Bank</option>
              <option value="hongleong">Hong Leong Bank</option>
            </select>
          </div>
          <div class="error-message" id="bank-error"></div>
          
          <div id="bank-login" class="bank-login-container hidden">
            <h4><i class="fas fa-lock"></i> Secure Login <span class="secure-badge"><i class="fas fa-shield-alt"></i> Secure</span></h4>
            <div class="form-group">
              <label for="bank-username">Username/ID</label>
              <input type="text" id="bank-username" placeholder="Enter your online banking username">
            </div>
            <div class="form-group">
              <label for="bank-password">Password</label>
              <input type="password" id="bank-password" placeholder="Enter your password">
            </div>
            <div class="form-group">
              <label for="bank-otp">OTP (if required)</label>
              <input type="text" id="bank-otp" placeholder="Enter OTP sent to your phone" inputmode="numeric">
            </div>
            <button class="btn btn-primary" onclick="authenticateBank()">
              <i class="fas fa-sign-in-alt"></i> Login & Authorize Payment
            </button>
          </div>
        </div>
        
        <div id="wallet-details" class="form-group hidden">
          <label for="wallet-select">Select e-Wallet</label>
          <div class="wallet-select-container">
            <select id="wallet-select" onchange="showWalletLogin()">
              <option value="">-- Select e-Wallet --</option>
              <option value="grabpay">GrabPay</option>
              <option value="tng">Touch 'n Go eWallet</option>
              <option value="boost">Boost</option>
              <option value="shopeepay">ShopeePay</option>
            </select>
          </div>
          <div class="error-message" id="wallet-error"></div>
          
          <div id="wallet-login" class="wallet-login-container hidden">
            <h4><i class="fas fa-mobile-alt"></i> e-Wallet Login <span class="secure-badge"><i class="fas fa-shield-alt"></i> Secure</span></h4>
            <div class="form-group">
              <label for="wallet-phone">Phone Number</label>
              <input type="text" id="wallet-phone" placeholder="Enter your registered phone number" inputmode="numeric">
            </div>
            <div class="form-group">
              <label for="wallet-pin">PIN</label>
              <input type="password" id="wallet-pin" placeholder="Enter your 6-digit PIN" inputmode="numeric" maxlength="6">
            </div>
            <button class="btn btn-primary" onclick="authenticateWallet()">
              <i class="fas fa-check-circle"></i> Verify & Pay
            </button>
          </div>
        </div>
        
        <button class="btn btn-primary" onclick="processPayment()" id="pay-now-btn">
          <i class="fas fa-lock"></i> Pay Now
        </button>
      </div>
    </div>

    <!-- Payment Confirmation -->
    <div id="confirmation" class="card hidden">
      <div id="payment-processing" class="hidden">
        <h2><i class="fas fa-spinner"></i> Processing Payment</h2>
        <p>Please wait while we process your payment...</p>
        <div class="loader"></div>
      </div>
      
      <div id="payment-success">
        <h2><i class="fas fa-check-circle" style="color: var(--success);"></i> Payment Successful!</h2>
        <p id="confirm-message">Your payment was successful.</p>
        
        <div class="receipt">
          <h3>Order Receipt</h3>
          <div class="receipt-item">
            <span>Order ID:</span>
            <span id="receipt-order-id"></span>
          </div>
          <div class="receipt-item">
            <span>Date:</span>
            <span id="receipt-date"></span>
          </div>
          <div class="receipt-item">
            <span>Payment Method:</span>
            <span id="receipt-method"></span>
          </div>
          <div class="receipt-item">
            <span>Amount:</span>
            <span id="receipt-amount"></span>
          </div>
          <div class="receipt-item receipt-total">
            <span>Total Paid:</span>
            <span id="receipt-total"></span>
          </div>
        </div>
        
        <button class="btn btn-secondary" onclick="downloadReceipt()">
          <i class="fas fa-download"></i> Download Receipt
        </button>
        <button class="btn btn-outline" onclick="backToHome()">
          <i class="fas fa-home"></i> Back to Home
        </button>
      </div>
    </div>

    <!-- Payment History Tab -->
    <div id="history" class="tab-content">
      <div class="card">
        <h2><i class="fas fa-history"></i> Payment History</h2>
        <div id="history-list">
          <?php if (empty($payment_history)): ?>
            <p style="text-align: center; padding: 20px; color: var(--gray);">No payment history found.</p>
          <?php else: ?>
            <?php foreach ($payment_history as $payment): ?>
              <div class="history-item">
                <div>
                  <div style="font-weight: bold;">Order <?php echo htmlspecialchars($payment['order_id']); ?></div>
                  <div style="font-size: 0.9rem; color: var(--gray);"><?php echo date('d-M-Y H:i', strtotime($payment['date'])); ?></div>
                  <div style="font-size: 0.9rem; color: var(--gray);"><?php echo htmlspecialchars($payment['method']); ?></div>
                </div>
                <div style="text-align: right;">
                  <div style="font-weight: bold;">RM<?php echo number_format($payment['amount'], 2); ?></div>
                  <div class="status status-<?php echo htmlspecialchars($payment['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($payment['status'])); ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        
        <button class="btn btn-outline" style="margin-top: 20px;" onclick="exportHistory()">
          <i class="fas fa-file-export"></i> Export History
        </button>
      </div>
    </div>

    <!-- Refund Request Tab -->
    <div id="refund" class="tab-content">
      <div class="card">
        <h2><i class="fas fa-exchange-alt"></i> Refund Request</h2>
        <p>Submit a refund request for your order. Our team will review your request within 3-5 business days.</p>
        
        <div id="refund-alert" class="hidden"></div>
        
        <div class="form-group">
          <label for="refund-order">Order ID</label>
          <select id="refund-order">
            <option value="">-- Select Order --</option>
            <?php foreach ($payment_history as $payment): ?>
              <?php if ($payment['status'] === 'completed'): ?>
                <option value="<?php echo htmlspecialchars($payment['order_id']); ?>">
                  <?php echo htmlspecialchars($payment['order_id']); ?> (RM<?php echo number_format($payment['amount'], 2); ?> - <?php echo date('d-M-Y', strtotime($payment['date'])); ?>)
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <div class="error-message" id="refund-order-error"></div>
        </div>
        
        <div class="form-group">
          <label for="refund-reason">Reason for Refund</label>
          <select id="refund-reason">
            <option value="">-- Select Reason --</option>
            <option>Order not received</option>
            <option>Wrong order delivered</option>
            <option>Food quality issues</option>
            <option>Other</option>
          </select>
          <div class="error-message" id="refund-reason-error"></div>
        </div>
        
        <div class="form-group">
          <label for="refund-details">Additional Details</label>
          <textarea id="refund-details" rows="4" placeholder="Please provide more details about your refund request..."></textarea>
          <div class="error-message" id="refund-details-error"></div>
        </div>
        
        <button class="btn btn-primary" onclick="submitRefundRequest()">
          <i class="fas fa-paper-plane"></i> Submit Request
        </button>
      </div>
    </div>

    <!-- Payment Methods Tab -->
    <div id="methods" class="tab-content">
      <div class="card">
        <h2><i class="far fa-credit-card"></i> My Payment Methods</h2>
        <div id="methods-alert" class="hidden"></div>
        
        <div id="saved-methods">
          <?php if (empty($payment_methods)): ?>
            <p style="text-align: center; padding: 20px; color: var(--gray);">No saved payment methods found.</p>
          <?php else: ?>
            <?php foreach ($payment_methods as $method): ?>
              <div style="background: linear-gradient(to right, rgba(0,0,0,0.02), white); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <div>
                    <div style="font-weight: bold;">
                      <img src="https://logo.clearbit.com/<?php echo strtolower(htmlspecialchars($method['card_type'])); ?>.com?size=24" class="card-logo" onerror="this.style.display='none'">
                      <?php echo htmlspecialchars($method['card_type']); ?> •••• <?php echo htmlspecialchars($method['last_four']); ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--gray);">Expires <?php echo htmlspecialchars($method['expiry']); ?></div>
                    <div style="font-size: 0.9rem; color: var(--gray);"><?php echo htmlspecialchars($method['name']); ?></div>
                  </div>
                  <button class="btn btn-danger" onclick="removePaymentMethod(<?php echo $method['id']; ?>)">
                    <i class="fas fa-trash-alt"></i> Remove
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        
        <h3><i class="fas fa-plus-circle"></i> Add New Payment Method</h3>
        
        <div class="form-group">
          <label for="new-card-number">Card Number</label>
          <div style="position: relative;">
            <input type="text" id="new-card-number" placeholder="1234 5678 9012 3456" maxlength="19" inputmode="numeric">
            <i class="fab fa-cc-visa card-icon" id="new-card-type-icon"></i>
          </div>
          <div class="error-message" id="new-card-number-error"></div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
          <div class="form-group">
            <label for="new-expiry">Expiry Date</label>
            <input type="text" id="new-expiry" placeholder="MM/YY" maxlength="5" inputmode="numeric">
            <div class="error-message" id="new-expiry-error"></div>
          </div>
          <div class="form-group">
            <label for="new-cvv">CVV</label>
            <input type="text" id="new-cvv" placeholder="123" maxlength="4" inputmode="numeric">
            <div class="error-message" id="new-cvv-error"></div>
          </div>
        </div>
        
        <div class="form-group">
          <label for="new-card-name">Name on Card</label>
          <input type="text" id="new-card-name" placeholder="John Doe">
          <div class="error-message" id="new-card-name-error"></div>
        </div>
        
        <button class="btn btn-primary" onclick="savePaymentMethod()">
          <i class="fas fa-save"></i> Save Card
        </button>
      </div>
    </div>
  </div>

  <script>
    // Tab navigation
    function openTab(tabId) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Remove active class from all tabs
      document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Show selected tab content
      document.getElementById(tabId).classList.add('active');
      
      // Add active class to clicked tab
      event.currentTarget.classList.add('active');
    }
    
    // Payment method selection
    function selectPaymentMethod(method) {
      // Update UI for method selection
      document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('active');
      });
      event.currentTarget.classList.add('active');
      
      // Show relevant details
      document.getElementById('card-details').classList.add('hidden');
      document.getElementById('bank-details').classList.add('hidden');
      document.getElementById('wallet-details').classList.add('hidden');
      
      if (method === 'card') {
        document.getElementById('card-details').classList.remove('hidden');
      } else if (method === 'bank') {
        document.getElementById('bank-details').classList.remove('hidden');
        showBankLogin();
      } else if (method === 'wallet') {
        document.getElementById('wallet-details').classList.remove('hidden');
        showWalletLogin();
      }
    }
    
    // Show bank login form when bank is selected
    function showBankLogin() {
      const bankSelect = document.getElementById('bank-select');
      const bankLogin = document.getElementById('bank-login');
      
      if (bankSelect.value) {
        bankLogin.classList.remove('hidden');
      } else {
        bankLogin.classList.add('hidden');
      }
    }
    
    // Show wallet login form when wallet is selected
    function showWalletLogin() {
      const walletSelect = document.getElementById('wallet-select');
      const walletLogin = document.getElementById('wallet-login');
      
      if (walletSelect.value) {
        walletLogin.classList.remove('hidden');
      } else {
        walletLogin.classList.add('hidden');
      }
    }
    
    // Authenticate bank credentials
    function authenticateBank() {
      // In a real app, this would call the bank's API
      showAlert('bank-details', 'Bank authentication successful! You can now proceed with payment.', 'success');
    }
    
    // Authenticate wallet credentials
    function authenticateWallet() {
      // In a real app, this would call the wallet's API
      showAlert('wallet-details', 'Wallet authentication successful! You can now proceed with payment.', 'success');
    }
    
    // Format card number with spaces
    function formatCardNumber(input) {
      // Remove all non-digit characters
      let value = input.value.replace(/\D/g, '');
      
      // Add spaces every 4 digits
      value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
      
      // Update the input value
      input.value = value;
      
      // Update card type icon
      updateCardTypeIcon(value, input.id.includes('new') ? 'new-card-type-icon' : 'card-type-icon');
    }
    
    // Update card type icon based on first digit
    function updateCardTypeIcon(cardNumber, iconId) {
      const icon = document.getElementById(iconId);
      if (!icon) return;
      
      // Remove all classes that start with 'fa-cc-'
      Array.from(icon.classList).forEach(className => {
        if (className.startsWith('fa-cc-')) {
          icon.classList.remove(className);
        }
      });
      
      // Determine card type based on first digit
      const firstDigit = cardNumber.charAt(0);
      let cardType = '';
      
      if (firstDigit === '4') {
        cardType = 'visa';
      } else if (firstDigit === '5') {
        cardType = 'mastercard';
      } else if (firstDigit === '3') {
        cardType = 'amex';
      } else if (firstDigit === '6') {
        cardType = 'discover';
      }
      
      if (cardType) {
        icon.classList.add(`fa-cc-${cardType}`);
      }
    }
    
    // Format expiry date
    function formatExpiryDate(input) {
      let value = input.value.replace(/\D/g, '');
      
      if (value.length > 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
      }
      
      input.value = value;
    }
    
    // Validate number input (prevent letters)
    function validateNumberInput(input, maxLength = null) {
      // Remove any non-digit characters
      input.value = input.value.replace(/\D/g, '');
      
      // Limit length if specified
      if (maxLength && input.value.length > maxLength) {
        input.value = input.value.substring(0, maxLength);
      }
    }
    
    // Validate amount input
    function validateAmount(input) {
      // Allow only numbers and one decimal point
      input.value = input.value.replace(/[^0-9.]/g, '');
      
      // Ensure only one decimal point
      const decimalCount = (input.value.match(/\./g) || []).length;
      if (decimalCount > 1) {
        input.value = input.value.substring(0, input.value.lastIndexOf('.'));
      }
      
      // Limit to 2 decimal places
      if (input.value.includes('.')) {
        const parts = input.value.split('.');
        if (parts[1].length > 2) {
          input.value = parts[0] + '.' + parts[1].substring(0, 2);
        }
      }
    }
    
    // Process payment
    function processPayment() {
      // Reset error states
      document.querySelectorAll('.error-message').forEach(el => {
        el.style.display = 'none';
      });
      document.querySelectorAll('input, select').forEach(el => {
        el.classList.remove('error');
      });
      
      // Validate inputs
      let isValid = true;
      const orderId = document.getElementById('order-id').value.trim();
      const amount = document.getElementById('payment-amount').value.trim();
      
      if (!orderId) {
        document.getElementById('order-id-error').textContent = 'Please enter an Order ID';
        document.getElementById('order-id-error').style.display = 'block';
        document.getElementById('order-id').classList.add('error');
        isValid = false;
      }
      
      if (!amount || isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
        document.getElementById('amount-error').textContent = 'Please enter a valid amount';
        document.getElementById('amount-error').style.display = 'block';
        document.getElementById('payment-amount').classList.add('error');
        isValid = false;
      }
      
      // Validate payment method specific fields
      const activeMethod = document.querySelector('.payment-method.active').textContent;
      let paymentMethod = '';
      
      if (activeMethod.includes('Credit/Debit')) {
        const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
        const expiry = document.getElementById('expiry-date').value;
        const cvv = document.getElementById('cvv').value;
        const cardName = document.getElementById('card-name').value.trim();
        
        if (!cardNumber || cardNumber.length < 16) {
          document.getElementById('card-number-error').textContent = 'Please enter a valid card number';
          document.getElementById('card-number-error').style.display = 'block';
          document.getElementById('card-number').classList.add('error');
          isValid = false;
        }
        
        if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
          document.getElementById('expiry-error').textContent = 'Please enter a valid expiry date (MM/YY)';
          document.getElementById('expiry-error').style.display = 'block';
          document.getElementById('expiry-date').classList.add('error');
          isValid = false;
        }
        
        if (!cvv || cvv.length < 3) {
          document.getElementById('cvv-error').textContent = 'Please enter a valid CVV';
          document.getElementById('cvv-error').style.display = 'block';
          document.getElementById('cvv').classList.add('error');
          isValid = false;
        }
        
        if (!cardName) {
          document.getElementById('card-name-error').textContent = 'Please enter the name on card';
          document.getElementById('card-name-error').style.display = 'block';
          document.getElementById('card-name').classList.add('error');
          isValid = false;
        }
        
        if (isValid) {
          // Determine card type
          const firstDigit = cardNumber.charAt(0);
          const cardType = firstDigit === '4' ? 'VISA' : 
                         (firstDigit === '5' ? 'Mastercard' : 
                         (firstDigit === '3' ? 'American Express' : 'Card'));
          
          paymentMethod = `${cardType} •••• ${cardNumber.slice(-4)}`;
        }
      } else if (activeMethod.includes('Online Banking')) {
        const bank = document.getElementById('bank-select').value;
        if (!bank) {
          document.getElementById('bank-error').textContent = 'Please select a bank';
          document.getElementById('bank-error').style.display = 'block';
          document.getElementById('bank-select').classList.add('error');
          isValid = false;
        } else {
          paymentMethod = document.getElementById('bank-select').selectedOptions[0].text;
        }
      } else if (activeMethod.includes('e-Wallet')) {
        const wallet = document.getElementById('wallet-select').value;
        if (!wallet) {
          document.getElementById('wallet-error').textContent = 'Please select an e-Wallet';
          document.getElementById('wallet-error').style.display = 'block';
          document.getElementById('wallet-select').classList.add('error');
          isValid = false;
        } else {
          paymentMethod = document.getElementById('wallet-select').selectedOptions[0].text;
        }
      }
      
      if (!isValid) {
        return;
      }
      
      // Show processing screen
      document.getElementById('payment-processing').classList.remove('hidden');
      document.getElementById('payment-success').classList.add('hidden');
      
      // Hide payment form, show confirmation
      document.querySelector('#payment.tab-content').classList.add('hidden');
      document.getElementById('confirmation').classList.remove('hidden');
      
      // Prepare data for AJAX request
      const formData = new FormData();
      formData.append('process_payment', 'true');
      formData.append('order_id', orderId);
      formData.append('amount', amount);
      formData.append('payment_method', paymentMethod);
      
      // Send AJAX request to process payment
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          // Hide processing, show success
          document.getElementById('payment-processing').classList.add('hidden');
          document.getElementById('payment-success').classList.remove('hidden');
          
          // Update confirmation message
          document.getElementById('confirm-message').textContent = 
            `Your payment of RM${parseFloat(amount).toFixed(2)} for order ${orderId} was successful.`;
          
          // Update receipt details
          document.getElementById('receipt-order-id').textContent = orderId;
          document.getElementById('receipt-amount').textContent = `RM${parseFloat(amount).toFixed(2)}`;
          document.getElementById('receipt-total').textContent = `RM${parseFloat(amount).toFixed(2)}`;
          document.getElementById('receipt-method').textContent = paymentMethod;
          
          // Add current date to receipt
          const now = new Date();
          const options = { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
          };
          document.getElementById('receipt-date').textContent = 
            now.toLocaleDateString('en-GB', options);
          
          // Create confetti effect
          createConfetti();
          
          // Reload the page to update the history tab
          setTimeout(() => {
            window.location.reload();
          }, 3000);
        } else {
          showAlert('payment', 'Payment failed. Please try again.', 'danger');
          backToHome();
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showAlert('payment', 'An error occurred. Please try again.', 'danger');
        backToHome();
      });
    }
    
    // Create confetti effect
    function createConfetti() {
      const colors = ['#0066cc', '#ff6b00', '#28a745', '#dc3545', '#6c757d'];
      
      for (let i = 0; i < 100; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.width = Math.random() * 10 + 5 + 'px';
        confetti.style.height = Math.random() * 10 + 5 + 'px';
        confetti.style.animationDuration = Math.random() * 2 + 2 + 's';
        document.body.appendChild(confetti);
        
        // Remove confetti after animation
        setTimeout(() => {
          confetti.remove();
        }, 3000);
      }
    }
    
    // Download receipt
    function downloadReceipt() {
      alert('Receipt downloaded successfully!');
    }
    
    // Back to home
    function backToHome() {
      document.getElementById('confirmation').classList.add('hidden');
      document.querySelector('#payment.tab-content').classList.remove('hidden');
      
      // Clear form
      document.getElementById('order-id').value = '';
      document.getElementById('payment-amount').value = '';
      document.getElementById('card-number').value = '';
      document.getElementById('expiry-date').value = '';
      document.getElementById('cvv').value = '';
      document.getElementById('card-name').value = '';
      document.getElementById('bank-select').value = '';
      document.getElementById('wallet-select').value = '';
      document.getElementById('bank-username').value = '';
      document.getElementById('bank-password').value = '';
      document.getElementById('bank-otp').value = '';
      document.getElementById('wallet-phone').value = '';
      document.getElementById('wallet-pin').value = '';
      
      // Reset payment method to card
      document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('active'));
      document.querySelector('.payment-method:nth-child(1)').classList.add('active');
      selectPaymentMethod('card');
    }
    
    // Export history
    function exportHistory() {
      alert('Payment history exported successfully!');
    }
    
    // Submit refund request
    function submitRefundRequest() {
      // Reset error states
      document.querySelectorAll('#refund .error-message').forEach(el => {
        el.style.display = 'none';
      });
      document.querySelectorAll('#refund input, #refund select, #refund textarea').forEach(el => {
        el.classList.remove('error');
      });
      
      const orderId = document.getElementById('refund-order').value;
      const reason = document.getElementById('refund-reason').value;
      const details = document.getElementById('refund-details').value.trim();
      
      let isValid = true;
      
      if (!orderId) {
        document.getElementById('refund-order-error').textContent = 'Please select an order';
        document.getElementById('refund-order-error').style.display = 'block';
        document.getElementById('refund-order').classList.add('error');
        isValid = false;
      }
      
      if (!reason) {
        document.getElementById('refund-reason-error').textContent = 'Please select a reason for refund';
        document.getElementById('refund-reason-error').style.display = 'block';
        document.getElementById('refund-reason').classList.add('error');
        isValid = false;
      }
      
      if (!details) {
        document.getElementById('refund-details-error').textContent = 'Please provide additional details';
        document.getElementById('refund-details-error').style.display = 'block';
        document.getElementById('refund-details').classList.add('error');
        isValid = false;
      }
      
      if (!isValid) {
        return;
      }
      
      // Prepare data for AJAX request
      const formData = new FormData();
      formData.append('submit_refund', 'true');
      formData.append('refund_order', orderId);
      formData.append('refund_reason', reason);
      formData.append('refund_details', details);
      
      // Send AJAX request to submit refund
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          showAlert('refund-alert', 'Refund request submitted successfully! Our team will review it shortly.', 'success');
          
          // Clear form
          document.getElementById('refund-order').value = '';
          document.getElementById('refund-reason').value = '';
          document.getElementById('refund-details').value = '';
          
          // Reload the page to update the history tab
          setTimeout(() => {
            window.location.reload();
          }, 2000);
        } else {
          showAlert('refund-alert', 'Failed to submit refund request. Please try again.', 'danger');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showAlert('refund-alert', 'An error occurred. Please try again.', 'danger');
      });
    }
    
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
      
      // Hide alert after 5 seconds
      setTimeout(() => {
        container.classList.add('hidden');
      }, 5000);
    }
    
    // Remove payment method
    function removePaymentMethod(id) {
      if (confirm('Are you sure you want to remove this payment method?')) {
        // Prepare data for AJAX request
        const formData = new FormData();
        formData.append('remove_payment_method', 'true');
        formData.append('method_id', id);
        
        // Send AJAX request to remove payment method
        fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            showAlert('methods-alert', 'Payment method removed successfully', 'success');
            // Reload the page to update the methods list
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            showAlert('methods-alert', 'Failed to remove payment method', 'danger');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('methods-alert', 'An error occurred. Please try again.', 'danger');
        });
      }
    }
    
    // Save new payment method
    function savePaymentMethod() {
      // Reset error states
      document.querySelectorAll('#methods .error-message').forEach(el => {
        el.style.display = 'none';
      });
      document.querySelectorAll('#methods input').forEach(el => {
        el.classList.remove('error');
      });
      
      const cardNumber = document.getElementById('new-card-number').value.replace(/\s/g, '');
      const expiry = document.getElementById('new-expiry').value.trim();
      const cvv = document.getElementById('new-cvv').value.trim();
      const name = document.getElementById('new-card-name').value.trim();
      
      let isValid = true;
      
      if (!cardNumber || cardNumber.length < 16) {
        document.getElementById('new-card-number-error').textContent = 'Please enter a valid card number';
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
      
      if (!cvv || cvv.length < 3) {
        document.getElementById('new-cvv-error').textContent = 'Please enter a valid CVV';
        document.getElementById('new-cvv-error').style.display = 'block';
        document.getElementById('new-cvv').classList.add('error');
        isValid = false;
      }
      
      if (!name) {
        document.getElementById('new-card-name-error').textContent = 'Please enter the name on card';
        document.getElementById('new-card-name-error').style.display = 'block';
        document.getElementById('new-card-name').classList.add('error');
        isValid = false;
      }
      
      if (!isValid) {
        return;
      }
      
      // Prepare data for AJAX request
      const formData = new FormData();
      formData.append('save_payment_method', 'true');
      formData.append('new_card_number', cardNumber);
      formData.append('new_expiry', expiry);
      formData.append('new_cvv', cvv);
      formData.append('new_card_name', name);
      
      // Send AJAX request to save payment method
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          showAlert('methods-alert', 'Payment method added successfully', 'success');
          // Clear form
          document.getElementById('new-card-number').value = '';
          document.getElementById('new-expiry').value = '';
          document.getElementById('new-cvv').value = '';
          document.getElementById('new-card-name').value = '';
          // Reload the page to update the methods list
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showAlert('methods-alert', 'Failed to add payment method', 'danger');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showAlert('methods-alert', 'An error occurred. Please try again.', 'danger');
      });
    }
    
    // Initialize the application
    document.addEventListener('DOMContentLoaded', function() {
      // Set up event listeners for input formatting
      document.getElementById('card-number').addEventListener('input', function() {
        formatCardNumber(this);
      });
      
      document.getElementById('new-card-number').addEventListener('input', function() {
        formatCardNumber(this);
      });
      
      document.getElementById('expiry-date').addEventListener('input', function() {
        formatExpiryDate(this);
      });
      
      document.getElementById('new-expiry').addEventListener('input', function() {
        formatExpiryDate(this);
      });
      
      document.getElementById('cvv').addEventListener('input', function() {
        validateNumberInput(this, 4);
      });
      
      document.getElementById('new-cvv').addEventListener('input', function() {
        validateNumberInput(this, 4);
      });
      
      document.getElementById('payment-amount').addEventListener('input', function() {
        validateAmount(this);
      });
      
      // Initialize default payment method
      selectPaymentMethod('card');
    });
  </script>
</body>
</html>