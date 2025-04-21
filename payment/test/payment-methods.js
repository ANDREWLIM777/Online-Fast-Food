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
async function downloadReceipt(orderId) {
  try {
    const response = await fetch(`receipt.php?order_id=${orderId}`);
    if (response.ok) {
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `receipt_${orderId}.pdf`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
      showAlert('confirmation-alert', 'Receipt downloaded successfully!', 'success');
    } else {
      throw new Error('Failed to download receipt');
    }
  } catch (error) {
    console.error('Error downloading receipt:', error);
    showAlert('confirmation-alert', 'Failed to download receipt', 'danger');
  }
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
async function loadPaymentHistory() {
  const historyList = document.getElementById('history-list');
  historyList.innerHTML = '<div class="loading">Loading payment history...</div>';
  
  try {
    const response = await fetch('payment_history.php');
    if (!response.ok) throw new Error('Network response was not ok');
    
    const payments = await response.json();
    
    if (payments.length === 0) {
      historyList.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No payment history found.</p>';
      return;
    }
    
    historyList.innerHTML = '';
    payments.forEach(payment => {
      const statusClass = `status-${payment.status}`;
      const statusText = payment.status.charAt(0).toUpperCase() + payment.status.slice(1);
      
      const item = document.createElement('div');
      item.className = 'history-item';
      item.innerHTML = `
        <div>
          <div style="font-weight: bold;">Order ${payment.order_id}</div>
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
  } catch (error) {
    console.error('Error loading payment history:', error);
    historyList.innerHTML = '<div class="error-message">Failed to load payment history. Please try again.</div>';
  }
}

// Export history
async function exportHistory() {
  try {
    const response = await fetch('export_history.php');
    if (response.ok) {
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'payment_history.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
      showAlert('history-alert', 'Payment history exported successfully!', 'success');
    } else {
      throw new Error('Export failed');
    }
  } catch (error) {
    console.error('Error exporting history:', error);
    showAlert('history-alert', 'Failed to export payment history', 'danger');
  }
}

// Load orders eligible for refund
async function loadRefundOrders() {
  const refundOrderSelect = document.getElementById('refund-order');
  
  // Clear existing options except the first one
  while (refundOrderSelect.options.length > 1) {
    refundOrderSelect.remove(1);
  }
  
  try {
    const response = await fetch('refund_orders.php');
    if (!response.ok) throw new Error('Network response was not ok');
    
    const orders = await response.json();
    
    // Add completed payments to refund options
    orders.forEach(order => {
      const option = document.createElement('option');
      option.value = order.order_id;
      option.textContent = `${order.order_id} (RM${order.amount.toFixed(2)} - ${order.date})`;
      refundOrderSelect.appendChild(option);
    });
  } catch (error) {
    console.error('Error loading refund orders:', error);
    showAlert('refund-alert', 'Failed to load refund orders', 'danger');
  }
}

// Submit refund request
async function submitRefundRequest() {
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
  
  try {
    const response = await fetch('refund_requests.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_id: orderId,
        reason: reason,
        details: details
      })
    });
    
    if (!response.ok) throw new Error('Refund request failed');
    
    const result = await response.json();
    
    // Show success message
    showAlert('refund-alert', 'Refund request submitted successfully! Our team will review it shortly.', 'success');
    
    // Clear form
    document.getElementById('refund-order').value = '';
    document.getElementById('refund-reason').value = '';
    document.getElementById('refund-details').value = '';
    
    // Reload refund orders
    await loadRefundOrders();
  } catch (error) {
    console.error('Error submitting refund request:', error);
    showAlert('refund-alert', 'Failed to submit refund request. Please try again.', 'danger');
  }
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
async function loadPaymentMethods() {
  const methodsContainer = document.getElementById('saved-methods');
  methodsContainer.innerHTML = '<div class="loading">Loading payment methods...</div>';
  
  try {
    const response = await fetch('payment_methods.php');
    if (!response.ok) throw new Error('Network response was not ok');
    
    const methods = await response.json();
    
    if (methods.length === 0) {
      methodsContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No saved payment methods found.</p>';
      return;
    }
    
    methodsContainer.innerHTML = '';
    methods.forEach(method => {
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
              <img src="https://logo.clearbit.com/${method.card_type.toLowerCase()}.com?size=24" class="card-logo" onerror="this.style.display='none'">
              ${method.card_type} •••• ${method.last_four}
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
  } catch (error) {
    console.error('Error loading payment methods:', error);
    methodsContainer.innerHTML = '<div class="error-message">Failed to load payment methods. Please try again.</div>';
  }
}

// Remove payment method
async function removePaymentMethod(id) {
  if (!confirm('Are you sure you want to remove this payment method?')) {
    return;
  }
  
  try {
    const response = await fetch('payment_methods.php', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ id: id })
    });
    
    if (!response.ok) throw new Error('Failed to remove payment method');
    
    await loadPaymentMethods();
    showAlert('methods-alert', 'Payment method removed successfully', 'success');
  } catch (error) {
    console.error('Error removing payment method:', error);
    showAlert('methods-alert', 'Failed to remove payment method', 'danger');
  }
}

// Save new payment method
async function savePaymentMethod() {
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
  
  try {
    const response = await fetch('payment_methods.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        card_number: cardNumber,
        expiry: expiry,
        cvv: cvv,
        name: name,
        card_type: cardType
      })
    });
    
    if (!response.ok) throw new Error('Failed to save payment method');
    
    // Clear form
    document.getElementById('new-card-number').value = '';
    document.getElementById('new-expiry').value = '';
    document.getElementById('new-cvv').value = '';
    document.getElementById('new-card-name').value = '';
    
    // Reload methods
    await loadPaymentMethods();
    
    showAlert('methods-alert', 'Payment method added successfully', 'success');
  } catch (error) {
    console.error('Error saving payment method:', error);
    showAlert('methods-alert', 'Failed to add payment method', 'danger');
  }
}