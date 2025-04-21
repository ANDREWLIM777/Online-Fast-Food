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
  
  // Load payment history
  function loadPaymentHistory() {
    const historyList = document.getElementById('history-list');
    historyList.innerHTML = '';
    
    if (appData.paymentHistory.length === 0) {
      historyList.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No payment history found.</p>';
      return;
    }
    
    appData.paymentHistory.forEach(payment => {
      const statusClass = `status-${payment.status}`;
      const statusText = payment.status.charAt(0).toUpperCase() + payment.status.slice(1);
      
      const item = document.createElement('div');
      item.className = 'history-item';
      item.innerHTML = `
        <div>
          <div style="font-weight: bold;">Order ${payment.orderId}</div>
          <div style="font-size: 0.9rem; color: var(--gray);">${payment.date}</div>
          <div style="font-size: 0.9rem; color: var(--gray);">${payment.method}</div>
        </div>
        <div style="text-align: right;">
          <div style="font-weight: bold;">RM${payment.amount.toFixed(2)}</div>
          <div class="status ${statusClass}">${statusText}</div>
        </div>
      `;
      historyList.appendChild(item);
    });
  }
  
  // Export history
  function exportHistory() {
    alert('Payment history exported successfully!');
  }
  
  // Load orders eligible for refund
  function loadRefundOrders() {
    const refundOrderSelect = document.getElementById('refund-order');
    
    // Clear existing options except the first one
    while (refundOrderSelect.options.length > 1) {
      refundOrderSelect.remove(1);
    }
    
    // Add completed payments to refund options
    appData.paymentHistory.forEach(payment => {
      if (payment.status === 'completed') {
        const option = document.createElement('option');
        option.value = payment.orderId;
        option.textContent = `${payment.orderId} (RM${payment.amount.toFixed(2)} - ${payment.date})`;
        refundOrderSelect.appendChild(option);
      }
    });
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
    
    // Add to refund requests
    appData.refundRequests.push({
      orderId: orderId,
      reason: reason,
      details: details,
      date: new Date().toLocaleString(),
      status: 'pending'
    });
    
    // Show success message
    showAlert('refund-alert', 'Refund request submitted successfully! Our team will review it shortly.', 'success');
    
    // Clear form
    document.getElementById('refund-order').value = '';
    document.getElementById('refund-reason').value = '';
    document.getElementById('refund-details').value = '';
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
  
  // Load payment methods
  function loadPaymentMethods() {
    const methodsContainer = document.getElementById('saved-methods');
    methodsContainer.innerHTML = '';
    
    if (appData.paymentMethods.length === 0) {
      methodsContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No saved payment methods found.</p>';
      return;
    }
    
    appData.paymentMethods.forEach(method => {
      const methodDiv = document.createElement('div');
      methodDiv.style.background = 'linear-gradient(to right, rgba(0,0,0,0.02), white)';
      methodDiv.style.padding = '15px';
      methodDiv.style.borderRadius = '8px';
      methodDiv.style.marginBottom = '20px';
      methodDiv.style.border = '1px solid rgba(0,0,0,0.05)';
      
      methodDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-weight: bold;">
              <img src="https://logo.clearbit.com/${method.cardType.toLowerCase()}.com?size=24" class="card-logo" onerror="this.style.display='none'">
              ${method.cardType} •••• ${method.lastFour}
            </div>
            <div style="font-size: 0.9rem; color: var(--gray);">Expires ${method.expiry}</div>
            <div style="font-size: 0.9rem; color: var(--gray);">${method.name}</div>
          </div>
          <button class="btn btn-danger" onclick="removePaymentMethod(${method.id})">
            <i class="fas fa-trash-alt"></i> Remove
          </button>
        </div>
      `;
      
      methodsContainer.appendChild(methodDiv);
    });
  }
  
  // Remove payment method
  function removePaymentMethod(id) {
    if (confirm('Are you sure you want to remove this payment method?')) {
      appData.paymentMethods = appData.paymentMethods.filter(method => method.id !== id);
      loadPaymentMethods();
      showAlert('methods-alert', 'Payment method removed successfully', 'success');
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
    
    // Determine card type (simplified)
    const cardType = cardNumber.startsWith('4') ? 'VISA' : 
                    cardNumber.startsWith('5') ? 'Mastercard' : 
                    cardNumber.startsWith('3') ? 'American Express' : 'Card';
    
    // Add new payment method
    const newMethod = {
      id: Date.now(), // Use timestamp as ID
      type: 'card',
      lastFour: cardNumber.slice(-4),
      cardType: cardType,
      expiry: expiry,
      name: name
    };
    
    appData.paymentMethods.push(newMethod);
    
    // Reload methods
    loadPaymentMethods();
    
    // Clear form
    document.getElementById('new-card-number').value = '';
    document.getElementById('new-expiry').value = '';
    document.getElementById('new-cvv').value = '';
    document.getElementById('new-card-name').value = '';
    
    showAlert('methods-alert', 'Payment method added successfully', 'success');
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
    loadPaymentHistory();
    loadRefundOrders();
    loadPaymentMethods();
  });