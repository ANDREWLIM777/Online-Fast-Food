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

// Delivery option selection
function selectDeliveryOption(option) {
  // Update UI for option selection
  document.querySelectorAll('.delivery-option').forEach(el => {
    el.classList.remove('active');
  });
  event.currentTarget.classList.add('active');
  
  // Show/hide delivery address field
  const addressSection = document.getElementById('delivery-address-section');
  if (option === 'delivery') {
    addressSection.classList.remove('hidden');
    updateDeliveryFee(5.00);
  } else {
    addressSection.classList.add('hidden');
    updateDeliveryFee(0.00);
  }
}

// Update delivery fee and total
function updateDeliveryFee(fee) {
  const deliveryFeeRow = document.getElementById('delivery-fee-row');
  const subtotal = parseFloat(document.querySelector('.cart-summary .summary-row:first-child span:last-child').textContent.replace('RM', ''));
  const tax = subtotal * 0.06;
  
  deliveryFeeRow.querySelector('span:last-child').textContent = `RM${fee.toFixed(2)}`;
  
  // Update total
  const total = subtotal + tax + fee;
  document.querySelector('.cart-summary .summary-row.total span:last-child').textContent = `RM${total.toFixed(2)}`;
}

// Payment method selection
function selectPaymentMethod(methodId) {
  // Update UI for method selection
  document.querySelectorAll('.payment-method-option').forEach(el => {
    el.classList.remove('selected');
  });
  
  if (methodId) {
    const methodOption = document.querySelector(`.payment-method-option[onclick="selectPaymentMethod(${methodId})"]`);
    if (methodOption) {
      methodOption.classList.add('selected');
    }
  }
  
  // Hide new card form
  document.getElementById('new-card-form').classList.add('hidden');
}

// Show new card form
function showNewCardForm() {
  // Deselect all payment methods
  document.querySelectorAll('.payment-method-option').forEach(el => {
    el.classList.remove('selected');
  });
  
  // Show new card form
  document.getElementById('new-card-form').classList.remove('hidden');
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

// Process payment
function processPayment() {
  // Reset error states
  document.querySelectorAll('.error-message').forEach(el => {
    el.style.display = 'none';
  });
  document.querySelectorAll('input, select, textarea').forEach(el => {
    el.classList.remove('error');
  });
  
  // Validate delivery option
  const deliveryOption = document.querySelector('.delivery-option.active');
  if (!deliveryOption) {
    showAlert('payment', 'Please select a delivery option', 'danger');
    return;
  }
  
  const deliveryType = deliveryOption.textContent.includes('Delivery') ? 'delivery' : 'pickup';
  
  // Validate delivery address if needed
  let deliveryAddress = '';
  if (deliveryType === 'delivery') {
    deliveryAddress = document.getElementById('delivery-address').value.trim();
    if (!deliveryAddress) {
      document.getElementById('delivery-address-error').textContent = 'Please enter a delivery address';
      document.getElementById('delivery-address-error').style.display = 'block';
      document.getElementById('delivery-address').classList.add('error');
      return;
    }
  }
  
  // Validate payment method
  const selectedMethod = document.querySelector('.payment-method-option.selected');
  const newCardForm = document.getElementById('new-card-form');
  
  let paymentMethod = '';
  
  if (selectedMethod) {
    // Using saved payment method
    const methodName = selectedMethod.querySelector('.payment-method-name').textContent;
    const methodInfo = selectedMethod.querySelector('.payment-method-info').textContent;
    paymentMethod = `${methodName} (${methodInfo})`;
  } else if (!newCardForm.classList.contains('hidden')) {
    // Validate new card form
    const cardNumber = document.getElementById('new-card-number').value.replace(/\s/g, '');
    const expiry = document.getElementById('new-expiry').value;
    const cvv = document.getElementById('new-cvv').value;
    const cardName = document.getElementById('new-card-name').value.trim();
    
    if (!cardNumber || cardNumber.length < 16) {
      document.getElementById('new-card-number-error').textContent = 'Please enter a valid card number';
      document.getElementById('new-card-number-error').style.display = 'block';
      document.getElementById('new-card-number').classList.add('error');
      return;
    }
    
    if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
      document.getElementById('new-expiry-error').textContent = 'Please enter a valid expiry date (MM/YY)';
      document.getElementById('new-expiry-error').style.display = 'block';
      document.getElementById('new-expiry').classList.add('error');
      return;
    }
    
    if (!cvv || cvv.length < 3) {
      document.getElementById('new-cvv-error').textContent = 'Please enter a valid CVV';
      document.getElementById('new-cvv-error').style.display = 'block';
      document.getElementById('new-cvv').classList.add('error');
      return;
    }
    
    if (!cardName) {
      document.getElementById('new-card-name-error').textContent = 'Please enter the name on card';
      document.getElementById('new-card-name-error').style.display = 'block';
      document.getElementById('new-card-name').classList.add('error');
      return;
    }
    
    // Determine card type
    const firstDigit = cardNumber.charAt(0);
    const cardType = firstDigit === '4' ? 'VISA' : 
                   (firstDigit === '5' ? 'Mastercard' : 
                   (firstDigit === '3' ? 'American Express' : 'Card'));
    
    paymentMethod = `${cardType} •••• ${cardNumber.slice(-4)}`;
  } else {
    showAlert('payment', 'Please select or add a payment method', 'danger');
    return;
  }
  
  // Get order notes
  const notes = document.getElementById('order-notes').value.trim();
  
  // Get total amount
  const totalAmount = parseFloat(document.querySelector('.cart-summary .summary-row.total span:last-child').textContent.replace('RM', ''));
  
  // Show processing screen
  document.getElementById('payment-processing').classList.remove('hidden');
  document.getElementById('payment-success').classList.add('hidden');
  
  // Hide payment form, show confirmation
  document.querySelector('#payment.tab-content').classList.add('hidden');
  document.getElementById('confirmation').classList.remove('hidden');
  
  // Prepare data for AJAX request
  const formData = new FormData();
  formData.append('process_payment', 'true');
  formData.append('delivery_type', deliveryType);
  formData.append('delivery_address', deliveryAddress);
  formData.append('payment_method', paymentMethod);
  formData.append('notes', notes);
  
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
        `Your order #${data.order_id} has been placed successfully. Total: RM${data.total_amount.toFixed(2)}`;
      
      // Update receipt details
      document.getElementById('receipt-order-id').textContent = data.order_id;
      document.getElementById('receipt-subtotal').textContent = `RM${(data.total_amount / 1.06).toFixed(2)}`;
      document.getElementById('receipt-tax').textContent = `RM${(data.total_amount * 0.06 / 1.06).toFixed(2)}`;
      document.getElementById('receipt-delivery-fee').textContent = deliveryType === 'delivery' ? 'RM5.00' : 'RM0.00';
      document.getElementById('receipt-total').textContent = `RM${data.total_amount.toFixed(2)}`;
      document.getElementById('receipt-method').textContent = paymentMethod;
      document.getElementById('receipt-delivery-method').textContent = deliveryType === 'delivery' ? 'Delivery' : 'Pickup';
      
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

// Show order details
function showOrderDetails(orderId) {
  fetch(`get_order_details.php?order_id=${orderId}`)
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success') {
        const modal = document.getElementById('order-details-modal');
        const content = document.getElementById('order-details-content');
        
        // Build order details HTML
        let html = `
          <div class="order-details-header">
            <div><strong>Order ID:</strong> ${data.order.order_id}</div>
            <div><strong>Date:</strong> ${new Date(data.order.order_date).toLocaleString()}</div>
            <div><strong>Status:</strong> <span class="status status-${data.order.status}">${data.order.status.charAt(0).toUpperCase() + data.order.status.slice(1)}</span></div>
            <div><strong>Delivery:</strong> ${data.order.delivery_type.charAt(0).toUpperCase() + data.order.delivery_type.slice(1)}</div>
            ${data.order.delivery_address ? `<div><strong>Address:</strong> ${data.order.delivery_address}</div>` : ''}
            ${data.order.notes ? `<div><strong>Notes:</strong> ${data.order.notes}</div>` : ''}
          </div>
          
          <h3>Items</h3>
          <div class="order-details-items">
        `;
        
        data.items.forEach(item => {
          html += `
            <div class="order-details-item">
              <div class="order-details-item-name">${item.name}</div>
              <div class="order-details-item-quantity">${item.quantity}x</div>
              <div class="order-details-item-price">RM${item.price.toFixed(2)}</div>
              <div class="order-details-item-total">RM${(item.price * item.quantity).toFixed(2)}</div>
            </div>
          `;
        });
        
        html += `
          </div>
          
          <div class="order-details-summary">
            <div class="order-details-item">
              <div>Subtotal:</div>
              <div>RM${(data.order.total_amount / 1.06).toFixed(2)}</div>
            </div>
            <div class="order-details-item">
              <div>Tax (6%):</div>
              <div>RM${(data.order.total_amount * 0.06 / 1.06).toFixed(2)}</div>
            </div>
            <div class="order-details-item">
              <div>Delivery Fee:</div>
              <div>RM${data.order.delivery_fee.toFixed(2)}</div>
            </div>
            <div class="order-details-item" style="font-weight: bold;">
              <div>Total:</div>
              <div>RM${data.order.total_amount.toFixed(2)}</div>
            </div>
          </div>
          
          <div style="margin-top: 20px;">
            <strong>Payment Method:</strong> ${data.payment.method}
          </div>
        `;
        
        content.innerHTML = html;
        modal.classList.remove('hidden');
      } else {
        showAlert('history', 'Failed to load order details', 'danger');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showAlert('history', 'An error occurred while loading order details', 'danger');
    });
}

// Close modal
function closeModal() {
  document.getElementById('order-details-modal').classList.add('hidden');
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

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
  // Set up event listeners for input formatting
  document.getElementById('new-card-number').addEventListener('input', function() {
    formatCardNumber(this);
  });
  
  document.getElementById('new-expiry').addEventListener('input', function() {
    formatExpiryDate(this);
  });
  
  document.getElementById('new-cvv').addEventListener('input', function() {
    validateNumberInput(this, 4);
  });
  
  // Select pickup by default
  selectDeliveryOption('pickup');
  
  // Select first payment method if available
  const firstPaymentMethod = document.querySelector('.payment-method-option');
  if (firstPaymentMethod && !firstPaymentMethod.onclick.toString().includes('showNewCardForm')) {
    firstPaymentMethod.click();
  }
});