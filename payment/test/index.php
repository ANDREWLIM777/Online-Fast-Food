<?php
require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payment'])) {
        // Process payment and create order
        $delivery_type = $_POST['delivery_type'];
        $delivery_address = $delivery_type === 'delivery' ? $_POST['delivery_address'] : '';
        $delivery_fee = $delivery_type === 'delivery' ? 5.00 : 0.00;
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';
        
        // Calculate cart total
        $subtotal = calculateCartTotal($conn, $_SESSION['session_id']);
        $tax_rate = 0.06; // 6% tax
        $tax_amount = round($subtotal * $tax_rate, 2);
        $total_amount = $subtotal + $tax_amount + $delivery_fee;
        
        // Generate order ID
        $order_id = generateOrderId();
        
        // Create order
        $stmt = $conn->prepare("INSERT INTO orders (order_id, session_id, order_date, total_amount, status, payment_status, delivery_type, delivery_address, delivery_fee, tax_amount, notes) VALUES (?, ?, NOW(), ?, 'pending', 'pending', ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdssdds", $order_id, $_SESSION['session_id'], $total_amount, $delivery_type, $delivery_address, $delivery_fee, $tax_amount, $notes);
        $stmt->execute();
        
        // Add order items
        $cart_items = getCartItems($conn, $_SESSION['session_id']);
        foreach ($cart_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siid", $order_id, $item['item_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }
        
        // Record payment
        $stmt = $conn->prepare("INSERT INTO payment_history (order_id, date, amount, status, method) VALUES (?, NOW(), ?, 'completed', ?)");
        $stmt->bind_param("sds", $order_id, $total_amount, $payment_method);
        $stmt->execute();
        
        // Update order payment status
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ?");
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        
        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE session_id = ?");
        $stmt->bind_param("s", $_SESSION['session_id']);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Order placed successfully',
            'order_id' => $order_id,
            'total_amount' => $total_amount
        ]);
        exit;
        
    } elseif (isset($_POST['submit_refund'])) {
        // Process refund request
        $order_id = $_POST['refund_order'];
        $reason = $_POST['refund_reason'];
        $details = $_POST['refund_details'];
        
        $stmt = $conn->prepare("INSERT INTO refund_requests (order_id, reason, details, date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $order_id, $reason, $details);
        $stmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Refund request submitted successfully']);
        exit;
        
    } elseif (isset($_POST['save_payment_method'])) {
        // Save new payment method
        $card_number = str_replace(' ', '', $_POST['new_card_number']);
        $expiry = $_POST['new_expiry'];
        $cvv = $_POST['new_cvv'];
        $name = $_POST['new_card_name'];
        
        $first_digit = substr($card_number, 0, 1);
        $card_type = $first_digit === '4' ? 'VISA' : 
                     ($first_digit === '5' ? 'Mastercard' : 
                     ($first_digit === '3' ? 'American Express' : 'Card'));
        
        $last_four = substr($card_number, -4);
        
        $stmt = $conn->prepare("INSERT INTO payment_methods (session_id, type, last_four, card_type, expiry, name) VALUES (?, 'card', ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $_SESSION['session_id'], $last_four, $card_type, $expiry, $name);
        $stmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Payment method saved successfully']);
        exit;
        
    } elseif (isset($_POST['remove_payment_method'])) {
        // Remove payment method
        $id = $_POST['method_id'];
        
        $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ? AND session_id = ?");
        $stmt->bind_param("is", $id, $_SESSION['session_id']);
        $stmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Payment method removed successfully']);
        exit;
    }
}

// Fetch data from database
$cart_items = getCartItems($conn, $_SESSION['session_id']);
$cart_total = calculateCartTotal($conn, $_SESSION['session_id']);
$payment_history = getPaymentHistory($conn, $_SESSION['session_id']);
$payment_methods = getPaymentMethods($conn, $_SESSION['session_id']);
$refund_requests = getRefundRequests($conn, $_SESSION['session_id']);
$customer_orders = getCustomerOrders($conn, $_SESSION['session_id']);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fast Food Payment Module</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container">
    <header>
      <h1><i class="fas fa-hamburger"></i> Fast Food Payment System</h1>
      <p>Secure and convenient payment solutions for your orders</p>
    </header>

    <div class="tab-container">
      <div class="tabs">
        <div class="tab active" onclick="openTab('payment')">Checkout</div>
        <div class="tab" onclick="openTab('history')">Order History</div>
        <div class="tab" onclick="openTab('refund')">Refund Request</div>
        <div class="tab" onclick="openTab('methods')">Payment Methods</div>
      </div>
    </div>

    <!-- Payment Gateway Tab -->
    <div id="payment" class="tab-content active">
      <div class="card">
        <h2><i class="fas fa-shopping-cart"></i> Order Summary</h2>
        
        <div id="cart-items">
          <?php if (empty($cart_items)): ?>
            <p style="text-align: center; padding: 20px; color: var(--gray);">Your cart is empty.</p>
          <?php else: ?>
            <?php foreach ($cart_items as $item): ?>
              <div class="cart-item">
                <div class="cart-item-image">
                  <?php if ($item['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                  <?php else: ?>
                    <div class="no-image"><i class="fas fa-utensils"></i></div>
                  <?php endif; ?>
                </div>
                <div class="cart-item-details">
                  <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                  <div class="cart-item-price">RM<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></div>
                </div>
                <div class="cart-item-total">RM<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
              </div>
            <?php endforeach; ?>
            
            <div class="cart-summary">
              <div class="summary-row">
                <span>Subtotal:</span>
                <span>RM<?php echo number_format($cart_total, 2); ?></span>
              </div>
              <div class="summary-row">
                <span>Tax (6%):</span>
                <span>RM<?php echo number_format($cart_total * 0.06, 2); ?></span>
              </div>
              <div class="summary-row" id="delivery-fee-row">
                <span>Delivery Fee:</span>
                <span>RM0.00</span>
              </div>
              <div class="summary-row total">
                <span>Total:</span>
                <span>RM<?php echo number_format($cart_total * 1.06, 2); ?></span>
              </div>
            </div>
          <?php endif; ?>
        </div>
        
        <?php if (!empty($cart_items)): ?>
          <h3><i class="fas fa-truck"></i> Delivery Options</h3>
          <div class="delivery-options">
            <div class="delivery-option active" onclick="selectDeliveryOption('pickup')">
              <i class="fas fa-store"></i>
              <div>Pickup</div>
              <div class="option-description">Collect your order at our restaurant</div>
            </div>
            <div class="delivery-option" onclick="selectDeliveryOption('delivery')">
              <i class="fas fa-motorcycle"></i>
              <div>Delivery</div>
              <div class="option-description">RM5.00 delivery fee</div>
            </div>
          </div>
          
          <div id="delivery-address-section" class="form-group hidden">
            <label for="delivery-address">Delivery Address</label>
            <textarea id="delivery-address" rows="3" placeholder="Enter your full delivery address"></textarea>
            <div class="error-message" id="delivery-address-error"></div>
          </div>
          
          <div class="form-group">
            <label for="order-notes">Order Notes (Optional)</label>
            <textarea id="order-notes" rows="2" placeholder="Any special instructions for your order..."></textarea>
          </div>
          
          <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
          <div class="payment-methods-select">
            <?php if (!empty($payment_methods)): ?>
              <?php foreach ($payment_methods as $method): ?>
                <div class="payment-method-option" onclick="selectPaymentMethod(<?php echo $method['id']; ?>)">
                  <div class="payment-method-icon">
                    <i class="fab fa-cc-<?php echo strtolower($method['card_type']); ?>"></i>
                  </div>
                  <div class="payment-method-details">
                    <div class="payment-method-name"><?php echo htmlspecialchars($method['name']); ?></div>
                    <div class="payment-method-info"><?php echo htmlspecialchars($method['card_type']); ?> •••• <?php echo htmlspecialchars($method['last_four']); ?></div>
                  </div>
                  <div class="payment-method-check">
                    <i class="fas fa-check"></i>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="payment-method-option" onclick="showNewCardForm()">
              <div class="payment-method-icon">
                <i class="fas fa-plus"></i>
              </div>
              <div class="payment-method-details">
                <div class="payment-method-name">Add New Payment Method</div>
              </div>
            </div>
          </div>
          
          <div id="new-card-form" class="form-group hidden">
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
            
            <button class="btn btn-secondary" onclick="savePaymentMethod()">
              <i class="fas fa-save"></i> Save Card
            </button>
          </div>
          
          <button class="btn btn-primary" onclick="processPayment()" id="pay-now-btn">
            <i class="fas fa-lock"></i> Place Order
          </button>
        <?php endif; ?>
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
        <h2><i class="fas fa-check-circle" style="color: var(--success);"></i> Order Placed Successfully!</h2>
        <p id="confirm-message">Your order has been placed successfully.</p>
        
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
            <span>Delivery Method:</span>
            <span id="receipt-delivery-method"></span>
          </div>
          <div class="receipt-item">
            <span>Payment Method:</span>
            <span id="receipt-method"></span>
          </div>
          <div class="receipt-item">
            <span>Subtotal:</span>
            <span id="receipt-subtotal"></span>
          </div>
          <div class="receipt-item">
            <span>Tax:</span>
            <span id="receipt-tax"></span>
          </div>
          <div class="receipt-item">
            <span>Delivery Fee:</span>
            <span id="receipt-delivery-fee"></span>
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

    <!-- Order History Tab -->
    <div id="history" class="tab-content">
      <div class="card">
        <h2><i class="fas fa-history"></i> Order History</h2>
        <div id="history-list">
          <?php if (empty($customer_orders)): ?>
            <p style="text-align: center; padding: 20px; color: var(--gray);">No order history found.</p>
          <?php else: ?>
            <?php foreach ($customer_orders as $order): ?>
              <div class="history-item" onclick="showOrderDetails('<?php echo $order['order_id']; ?>')">
                <div>
                  <div style="font-weight: bold;">Order <?php echo htmlspecialchars($order['order_id']); ?></div>
                  <div style="font-size: 0.9rem; color: var(--gray);">
                    <?php echo date('d-M-Y H:i', strtotime($order['order_date'])); ?>
                    • <?php echo ucfirst($order['delivery_type']); ?>
                  </div>
                </div>
                <div style="text-align: right;">
                  <div style="font-weight: bold;">RM<?php echo number_format($order['total_amount'], 2); ?></div>
                  <div class="status status-<?php echo htmlspecialchars($order['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
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
            <?php foreach ($customer_orders as $order): ?>
              <?php if ($order['payment_status'] === 'paid' && $order['status'] !== 'cancelled'): ?>
                <option value="<?php echo htmlspecialchars($order['order_id']); ?>">
                  <?php echo htmlspecialchars($order['order_id']); ?> (RM<?php echo number_format($order['total_amount'], 2); ?> - <?php echo date('d-M-Y', strtotime($order['order_date'])); ?>
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
                      <i class="fab fa-cc-<?php echo strtolower(htmlspecialchars($method['card_type'])); ?>"></i>
                      <?php echo htmlspecialchars($method['card_type']); ?> •••• <?php echo htmlspecialchars($method['last_four']); ?></div>
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

  <!-- Order Details Modal -->
  <div id="order-details-modal" class="modal hidden">
    <div class="modal-content">
      <span class="close-modal" onclick="closeModal()">&times;</span>
      <h2><i class="fas fa-receipt"></i> Order Details</h2>
      <div id="order-details-content"></div>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>