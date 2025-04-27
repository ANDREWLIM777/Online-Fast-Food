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