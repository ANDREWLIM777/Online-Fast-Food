<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Database configuration
$servername = "localhost";
$username = "brizo_user";
$password = "secure_password";
$dbname = "brizo";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Create tables if they don't exist (same as before, plus the new ones above)
// ... [your existing table creation code] ...

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $item_id = $_POST['item_id'];
        $quantity = $_POST['quantity'] ?? 1;
        
        // Get item details from database
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();
            
            // Add to cart or update quantity
            if (isset($_SESSION['cart'][$item_id])) {
                $_SESSION['cart'][$item_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$item_id] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $quantity,
                    'image' => $item['image_url']
                ];
            }
            
            echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['cart'])]);
            exit;
        }
    }
    elseif (isset($_POST['update_cart_item'])) {
        $item_id = $_POST['item_id'];
        $quantity = $_POST['quantity'];
        
        if (isset($_SESSION['cart'][$item_id])) {
            if ($quantity > 0) {
                $_SESSION['cart'][$item_id]['quantity'] = $quantity;
            } else {
                unset($_SESSION['cart'][$item_id]);
            }
        }
        
        echo json_encode(['status' => 'success']);
        exit;
    }
    elseif (isset($_POST['remove_cart_item'])) {
        $item_id = $_POST['item_id'];
        
        if (isset($_SESSION['cart'][$item_id])) {
            unset($_SESSION['cart'][$item_id]);
        }
        
        echo json_encode(['status' => 'success']);
        exit;
    }
    elseif (isset($_POST['process_payment'])) {
        // Process payment and create order
        $delivery_type = $_POST['delivery_type'];
        $delivery_address = $_POST['delivery_address'] ?? '';
        $contact_number = $_POST['contact_number'];
        $special_instructions = $_POST['special_instructions'] ?? '';
        $payment_method = $_POST['payment_method'];
        
        // Calculate totals
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $delivery_fee = ($delivery_type === 'delivery') ? 5.00 : 0.00;
        $total_amount = $subtotal + $delivery_fee;
        
        // Generate unique order ID
        $order_id = 'ORD-' . strtoupper(uniqid());
        
        // Create order
        $stmt = $conn->prepare("INSERT INTO orders (order_id, order_date, total_amount, delivery_type, delivery_address, delivery_fee, contact_number, special_instructions) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdssdss", $order_id, $total_amount, $delivery_type, $delivery_address, $delivery_fee, $contact_number, $special_instructions);
        $stmt->execute();
        
        // Add order items
        foreach ($_SESSION['cart'] as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siid", $order_id, $item['id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }
        
        // Record payment
        $stmt = $conn->prepare("INSERT INTO payment_history (order_id, date, amount, status, method) VALUES (?, NOW(), ?, 'completed', ?)");
        $stmt->bind_param("sds", $order_id, $total_amount, $payment_method);
        $stmt->execute();
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'order_id' => $order_id,
            'total_amount' => number_format($total_amount, 2),
            'delivery_type' => $delivery_type,
            'delivery_fee' => number_format($delivery_fee, 2),
            'subtotal' => number_format($subtotal, 2)
        ]);
        exit;
    }
    // ... [your existing payment method handlers] ...
}

// Fetch menu items
$menu_items = [];
$result = $conn->query("SELECT * FROM menu_items");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

// Fetch other data (same as before)
// ... [your existing data fetching code] ...

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <!-- [Your existing head content] -->
  <style>
    /* Add these styles to your existing CSS */
    .cart-container {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
      margin-bottom: 25px;
    }
    
    .cart-item {
      display: flex;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid #eee;
    }
    
    .cart-item-image {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
      margin-right: 15px;
    }
    
    .cart-item-details {
      flex: 1;
    }
    
    .cart-item-name {
      font-weight: bold;
      margin-bottom: 5px;
    }
    
    .cart-item-price {
      color: var(--primary);
      font-weight: bold;
    }
    
    .cart-item-actions {
      display: flex;
      align-items: center;
    }
    
    .quantity-input {
      width: 50px;
      text-align: center;
      margin: 0 10px;
    }
    
    .delivery-options {
      display: flex;
      gap: 15px;
      margin: 20px 0;
    }
    
    .delivery-option {
      flex: 1;
      text-align: center;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .delivery-option.active {
      border-color: var(--primary);
      background-color: rgba(0, 119, 204, 0.05);
    }
    
    .delivery-option i {
      font-size: 1.5rem;
      color: var(--primary);
      margin-bottom: 10px;
    }
    
    .summary-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    
    .summary-total {
      font-weight: bold;
      font-size: 1.2rem;
      border-top: 1px solid #ddd;
      padding-top: 10px;
      margin-top: 10px;
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
        <div class="tab active" onclick="openTab('cart')">Your Order</div>
        <div class="tab" onclick="openTab('payment')">Payment</div>
        <div class="tab" onclick="openTab('history')">Order History</div>
        <div class="tab" onclick="openTab('methods')">Payment Methods</div>
      </div>
    </div>

    <!-- Cart Tab -->
    <div id="cart" class="tab-content active">
      <div class="cart-container">
        <h2><i class="fas fa-shopping-cart"></i> Your Order</h2>
        
        <div id="cart-items">
          <?php if (empty($_SESSION['cart'])): ?>
            <p style="text-align: center; padding: 20px; color: var(--gray);">Your cart is empty.</p>
          <?php else: ?>
            <?php 
              $subtotal = 0;
              foreach ($_SESSION['cart'] as $item): 
              $subtotal += $item['price'] * $item['quantity'];
            ?>
              <div class="cart-item" data-item-id="<?php echo $item['id']; ?>">
                <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="cart-item-image">
                <div class="cart-item-details">
                  <div class="cart-item-name"><?php echo $item['name']; ?></div>
                  <div class="cart-item-price">RM<?php echo number_format($item['price'], 2); ?></div>
                </div>
                <div class="cart-item-actions">
                  <button class="btn btn-outline" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">
                    <i class="fas fa-minus"></i>
                  </button>
                  <input type="text" class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                         onchange="updateQuantityInput(<?php echo $item['id']; ?>, this.value)">
                  <button class="btn btn-outline" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">
                    <i class="fas fa-plus"></i>
                  </button>
                  <button class="btn btn-danger" onclick="removeItem(<?php echo $item['id']; ?>)">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
            
            <div style="margin-top: 20px;">
              <div class="summary-item">
                <span>Subtotal:</span>
                <span>RM<?php echo number_format($subtotal, 2); ?></span>
              </div>
              <div class="summary-item">
                <span>Delivery Fee:</span>
                <span id="delivery-fee">RM0.00</span>
              </div>
              <div class="summary-item summary-total">
                <span>Total:</span>
                <span id="cart-total">RM<?php echo number_format($subtotal, 2); ?></span>
              </div>
            </div>
            
            <div style="margin-top: 30px;">
              <h3>Delivery Options</h3>
              <div class="delivery-options">
                <div class="delivery-option active" onclick="selectDeliveryOption('pickup')">
                  <i class="fas fa-store"></i>
                  <div>Pick Up</div>
                  <small>Collect at our restaurant</small>
                </div>
                <div class="delivery-option" onclick="selectDeliveryOption('delivery')">
                  <i class="fas fa-truck"></i>
                  <div>Delivery</div>
                  <small>RM5.00 delivery fee</small>
                </div>
              </div>
              
              <div id="delivery-details" class="hidden" style="margin-top: 20px;">
                <div class="form-group">
                  <label for="delivery-address">Delivery Address</label>
                  <textarea id="delivery-address" rows="3" placeholder="Enter your full delivery address"></textarea>
                </div>
              </div>
              
              <div class="form-group">
                <label for="contact-number">Contact Number</label>
                <input type="text" id="contact-number" placeholder="Enter your phone number">
              </div>
              
              <div class="form-group">
                <label for="special-instructions">Special Instructions</label>
                <textarea id="special-instructions" rows="2" placeholder="Any special requests?"></textarea>
              </div>
              
              <button class="btn btn-primary" onclick="proceedToPayment()" <?php echo empty($_SESSION['cart']) ? 'disabled' : ''; ?>>
                <i class="fas fa-arrow-right"></i> Proceed to Payment
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Payment Tab (modified to include cart summary) -->
    <div id="payment" class="tab-content">
      <div class="card">
        <h2><i class="fas fa-credit-card"></i> Payment Gateway</h2>
        
        <div class="receipt" style="margin-bottom: 20px;">
          <h3>Order Summary</h3>
          <div id="payment-order-items">
            <!-- Will be populated by JavaScript -->
          </div>
          <div class="receipt-item">
            <span>Subtotal:</span>
            <span id="payment-subtotal">RM0.00</span>
          </div>
          <div class="receipt-item">
            <span>Delivery Fee:</span>
            <span id="payment-delivery-fee">RM0.00</span>
          </div>
          <div class="receipt-item receipt-total">
            <span>Total:</span>
            <span id="payment-total">RM0.00</span>
          </div>
        </div>
        
        <!-- [Your existing payment form] -->
        
        <button class="btn btn-primary" onclick="processPayment()" id="pay-now-btn">
          <i class="fas fa-lock"></i> Complete Payment
        </button>
      </div>
    </div>

    <!-- [Your existing other tabs] -->
  </div>

  <script>
    // [Your existing JavaScript functions]
    
    // Add these new functions for cart functionality
    function selectDeliveryOption(option) {
      document.querySelectorAll('.delivery-option').forEach(el => {
        el.classList.remove('active');
      });
      event.currentTarget.classList.add('active');
      
      const deliveryFee = option === 'delivery' ? 5.00 : 0.00;
      document.getElementById('delivery-fee').textContent = 'RM' + deliveryFee.toFixed(2);
      
      // Update total
      const subtotal = parseFloat(document.getElementById('cart-total').textContent.replace('RM', '')) - parseFloat(document.getElementById('delivery-fee').textContent.replace('RM', ''));
      const total = subtotal + deliveryFee;
      document.getElementById('cart-total').textContent = 'RM' + total.toFixed(2);
      
      // Show/hide delivery address
      if (option === 'delivery') {
        document.getElementById('delivery-details').classList.remove('hidden');
      } else {
        document.getElementById('delivery-details').classList.add('hidden');
      }
    }
    
    function updateQuantity(itemId, change) {
      const input = document.querySelector(`.cart-item[data-item-id="${itemId}"] .quantity-input`);
      const newQuantity = parseInt(input.value) + change;
      
      if (newQuantity > 0) {
        input.value = newQuantity;
        updateCartItem(itemId, newQuantity);
      }
    }
    
    function updateQuantityInput(itemId, quantity) {
      quantity = parseInt(quantity);
      if (isNaN(quantity) quantity = 1;
      if (quantity < 1) quantity = 1;
      
      updateCartItem(itemId, quantity);
    }
    
    function updateCartItem(itemId, quantity) {
      fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `update_cart_item=true&item_id=${itemId}&quantity=${quantity}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          window.location.reload();
        }
      });
    }
    
    function removeItem(itemId) {
      if (confirm('Remove this item from your cart?')) {
        fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `remove_cart_item=true&item_id=${itemId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            window.location.reload();
          }
        });
      }
    }
    
    function proceedToPayment() {
      const deliveryOption = document.querySelector('.delivery-option.active').textContent.trim().toLowerCase();
      const contactNumber = document.getElementById('contact-number').value.trim();
      
      if (!contactNumber) {
        alert('Please enter your contact number');
        return;
      }
      
      if (deliveryOption === 'delivery') {
        const deliveryAddress = document.getElementById('delivery-address').value.trim();
        if (!deliveryAddress) {
          alert('Please enter your delivery address');
          return;
        }
      }
      
      // Prepare order summary for payment page
      const cartItems = document.querySelectorAll('.cart-item');
      const paymentItemsContainer = document.getElementById('payment-order-items');
      paymentItemsContainer.innerHTML = '';
      
      let subtotal = 0;
      
      cartItems.forEach(item => {
        const itemId = item.dataset.itemId;
        const itemName = item.querySelector('.cart-item-name').textContent;
        const itemPrice = parseFloat(item.querySelector('.cart-item-price').textContent.replace('RM', ''));
        const itemQuantity = parseInt(item.querySelector('.quantity-input').value);
        const itemTotal = itemPrice * itemQuantity;
        
        subtotal += itemTotal;
        
        const itemElement = document.createElement('div');
        itemElement.className = 'receipt-item';
        itemElement.innerHTML = `
          <span>${itemName} x${itemQuantity}</span>
          <span>RM${itemTotal.toFixed(2)}</span>
        `;
        paymentItemsContainer.appendChild(itemElement);
      });
      
      const deliveryFee = deliveryOption === 'delivery' ? 5.00 : 0.00;
      const total = subtotal + deliveryFee;
      
      document.getElementById('payment-subtotal').textContent = 'RM' + subtotal.toFixed(2);
      document.getElementById('payment-delivery-fee').textContent = 'RM' + deliveryFee.toFixed(2);
      document.getElementById('payment-total').textContent = 'RM' + total.toFixed(2);
      
      // Switch to payment tab
      openTab('payment');
    }
    
    // Modify your existing processPayment function to include delivery info
    function processPayment() {
      // Get delivery info
      const deliveryOption = document.querySelector('.delivery-option.active').textContent.trim().toLowerCase();
      const deliveryAddress = deliveryOption === 'delivery' ? document.getElementById('delivery-address').value.trim() : '';
      const contactNumber = document.getElementById('contact-number').value.trim();
      const specialInstructions = document.getElementById('special-instructions').value.trim();
      
      // Get payment method (from your existing code)
      const activeMethod = document.querySelector('.payment-method.active').textContent;
      let paymentMethod = '';
      
      if (activeMethod.includes('Credit/Debit')) {
        const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
        const firstDigit = cardNumber.charAt(0);
        const cardType = firstDigit === '4' ? 'VISA' : 
                       (firstDigit === '5' ? 'Mastercard' : 
                       (firstDigit === '3' ? 'American Express' : 'Card'));
        
        paymentMethod = `${cardType} •••• ${cardNumber.slice(-4)}`;
      } 
      else if (activeMethod.includes('Online Banking')) {
        paymentMethod = document.getElementById('bank-select').selectedOptions[0].text;
      } 
      else if (activeMethod.includes('e-Wallet')) {
        paymentMethod = document.getElementById('wallet-select').selectedOptions[0].text;
      }
      
      // Prepare data for AJAX request
      const formData = new FormData();
      formData.append('process_payment', 'true');
      formData.append('delivery_type', deliveryOption);
      formData.append('delivery_address', deliveryAddress);
      formData.append('contact_number', contactNumber);
      formData.append('special_instructions', specialInstructions);
      formData.append('payment_method', paymentMethod);
      
      // Get cart items
      <?php foreach ($_SESSION['cart'] as $item): ?>
        formData.append('cart_items[]', JSON.stringify({
          id: <?php echo $item['id']; ?>,
          name: '<?php echo $item['name']; ?>',
          price: <?php echo $item['price']; ?>,
          quantity: <?php echo $item['quantity']; ?>
        }));
      <?php endforeach; ?>
      
      // [Rest of your existing processPayment function]
    }
    
    // Initialize the application
    document.addEventListener('DOMContentLoaded', function() {
      // [Your existing initialization code]
      
      // Calculate initial totals
      if (document.getElementById('cart-total')) {
        const subtotal = parseFloat(document.getElementById('cart-total').textContent.replace('RM', ''));
        const deliveryFee = 0.00;
        document.getElementById('cart-total').textContent = 'RM' + (subtotal + deliveryFee).toFixed(2);
      }
    });
  </script>
</body>
</html>